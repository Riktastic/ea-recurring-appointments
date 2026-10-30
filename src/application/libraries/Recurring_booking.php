<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Repeating appointments booked by customers on the public booking page.
 *
 * The core booking flow stays in charge of the first appointment: captcha, customer record, consents, availability
 * check, e-mail and calendar sync all happen in Booking::register as usual. When that request succeeds and the customer
 * chose a repeat, the display hook calls extend() with the new appointment's hash. extend() then books the following
 * dates for the same customer, service, provider and time, but only where the core availability library would also
 * offer that time slot on the booking page. Dates that are not free are skipped and reported.
 *
 * Customers can only choose from a fixed list of patterns (PATTERNS) and up to the administrator's maximum number of
 * appointments, so a public request can never create more than that.
 *
 * @package Libraries
 */
class Recurring_booking
{
    /**
     * Patterns a customer can choose, mapped to a recurrence rule (the weekday comes from the first appointment).
     */
    public const PATTERNS = [
        'weekly' => ['frequency' => 'weekly', 'interval' => 1],
        'every_2_weeks' => ['frequency' => 'weekly', 'interval' => 2],
        'every_4_weeks' => ['frequency' => 'weekly', 'interval' => 4],
        'monthly' => ['frequency' => 'monthly_weekday', 'interval' => 1],
    ];

    /**
     * A repeat can only be added to an appointment that was booked moments ago, in the same request.
     */
    private const MAX_AGE_SECONDS = 600;

    /**
     * Fields copied from the first appointment to the following ones.
     */
    private const TEMPLATE_FIELDS = [
        'id_services',
        'id_users_provider',
        'id_users_customer',
        'location',
        'meeting_link',
        'notes',
        'color',
        'status',
    ];

    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->helper('recurring_appointments');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('appointment_series_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('customers_model');
        $this->CI->load->library('availability');
        $this->CI->load->library('notifications');
        $this->CI->load->library('synchronization');
        $this->CI->load->library('webhooks_client');

        require_once APPPATH . 'libraries/Recurrence_rule.php';
    }

    /**
     * Highest number of appointments (the first one included) a customer can book at once.
     */
    public static function max_count(): int
    {
        return max(2, min(RA_BOOKING_MAX_LIMIT, (int) ra_setting('booking_max')));
    }

    /**
     * Book the following dates of a repeat that a customer chose on the booking page.
     *
     * @param string $appointment_hash Hash of the appointment Booking::register just created.
     * @param array $repeat Request values: pattern (a PATTERNS key) and count (appointments in total, first included).
     *
     * @return array|null Null when nothing was asked or allowed, otherwise:
     *                    ['series_id' => int, 'booked' => string[] start datetimes (first included),
     *                     'skipped' => string[] start datetimes that were not free,
     *                     'timezone' => string the customer's timezone, which booked and skipped are in]
     */
    public function extend(string $appointment_hash, array $repeat): ?array
    {
        if (ra_setting('booking_enabled') !== '1' || !$this->CI->appointment_series_model->is_installed()) {
            return null;
        }

        $pattern = (string) ($repeat['pattern'] ?? '');
        $count = (int) ($repeat['count'] ?? 0);

        if (!isset(self::PATTERNS[$pattern]) || $count < 2) {
            return null; // "Does not repeat" or a tampered request: the single appointment stays as it is.
        }

        $count = min($count, self::max_count());

        $first = $this->CI->appointments_model->get(['hash' => $appointment_hash])[0] ?? null;

        if (
            !$first ||
            !empty($first['is_unavailability']) ||
            abs(time() - strtotime((string) $first['book_datetime'])) > self::MAX_AGE_SECONDS ||
            $this->CI->appointment_series_model->find_series_id((int) $first['id'])
        ) {
            return null;
        }

        $start = new DateTimeImmutable($first['start_datetime']);
        $duration = (int) round((strtotime($first['end_datetime']) - $start->getTimestamp()) / 60);

        $raw_rule = self::PATTERNS[$pattern] + ['count' => $count];

        if ($raw_rule['frequency'] === 'monthly_weekday') {
            // "The 5th Tuesday" does not exist in most months, so the last week of the month becomes "last Tuesday".
            $nth = (int) ceil((int) $start->format('j') / 7);
            $raw_rule['nth'] = $nth === 5 ? -1 : $nth;
        }

        $rule = Recurrence_rule::from_array($raw_rule, $start->format('Y-m-d'));

        // The first date is the appointment that already exists.
        $dates = array_slice($rule->dates($start->format('Y-m-d')), 1);

        $service = $this->CI->services_model->find((int) $first['id_services']);
        $provider = $this->CI->providers_model->find((int) $first['id_users_provider']);

        $template = [];

        foreach (self::TEMPLATE_FIELDS as $field) {
            $template[$field] = $first[$field] ?? null;
        }

        $booked = [$first['start_datetime']];
        $skipped = [];
        $created_ids = [];

        $this->CI->db->trans_begin();

        try {
            $series_id = $this->CI->appointment_series_model->insert_series([
                'rule' => json_encode($rule->to_array()),
                'start_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'duration' => $duration,
                'id_users_provider' => (int) $first['id_users_provider'],
                'id_users_customer' => (int) $first['id_users_customer'],
                'id_services' => (int) $first['id_services'],
                'id_users_created_by' => null, // Booked by the customer.
            ]);

            $this->CI->appointment_series_model->link($series_id, (int) $first['id'], 1, $start->format('Y-m-d'));

            foreach ($dates as $index => $date) {
                $occurrence_start = new DateTimeImmutable($date . ' ' . $start->format('H:i:s'));

                if (!$this->is_bookable($occurrence_start, $service, $provider)) {
                    $skipped[] = $occurrence_start->format('Y-m-d H:i:s');
                    continue;
                }

                $appointment_id = $this->CI->appointments_model->save(
                    array_merge($template, [
                        'start_datetime' => $occurrence_start->format('Y-m-d H:i:s'),
                        'end_datetime' => $occurrence_start->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
                        'is_unavailability' => false,
                    ]),
                );

                $this->CI->appointment_series_model->link($series_id, $appointment_id, $index + 2, $date);

                $created_ids[] = $appointment_id;
                $booked[] = $occurrence_start->format('Y-m-d H:i:s');
            }

            $this->CI->db->trans_commit();
        } catch (Throwable $e) {
            $this->CI->db->trans_rollback();

            throw $e;
        }

        $this->after_save($created_ids, $service, $provider);

        // Times are stored in the provider's timezone; the customer sees them in their own, like in the e-mails.
        $customer = $this->CI->customers_model->find((int) $first['id_users_customer']);
        $customer_timezone = (string) ($customer['timezone'] ?? '') ?: (string) $provider['timezone'];

        $to_customer = fn(array $datetimes) => array_map(
            fn(string $datetime) => $this->to_timezone($datetime, (string) $provider['timezone'], $customer_timezone),
            $datetimes,
        );

        return [
            'series_id' => $series_id,
            'booked' => $to_customer($booked),
            'skipped' => $to_customer($skipped),
            'timezone' => $customer_timezone,
        ];
    }

    private function to_timezone(string $datetime, string $from, string $to): string
    {
        try {
            return (new DateTime($datetime, new DateTimeZone($from ?: date_default_timezone_get())))
                ->setTimezone(new DateTimeZone($to ?: date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return $datetime; // Unknown timezone name: show the stored time rather than nothing.
        }
    }

    /**
     * Whether the booking page itself would offer this time slot to a customer: the core availability library applies
     * the working plan, breaks, exceptions, other appointments, buffers, group sizes and the "book in advance" timeout.
     * The future booking limit is checked here, since the booking page applies it to the date picker only.
     */
    private function is_bookable(DateTimeImmutable $start, array $service, array $provider): bool
    {
        $limit_days = (int) setting('future_booking_limit');

        if ($limit_days > 0 && $start > new DateTimeImmutable('+' . $limit_days . ' days')) {
            return false;
        }

        $hours = $this->CI->availability->get_available_hours($start->format('Y-m-d'), $service, $provider);

        return in_array($start->format('H:i'), $hours, true);
    }

    /**
     * Calendar sync, e-mails and webhooks for the extra appointments, like Booking::register does for the first one.
     */
    private function after_save(array $appointment_ids, array $service, array $provider): void
    {
        $company_color = setting('company_color');
        $default_color = defined('DEFAULT_COMPANY_COLOR') ? DEFAULT_COMPANY_COLOR : null;

        $settings = [
            'company_name' => setting('company_name'),
            'company_link' => setting('company_link'),
            'company_email' => setting('company_email'),
            'company_color' => !empty($company_color) && $company_color != $default_color ? $company_color : null,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ];

        $notify = ra_setting('booking_notify') === 'all';

        foreach ($appointment_ids as $appointment_id) {
            // External services must not undo the booking: a failing mail server or calendar is logged, not thrown.
            try {
                $appointment = $this->CI->appointments_model->find($appointment_id);
                $customer = $this->CI->customers_model->find((int) $appointment['id_users_customer']);

                $this->CI->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

                if ($notify) {
                    $this->CI->notifications->notify_appointment_saved(
                        $appointment,
                        $service,
                        $provider,
                        $customer,
                        $settings,
                        false,
                    );
                }

                $this->CI->webhooks_client->trigger(WEBHOOK_APPOINTMENT_SAVE, $appointment);
            } catch (Throwable $e) {
                log_message('error', 'Recurring appointments: follow-up of appointment ' . $appointment_id . ' failed: ' . $e->getMessage());
            }
        }
    }
}
