<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

if (!function_exists('ra_strings')) {
    /**
     * Strings of the extension in the active Easy!Appointments language, with English filling in every missing key.
     *
     * The language files are read directly instead of through CI_Lang::load(), because CI_Lang::load() aborts the
     * request when a file is missing, while a missing translation must simply fall back to English.
     *
     * @param string|null $language Language folder name (e.g. "dutch"), defaults to the active language.
     *
     * @return array<string, string>
     */
    function ra_strings(?string $language = null): array
    {
        static $cache = [];

        $language = $language ?? ((string) config('language') ?: 'english');

        // Only plain folder names, never a path.
        if (!preg_match('/^[a-z\-]+$/', $language)) {
            $language = 'english';
        }

        if (isset($cache[$language])) {
            return $cache[$language];
        }

        $read = function (string $name): array {
            $file = APPPATH . 'language/' . $name . '/recurring_appointments_lang.php';

            if (!is_file($file)) {
                return [];
            }

            $lang = [];

            include $file;

            return $lang;
        };

        return $cache[$language] = array_merge($read('english'), $language === 'english' ? [] : $read($language));
    }
}

if (!function_exists('ra_setting')) {
    /**
     * Setting of the extension, with its default when an administrator never saved it.
     *
     * The values live in the core settings table (name prefixed with "recurring_appointments_") and are edited on
     * Settings → Booking, where the core Save button stores them together with the other booking settings.
     *
     * @param string $name Name without the prefix, see RA_SETTING_DEFAULTS.
     */
    function ra_setting(string $name): string
    {
        $default = RA_SETTING_DEFAULTS[$name] ?? '';
        $value = setting('recurring_appointments_' . $name);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    /**
     * Defaults of the settings:
     *
     * - staff_enabled:     staff can create repeating appointments in the calendar.
     * - staff_default_on:  the Repeat switch starts switched on for new appointments.
     * - booking_enabled:   customers can choose a repeat on the public booking page.
     * - booking_max:       the most appointments a customer can book at once (the first one included).
     * - max_occurrences:   the most appointments one series can have (staff), 2 - 1000.
     * - booking_notify:    "all" sends the usual confirmation e-mail for every extra appointment (each with its own
     *                      cancel and reschedule link), "first" only the one for the first appointment.
     */
    define('RA_SETTING_DEFAULTS', [
        'staff_enabled' => '1',
        'staff_default_on' => '0',
        'booking_enabled' => '0',
        'booking_max' => '8',
        'booking_notify' => 'all',
        'max_occurrences' => '400',
    ]);

    /**
     * Upper limit for booking_max, whatever an administrator enters.
     */
    define('RA_BOOKING_MAX_LIMIT', 52);
}

if (!function_exists('ra_load_optional')) {
    /**
     * Load a model or library only when the running Easy!Appointments release ships it.
     *
     * @param string $type "model" or "library".
     * @param string $name Lowercase name, e.g. "working_plan_exceptions_model".
     */
    function ra_load_optional(string $type, string $name): void
    {
        $folder = $type === 'model' ? 'models' : 'libraries';

        if (is_file(APPPATH . $folder . '/' . ucfirst($name) . '.php')) {
            get_instance()->load->$type($name);
        }
    }
}

if (!function_exists('ra_authorize_event_write')) {
    /**
     * Provider scope check before writing an appointment.
     *
     * Uses the core authorize_event_write() where available (Easy!Appointments 1.6) and an equivalent check otherwise:
     * providers may only touch their own records, secretaries those of their assigned providers.
     */
    function ra_authorize_event_write(mixed $record_id, mixed $provider_id): void
    {
        if (function_exists('authorize_event_write')) {
            authorize_event_write($record_id, $provider_id);

            return;
        }

        $CI = &get_instance();

        $can_manage = function (int $id) use ($CI): bool {
            $user_id = (int) session('user_id');
            $role_slug = session('role_slug');

            if ($role_slug === DB_SLUG_PROVIDER) {
                return $user_id === $id;
            }

            if ($role_slug === DB_SLUG_SECRETARY) {
                $CI->load->model('secretaries_model');

                return in_array($id, array_map('intval', $CI->secretaries_model->find($user_id)['providers'] ?? []), true);
            }

            return true;
        };

        if (!empty($record_id)) {
            $record = $CI->db->get_where('appointments', ['id' => (int) $record_id])->row_array();

            if (!$record || !$can_manage((int) $record['id_users_provider'])) {
                abort(403, 'Forbidden');
            }
        }

        if (!empty($provider_id) && !$can_manage((int) $provider_id)) {
            abort(403, 'Forbidden');
        }
    }
}

if (!function_exists('ra_lang')) {
    /**
     * Translate a key of the extension and fill in {placeholders}.
     *
     * @param string $key Key without or with the "ra_" prefix.
     * @param array $params Placeholder values.
     */
    function ra_lang(string $key, array $params = []): string
    {
        $key = str_starts_with($key, 'ra_') ? $key : 'ra_' . $key;

        $text = ra_strings()[$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }
}
