<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Display override hook: the one place where the extension joins the core pages, without editing core views or scripts.
 *
 * CodeIgniter calls this instead of sending the output itself. Depending on the page that was rendered:
 *
 * | Request                          | What the extension does                                                     |
 * |----------------------------------|-----------------------------------------------------------------------------|
 * | calendar (backend)               | adds the calendar script (Repeat section, series panel, markers)            |
 * | booking_settings (backend)       | adds the "Repeating appointments" settings to Settings → Booking            |
 * | booking (public booking page)    | adds the Repeat choice, when the administrator allowed it                  |
 * | booking/register (JSON)          | books the following dates after the core booked the first one              |
 * | booking_confirmation/of/<hash>   | lists the booked and skipped dates                                         |
 *
 * Every other page and response is sent unchanged, and any failure in here is logged and falls back to sending the
 * original output, so the extension can never break a core page.
 */
function recurring_appointments_inject_script(): void
{
    $CI = &get_instance();

    $output = $CI->output->get_output();

    try {
        if ($CI instanceof CI_Controller && is_string($output)) {
            $output = recurring_appointments_handle($CI, $output);
        }
    } catch (Throwable $e) {
        log_message('error', 'Recurring appointments: ' . $e->getMessage());
    }

    $CI->output->_display($output);
}

/**
 * Route the output to the matching handler.
 */
function recurring_appointments_handle(object $CI, string $output): string
{
    $page = strtolower($CI->router->class) . '/' . $CI->router->method;

    return match ($page) {
        'calendar/index', 'calendar/reschedule' => recurring_appointments_page_script($CI, $output, 'recurring_appointments.js'),
        'booking_settings/index' => recurring_appointments_page_script($CI, $output, 'recurring_appointments_settings.js'),
        'booking/index' => recurring_appointments_booking_page($CI, $output),
        'booking/register' => recurring_appointments_after_register($CI, $output),
        'booking_confirmation/of' => recurring_appointments_confirmation($CI, $output),
        default => $output,
    };
}

/**
 * Add an extension script with its configuration (translated strings, settings) before </body> of an HTML page.
 */
function recurring_appointments_page_script(object $CI, string $output, string $script, array $extra = []): string
{
    if (stripos($output, '</body>') === false) {
        return $output; // Not a full page (e.g. a redirect or an error).
    }

    $CI->load->helper('recurring_appointments');
    $CI->load->model('appointment_series_model');

    $script_path = 'assets/js/' . $script;
    $version = is_file(FCPATH . $script_path) ? filemtime(FCPATH . $script_path) : '1';

    $settings = [];

    foreach (array_keys(RA_SETTING_DEFAULTS) as $name) {
        $settings[$name] = ra_setting($name);
    }

    $config = $extra + [
        'language' => config('language') ?: 'english',
        'locale' => recurring_appointments_locale(),
        'strings' => ra_strings(),
        'installed' => $CI->appointment_series_model->is_installed(),
        'can_install' => session('role_slug') === DB_SLUG_ADMIN,
        'settings' => $settings,
        'defaults' => RA_SETTING_DEFAULTS,
        'booking_max_limit' => RA_BOOKING_MAX_LIMIT,
        'max_occurrences_limit' => 1000, // Recurrence_rule::MAX_LIMIT, without loading the class on every page.
    ];

    // JSON_HEX_* keeps "</script>" and quotes inside translations from breaking out of the script element.
    $snippet =
        '<script>window.RecurringAppointmentsConfig = ' .
        json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) .
        ';</script>' .
        PHP_EOL .
        '<script src="' .
        html_escape(base_url($script_path . '?v=' . $version)) .
        '"></script>' .
        PHP_EOL;

    return recurring_appointments_before_body_end($output, $snippet);
}

/**
 * Public booking page: only for a new booking (not when a customer reschedules), and only when allowed in the settings.
 */
function recurring_appointments_booking_page(object $CI, string $output): string
{
    $CI->load->helper('recurring_appointments');
    $CI->load->model('appointment_series_model');

    if (ra_setting('booking_enabled') !== '1' || !$CI->appointment_series_model->is_installed()) {
        return $output;
    }

    $CI->load->library('recurring_booking');

    return recurring_appointments_page_script($CI, $output, 'recurring_appointments_booking.js', [
        'booking_max' => Recurring_booking::max_count(),
    ]);
}

/**
 * After Booking::register: when the customer chose a repeat and the first appointment was booked, book the others.
 *
 * The result is added to the JSON response (the core script ignores unknown keys) and kept in the session for the
 * confirmation page. A failure here never undoes the first appointment, which the core already booked and e-mailed.
 */
function recurring_appointments_after_register(object $CI, string $output): string
{
    $repeat = $CI->input->post('ra_repeat');

    if (!is_array($repeat)) {
        return $output;
    }

    $response = json_decode($output, true);
    $hash = is_array($response) ? (string) ($response['appointment_hash'] ?? '') : '';
    $post_data = $CI->input->post('post_data');

    if ($hash === '' || filter_var($post_data['manage_mode'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return $output;
    }

    $CI->load->library('recurring_booking');

    $any_provider_value = defined('ANY_PROVIDER') ? ANY_PROVIDER : 'any-provider';
    $any_provider = (string) ($post_data['appointment']['id_users_provider'] ?? '') === (string) $any_provider_value;

    $result = $CI->recurring_booking->extend($hash, $repeat, $any_provider);

    if ($result === null) {
        return $output;
    }

    $CI->session->set_flashdata('recurring_appointments_booking_' . $hash, $result);

    $response['recurring'] = ['booked' => count($result['booked']), 'skipped' => count($result['skipped'])];

    return json_encode($response);
}

/**
 * Confirmation page: list the dates of the series the customer just booked, and the dates that were not free.
 */
function recurring_appointments_confirmation(object $CI, string $output): string
{
    $hash = (string) $CI->uri->segment(3);
    $result = $CI->session->flashdata('recurring_appointments_booking_' . $hash);

    if (!is_array($result) || stripos($output, '</body>') === false) {
        return $output;
    }

    $CI->load->helper('recurring_appointments');

    $format = function (string $datetime): string {
        return function_exists('format_date_time') ? format_date_time($datetime) : $datetime;
    };

    $booked = $result['booked'] ?? [];
    $skipped = $result['skipped'] ?? [];

    // Name the provider per date only when not every date has the same one ("Any provider").
    $show_providers = count(array_unique(array_column($booked, 'provider'))) > 1;

    $html = '<div id="ra-booking-result" class="text-start mx-auto my-4" style="max-width: 420px;">';

    if (count($booked) > 1) {
        $html .= '<h5 class="mb-2">&#8635; ' . html_escape(ra_lang('booking_booked_title', ['count' => count($booked)])) . '</h5><ul class="mb-3">';

        foreach ($booked as $item) {
            $text = $format($item['start']) . ($show_providers ? ' – ' . $item['provider'] : '');
            $html .= '<li>' . html_escape($text) . '</li>';
        }

        $html .= '</ul>';
    } elseif ($skipped) {
        $html .= '<p class="mb-2">' . html_escape(ra_lang('booking_only_first')) . '</p>';
    }

    if (!empty($result['capped'])) {
        $html .= '<p class="mb-2">' . html_escape(ra_lang('booking_capped', ['max' => $result['capped']])) . '</p>';
    }

    if ($skipped) {
        $html .= '<p class="mb-1 text-muted">' . html_escape(ra_lang('booking_skipped_title')) . '</p><ul class="text-muted">';

        foreach ($skipped as $datetime) {
            $html .= '<li>' . html_escape($format($datetime)) . '</li>';
        }

        $html .= '</ul>';
    }

    // The times above are in the customer's timezone, named like in the confirmation e-mail.
    if (!empty($result['timezone']) && function_exists('format_timezone')) {
        $html .= '<p class="small text-muted mb-0">' . html_escape(lang('timezone') . ': ' . format_timezone($result['timezone'])) . '</p>';
    }

    $html .= '</div>';

    // Shown under the "Appointment registered" heading; the template keeps it out of sight until it is moved there.
    $snippet =
        '<template id="ra-booking-result-template">' .
        $html .
        '</template><script>(function () {' .
        'var t = document.getElementById("ra-booking-result-template");' .
        'var h = document.querySelector("h3.text-success") || document.querySelector("main, .container, body");' .
        'if (t && h) { h.insertAdjacentElement(h.tagName === "H3" ? "afterend" : "afterbegin", t.content.firstElementChild); }' .
        '})();</script>';

    return recurring_appointments_before_body_end($output, $snippet);
}

function recurring_appointments_before_body_end(string $output, string $snippet): string
{
    $position = strripos($output, '</body>');

    return $position === false ? $output : substr($output, 0, $position) . $snippet . substr($output, $position);
}

/**
 * Locale code of the active language (e.g. "nl"), for weekday names from the browser's Intl API.
 */
function recurring_appointments_locale(): string
{
    $language_code = (string) (config('language_code') ?: '');

    if ($language_code === '') {
        $codes = config('language_codes') ?: [];
        $language_code = (string) (array_search(config('language'), $codes, true) ?: 'en');
    }

    return $language_code;
}
