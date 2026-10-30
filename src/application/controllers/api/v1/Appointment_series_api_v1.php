<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Appointment series REST API (v1), next to the core /api/v1/appointments.
 *
 * Same authentication as the core API (API token as Bearer, or admin Basic auth) and the same field names as
 * /api/v1/appointments. The occurrences themselves stay regular appointments: read or change one of them through
 * /api/v1/appointments/{id}.
 *
 * | Request                                   | What it does                                                        |
 * |-------------------------------------------|---------------------------------------------------------------------|
 * | GET    /api/v1/appointment_series         | list series (?providerId, ?customerId, ?serviceId, ?limit, ?page)   |
 * | GET    /api/v1/appointment_series/{id}    | one series with its occurrences                                     |
 * | POST   /api/v1/appointment_series         | create a series (see store())                                       |
 * | PUT    /api/v1/appointment_series/{id}    | change every occurrence, or from a date on (see update())           |
 * | DELETE /api/v1/appointment_series/{id}    | delete every occurrence, or from a date on (?from=Y-m-d)            |
 *
 * The routes are added by config/recurring_appointments_routes.php.
 *
 * @package Controllers
 */
class Appointment_series_api_v1 extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper('recurring_appointments');

        $this->load->model('appointment_series_model');
        $this->load->model('appointments_model');

        $this->load->library('api');
        $this->load->library('recurring_series');

        $this->api->auth();
    }

    /**
     * List series.
     */
    public function index(): void
    {
        try {
            $this->require_installed();

            $where = [];

            foreach (['providerId' => 'id_users_provider', 'customerId' => 'id_users_customer', 'serviceId' => 'id_services'] as $param => $column) {
                if (!empty(request($param))) {
                    $where[$column] = (int) request($param);
                }
            }

            $series = $this->appointment_series_model->get_series($where, $this->api->request_limit(), $this->api->request_offset());

            json_response(array_map(fn($row) => $this->encode($row, false), $series));
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * One series with its occurrences.
     */
    public function show(?int $id = null): void
    {
        try {
            $this->require_installed();

            $series = $this->find_or_404($id);

            if ($series !== null) {
                json_response($this->encode($series, true));
            }
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Create a series.
     *
     * JSON body: the first appointment with the fields of /api/v1/appointments (start, end, serviceId, providerId,
     * customerId, location, notes, color, status, meetingLink), plus:
     *
     * - rule:         {"frequency": "daily|weekly|monthly_weekday|monthly_day|yearly", "interval": 1, "weekdays": [3],
     *                  "nth": 1, "weekday": 1, "day": 15, "month": 3, "count": 10 | "until": "2027-06-30"}
     * - conflictMode: "abort" (default: answer 409 with the conflicts, create nothing), "skip" or "force".
     * - notify:       "none" (default), "first" or "all".
     *
     * Answers 201 with the series, 409 when dates conflict and conflictMode is "abort".
     */
    public function store(): void
    {
        try {
            $this->require_installed();

            $body = request();

            if (!is_array($body)) {
                throw new InvalidArgumentException(ra_lang('error_invalid_request', ['detail' => 'JSON body']));
            }

            $rule = is_array($body['rule'] ?? null) ? $body['rule'] : [];
            $conflict_mode = $this->enum($body['conflictMode'] ?? 'abort', Recurring_series::CONFLICT_MODES, 'conflictMode');
            $notify = $this->enum($body['notify'] ?? 'none', Recurring_series::NOTIFY_MODES, 'notify');

            $appointment = $body;
            $this->appointments_model->api_decode($appointment);
            unset($appointment['id']);

            if (empty($appointment['end_datetime']) && !empty($appointment['start_datetime'])) {
                $appointment['end_datetime'] = $this->appointments_model->calculate_end_datetime($appointment);
            }

            if (empty($appointment['id_users_customer'])) {
                throw new InvalidArgumentException(ra_lang('error_no_customer'));
            }

            [$expanded_rule, $occurrences] = $this->recurring_series->expand($rule, $appointment);

            if (empty($occurrences)) {
                throw new InvalidArgumentException(ra_lang('error_no_occurrences'));
            }

            $conflicting = array_values(array_filter($occurrences, fn($o) => !empty($o['conflicts'])));

            if ($conflicting && $conflict_mode === 'abort') {
                json_response(['message' => 'Some dates conflict.', 'occurrences' => $conflicting], 409);

                return;
            }

            if ($conflict_mode === 'skip' && count($conflicting) === count($occurrences)) {
                throw new RuntimeException(ra_lang('error_all_conflict'));
            }

            $result = $this->recurring_series->create($appointment, $expanded_rule, $occurrences, $conflict_mode, $notify, null);

            $series = $this->appointment_series_model->find($result['series_id']);

            json_response(
                $this->encode($series, true) + [
                    'skipped' => array_map(
                        fn($o) => ['start' => $o['start_datetime'], 'end' => $o['end_datetime']],
                        $result['skipped'],
                    ),
                ],
                201,
            );
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Change every occurrence, or those from a date on.
     *
     * JSON body: any of the /api/v1/appointments fields (start, end, serviceId, providerId, customerId, location,
     * notes, color, status, meetingLink), plus:
     *
     * - from:         optional Y-m-d: only the occurrences on or after this date (they become a series of their own
     *                 when they move to another day).
     * - conflictMode: "abort" (default: answer 409 with the conflicts, change nothing), "skip" or "force".
     * - notify:       "none" (default), "first" or "all".
     *
     * start/end describe the first occurrence in scope: the others get the same time of day and duration. When start
     * moves that occurrence to another day, every occurrence in scope moves by the same number of days (every
     * Wednesday → every Thursday). Without start/end, only the other fields change.
     *
     * Answers 200 with the series the changed occurrences belong to.
     */
    public function update(int $id): void
    {
        try {
            $this->require_installed();

            if ($this->find_or_404($id) === null) {
                return;
            }

            $body = request();

            if (!is_array($body)) {
                throw new InvalidArgumentException(ra_lang('error_invalid_request', ['detail' => 'JSON body']));
            }

            $from = (string) ($body['from'] ?? '');
            $conflict_mode = $this->enum($body['conflictMode'] ?? 'abort', Recurring_series::CONFLICT_MODES, 'conflictMode');
            $notify = $this->enum($body['notify'] ?? 'none', Recurring_series::NOTIFY_MODES, 'notify');

            if ($from !== '' && !DateTimeImmutable::createFromFormat('!Y-m-d', $from)) {
                throw new InvalidArgumentException(ra_lang('error_invalid_date', ['value' => $from]));
            }

            $first = $this->appointment_series_model->get_occurrences($id, $from !== '' ? $from . ' 00:00:00' : null)[0] ?? null;

            if ($first === null) {
                throw new InvalidArgumentException(ra_lang('error_no_occurrences'));
            }

            $current = $this->appointments_model->find((int) $first['id']);

            // The new values on top of the current ones, with the same field names as /api/v1/appointments.
            $appointment = array_diff_key($body, array_flip(['from', 'conflictMode', 'notify', 'id', 'rule']));
            $this->appointments_model->api_decode($appointment, $current);

            // A new start without an end keeps the duration.
            if (array_key_exists('start', $body) && !array_key_exists('end', $body)) {
                $appointment['end_datetime'] = (new DateTimeImmutable($appointment['start_datetime']))
                    ->modify('+' . $this->recurring_series->duration_minutes($current) . ' minutes')
                    ->format('Y-m-d H:i:s');
            }

            $scope = $from !== '' ? 'following' : 'all';

            $plan = $this->recurring_series->plan_update($id, $current, $appointment, $scope);

            if ($plan['conflict_count'] && $conflict_mode === 'abort') {
                json_response(
                    [
                        'message' => 'Some dates conflict.',
                        'occurrences' => array_values(array_filter($plan['planned'], fn($o) => !empty($o['conflicts']))),
                    ],
                    409,
                );

                return;
            }

            $this->recurring_series->apply_update($id, $plan, $appointment, $conflict_mode, $notify, null);

            $series_id = $this->appointment_series_model->find_series_id((int) $current['id']) ?? $id;

            json_response($this->encode($this->appointment_series_model->find($series_id), true));
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Delete every occurrence, or those that start on or after ?from=Y-m-d. ?notify=none|first|all (default none).
     */
    public function destroy(int $id): void
    {
        try {
            $this->require_installed();

            $series = $this->find_or_404($id);

            if ($series === null) {
                return;
            }

            $from = (string) request('from');
            $notify = $this->enum(request('notify') ?: 'none', Recurring_series::NOTIFY_MODES, 'notify');

            if ($from !== '' && !DateTimeImmutable::createFromFormat('!Y-m-d', $from)) {
                throw new InvalidArgumentException(ra_lang('error_invalid_date', ['value' => $from]));
            }

            $targets = $this->appointment_series_model->get_occurrences($id, $from !== '' ? $from . ' 00:00:00' : null);

            $this->recurring_series->delete($id, $targets, $notify);

            response('', 204);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * API form of a series row: the same naming style as /api/v1/appointments.
     */
    private function encode(array $series, bool $with_occurrences): array
    {
        $occurrences = $this->appointment_series_model->get_occurrences((int) $series['id']);

        $encoded = [
            'id' => (int) $series['id'],
            'rule' => is_array($series['rule']) ? $series['rule'] : json_decode((string) $series['rule'], true),
            'startDate' => $series['start_date'],
            'startTime' => $series['start_time'],
            'duration' => (int) $series['duration'],
            'providerId' => (int) $series['id_users_provider'],
            'customerId' => (int) $series['id_users_customer'],
            'serviceId' => (int) $series['id_services'],
            'occurrenceCount' => count($occurrences),
        ];

        if ($with_occurrences) {
            $encoded['occurrences'] = array_map(
                fn($row) => [
                    'id' => (int) $row['id'],
                    'sequence' => (int) $row['sequence'],
                    'start' => $row['start_datetime'],
                    'end' => $row['end_datetime'],
                    'providerId' => (int) $row['id_users_provider'],
                ],
                $occurrences,
            );
        }

        return $encoded;
    }

    private function find_or_404(?int $id): ?array
    {
        $this->appointment_series_model->cleanup_orphans();

        try {
            return $this->appointment_series_model->find((int) $id);
        } catch (InvalidArgumentException) {
            response('', 404);

            return null;
        }
    }

    private function require_installed(): void
    {
        if (!$this->appointment_series_model->is_installed()) {
            throw new RuntimeException(ra_lang('error_not_installed'));
        }
    }

    private function enum(mixed $value, array $allowed, string $name): string
    {
        $value = (string) $value;

        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(ra_lang('error_invalid_request', ['detail' => $name . ' = ' . $value]));
        }

        return $value;
    }

    /**
     * Error response with a translated message for recurrence rule errors, like the calendar endpoints.
     */
    private function fail(Throwable $e): void
    {
        if ($e instanceof Recurrence_rule_exception) {
            $e = new InvalidArgumentException(ra_lang($e->key, $e->params), 0, $e);
        }

        json_exception($e);
    }
}
