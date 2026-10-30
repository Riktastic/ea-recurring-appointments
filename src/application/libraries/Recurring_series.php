<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once APPPATH . 'libraries/Recurrence_rule.php';
require_once APPPATH . 'libraries/Series_conflict_checker.php';

/**
 * Series operations shared by the calendar endpoints (Recurring_appointments) and the REST API
 * (Appointment_series_api_v1): expanding a rule with conflicts, creating a series, deleting occurrences, and the
 * calendar sync, e-mails and webhooks that go with them.
 *
 * Who may do what is checked by the callers (session permissions or the API token); this library only does the work.
 *
 * @package Libraries
 */
class Recurring_series
{
    public const CONFLICT_MODES = ['abort', 'skip', 'force'];

    public const NOTIFY_MODES = ['none', 'first', 'all'];

    public const SCOPES = ['single', 'following', 'all'];

    /**
     * Appointment fields that are copied to every occurrence.
     */
    public const TEMPLATE_FIELDS = [
        'location',
        'meeting_link',
        'notes',
        'color',
        'status',
        'id_users_provider',
        'id_users_customer',
        'id_services',
    ];

    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->helper('recurring_appointments');

        $this->CI->load->model('appointment_series_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('customers_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('services_model');

        $this->CI->load->library('notifications');
        $this->CI->load->library('synchronization');
        $this->CI->load->library('webhooks_client');

        // Not every Easy!Appointments release ships these, load them only when present.
        ra_load_optional('model', 'blocked_periods_model');
        ra_load_optional('model', 'working_plan_exceptions_model');
        ra_load_optional('library', 'jitsi_client');
    }

    /**
     * Expand a rule into occurrences (with conflicts) for the first appointment of a series.
     *
     * @param array $raw_rule Rule as sent by the client, see Recurrence_rule.
     * @param array $appointment Needs start_datetime, end_datetime, id_users_provider and id_services.
     *
     * @return array{0: Recurrence_rule, 1: array}
     */
    public function expand(array $raw_rule, array $appointment): array
    {
        foreach (['start_datetime', 'end_datetime', 'id_users_provider', 'id_services'] as $field) {
            if (empty($appointment[$field])) {
                throw new InvalidArgumentException(ra_lang('error_invalid_request', ['detail' => $field]));
            }
        }

        if (!validate_datetime($appointment['start_datetime']) || !validate_datetime($appointment['end_datetime'])) {
            throw new InvalidArgumentException(
                ra_lang('error_invalid_date', ['value' => $appointment['start_datetime']]),
            );
        }

        $start = new DateTimeImmutable($appointment['start_datetime']);
        $duration = $this->duration_minutes($appointment);

        $rule = Recurrence_rule::from_array($raw_rule, $start->format('Y-m-d'), (int) ra_setting('max_occurrences'));

        $occurrences = [];

        foreach ($rule->dates($start->format('Y-m-d')) as $index => $date) {
            $occurrence_start = new DateTimeImmutable($date . ' ' . $start->format('H:i:s'));

            $occurrences[] = [
                'sequence' => $index + 1,
                'date' => $date,
                'start_datetime' => $occurrence_start->format('Y-m-d H:i:s'),
                'end_datetime' => $occurrence_start->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
            ];
        }

        return [$rule, $this->attach_conflicts((int) $appointment['id_users_provider'], $occurrences)];
    }

    /**
     * Create a series from expanded occurrences.
     *
     * @param array $appointment The first occurrence, with id_users_customer set (an existing customer).
     * @param Recurrence_rule $rule Expanded rule.
     * @param array $occurrences From expand().
     * @param string $conflict_mode "skip" leaves out conflicting dates, "force" books them too. ("abort" is handled by
     *                              the caller before calling this.)
     * @param string $notify none|first|all.
     * @param int|null $created_by User ID, null when unknown (API, customer).
     *
     * @return array{series_id: int, created_ids: int[], skipped: array}
     */
    public function create(
        array $appointment,
        Recurrence_rule $rule,
        array $occurrences,
        string $conflict_mode,
        string $notify,
        ?int $created_by,
    ): array {
        $template = $this->template($appointment);

        // Jitsi integration, same as the core calendar: one meeting link for the whole series.
        if (isset($this->CI->jitsi_client) && setting('jitsi_enabled') === '1' && empty($template['meeting_link'])) {
            $template['meeting_link'] = $this->CI->jitsi_client->generate_link();
        }

        $start = new DateTimeImmutable($appointment['start_datetime']);
        $duration = $this->duration_minutes($appointment);

        $created_ids = [];
        $skipped = [];

        $this->CI->db->trans_begin();

        try {
            $series_id = $this->CI->appointment_series_model->insert_series([
                'rule' => json_encode($rule->to_array()),
                'start_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'duration' => $duration,
                'id_users_provider' => (int) $template['id_users_provider'],
                'id_users_customer' => (int) $template['id_users_customer'],
                'id_services' => (int) $template['id_services'],
                'id_users_created_by' => $created_by,
            ]);

            foreach ($occurrences as $occurrence) {
                if (!empty($occurrence['conflicts']) && $conflict_mode === 'skip') {
                    $skipped[] = $occurrence;
                    continue;
                }

                $appointment_id = $this->CI->appointments_model->save(
                    array_merge($template, [
                        'start_datetime' => $occurrence['start_datetime'],
                        'end_datetime' => $occurrence['end_datetime'],
                        'is_unavailability' => false,
                    ]),
                );

                $this->CI->appointment_series_model->link(
                    $series_id,
                    $appointment_id,
                    $occurrence['sequence'],
                    $occurrence['date'],
                );

                $created_ids[] = $appointment_id;
            }

            $this->CI->db->trans_commit();
        } catch (Throwable $e) {
            $this->CI->db->trans_rollback();

            throw $e;
        }

        // External side effects only after the commit, so that a rollback cannot leave orphan calendar events.
        $this->after_save($created_ids, $notify, false);

        return ['series_id' => $series_id, 'created_ids' => $created_ids, 'skipped' => $skipped];
    }

    /**
     * Plan a change of several occurrences, without writing anything.
     *
     * The start time, duration and the other fields come from $appointment (the edited values of the occurrence
     * $current). When $current is moved to another day, every occurrence in scope moves by the same number of days.
     *
     * @param array $current The occurrence as it is now (from the database).
     * @param array $appointment Its new values: start_datetime, end_datetime, id_users_provider, ... .
     * @param string $scope following|all.
     *
     * @return array{planned: array, day_offset: int, new_start: DateTimeImmutable, duration: int, conflict_count: int}
     *               planned: one entry per occurrence in scope (id, sequence, date, start/end, conflicts).
     */
    public function plan_update(int $series_id, array $current, array $appointment, string $scope): array
    {
        $targets = $this->scope_occurrences($series_id, $current, $scope);

        $new_start = new DateTimeImmutable($appointment['start_datetime']);
        $duration = $this->duration_minutes($appointment);

        $day_offset = (int) (new DateTimeImmutable(substr($current['start_datetime'], 0, 10)))
            ->diff(new DateTimeImmutable($new_start->format('Y-m-d')))
            ->format('%r%a');

        $planned = [];

        foreach ($targets as $target) {
            $date = (new DateTimeImmutable(substr($target['start_datetime'], 0, 10)))
                ->modify(sprintf('%+d days', $day_offset))
                ->format('Y-m-d');

            $start = new DateTimeImmutable($date . ' ' . $new_start->format('H:i:s'));

            $planned[] = [
                'id' => (int) $target['id'],
                'sequence' => (int) $target['sequence'],
                'date' => $date,
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $start->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
            ];
        }

        $planned = $this->attach_conflicts(
            (int) $appointment['id_users_provider'],
            $planned,
            array_column($planned, 'id'),
        );

        return [
            'planned' => $planned,
            'day_offset' => $day_offset,
            'new_start' => $new_start,
            'duration' => $duration,
            'conflict_count' => count(array_filter($planned, fn($o) => !empty($o['conflicts']))),
        ];
    }

    /**
     * Save a change planned by plan_update().
     *
     * @param array $plan From plan_update().
     * @param array $appointment The new values, with id_users_customer set (an existing customer).
     * @param string $conflict_mode "skip" leaves conflicting occurrences as they are, "force" saves them too.
     * @param string $notify none|first|all.
     * @param int|null $user_id Who made the change (for a series that is split off), null when unknown.
     *
     * @return array{updated_ids: int[], skipped: array}
     */
    public function apply_update(
        int $series_id,
        array $plan,
        array $appointment,
        string $conflict_mode,
        string $notify,
        ?int $user_id,
    ): array {
        $template = $this->template($appointment);
        $planned = $plan['planned'];

        $updated_ids = [];
        $skipped = [];

        // Moving to another provider removes the events from the old provider's synced calendar. This talks to
        // external calendars, so it runs outside the transaction and only for occurrences that will be saved.
        foreach ($planned as $occurrence) {
            if (
                (empty($occurrence['conflicts']) || $conflict_mode !== 'skip') &&
                method_exists($this->CI->synchronization, 'remove_appointment_on_provider_change')
            ) {
                $this->CI->synchronization->remove_appointment_on_provider_change(
                    $occurrence['id'],
                    $template['id_users_provider'],
                );
            }
        }

        $this->CI->db->trans_begin();

        try {
            foreach ($planned as $occurrence) {
                if (!empty($occurrence['conflicts']) && $conflict_mode === 'skip') {
                    $skipped[] = $occurrence;
                    continue;
                }

                $this->CI->appointments_model->save(
                    array_merge($template, [
                        'id' => $occurrence['id'],
                        'start_datetime' => $occurrence['start_datetime'],
                        'end_datetime' => $occurrence['end_datetime'],
                        'is_unavailability' => false,
                    ]),
                );

                $updated_ids[] = $occurrence['id'];
            }

            $series_values = [
                'start_time' => $plan['new_start']->format('H:i:s'),
                'duration' => $plan['duration'],
                'id_users_provider' => (int) $template['id_users_provider'],
                'id_users_customer' => (int) $template['id_users_customer'],
                'id_services' => (int) $template['id_services'],
            ];

            if ($plan['day_offset'] === 0) {
                // The edited values become the template of the series.
                $this->CI->appointment_series_model->update_series($series_id, $series_values);
            } else {
                $this->move_series($series_id, $planned, $updated_ids, $plan['day_offset'], $series_values, $user_id);
            }

            $this->CI->db->trans_commit();
        } catch (Throwable $e) {
            $this->CI->db->trans_rollback();

            throw $e;
        }

        $this->after_save($updated_ids, $notify, true);

        return ['updated_ids' => $updated_ids, 'skipped' => $skipped];
    }

    /**
     * Store a move to another day in the series.
     *
     * When every occurrence moved, the rule of the series turns along (e.g. every Wednesday → every Thursday). When only
     * "this and following" moved, those occurrences become a new series with the moved rule, and the earlier ones keep
     * the old rule. Occurrences that were skipped because of a conflict stay where they were, in the old series.
     */
    private function move_series(
        int $series_id,
        array $planned,
        array $moved_ids,
        int $day_offset,
        array $series_values,
        ?int $user_id,
    ): void {
        $series = $this->CI->appointment_series_model->find($series_id);
        $moved = array_values(array_filter($planned, fn($o) => in_array($o['id'], $moved_ids, true)));

        if (empty($moved)) {
            return;
        }

        $remaining = count($this->CI->appointment_series_model->get_occurrences($series_id)) - count($moved);

        $rule = Recurrence_rule::shift($series['rule'], $day_offset);
        $series_values['start_date'] = $moved[0]['date'];

        if (isset($rule['count'])) {
            $rule['count'] = count($moved);
        }

        $series_values['rule'] = json_encode($rule);

        if ($remaining === 0) {
            $this->CI->appointment_series_model->update_series($series_id, $series_values);
            $target_series_id = $series_id;
        } else {
            $target_series_id = $this->CI->appointment_series_model->insert_series(
                $series_values + ['id_users_created_by' => $user_id],
            );

            if (isset($series['rule']['count'])) {
                $old_rule = $series['rule'];
                $old_rule['count'] = $remaining;
                $this->CI->appointment_series_model->update_series($series_id, ['rule' => json_encode($old_rule)]);
            }
        }

        foreach ($moved as $index => $occurrence) {
            $this->CI->appointment_series_model->link($target_series_id, $occurrence['id'], $index + 1, $occurrence['date']);
        }
    }

    /**
     * Delete occurrences (from scope_occurrences()) with their e-mails, calendar sync and webhooks, and remove the
     * series once it is empty.
     *
     * @return int Number of deleted appointments.
     */
    public function delete(int $series_id, array $targets, string $notify, string $cancellation_reason = ''): int
    {
        $settings = $this->company_settings();
        $deleted = 0;

        foreach (array_values($targets) as $index => $target) {
            $appointment = $this->CI->appointments_model->find((int) $target['id']);
            $provider = $this->CI->providers_model->find($appointment['id_users_provider']);

            $this->CI->appointments_model->delete($appointment['id']);
            $this->CI->appointment_series_model->unlink((int) $appointment['id']);
            $deleted++;

            if ($notify === 'all' || ($notify === 'first' && $index === 0)) {
                $this->CI->notifications->notify_appointment_deleted(
                    $appointment,
                    $this->CI->services_model->find($appointment['id_services']),
                    $provider,
                    $this->CI->customers_model->find($appointment['id_users_customer']),
                    $settings,
                    $cancellation_reason,
                );
            }

            $this->CI->synchronization->sync_appointment_deleted($appointment, $provider);

            $this->CI->webhooks_client->trigger(WEBHOOK_APPOINTMENT_DELETE, $appointment);
        }

        $this->CI->appointment_series_model->delete_if_empty($series_id);

        return $deleted;
    }

    /**
     * Add a "conflicts" list to every planned occurrence, using one query per data source for the whole range.
     *
     * @param int[] $exclude_ids Appointments that are being moved and must not conflict with themselves.
     */
    public function attach_conflicts(int $provider_id, array $occurrences, array $exclude_ids = []): array
    {
        if (empty($occurrences)) {
            return $occurrences;
        }

        $first_date = min(array_column($occurrences, 'date'));
        $last_date = substr(max(array_column($occurrences, 'end_datetime')), 0, 10);

        $provider = $this->CI->providers_model->find($provider_id);

        $checker = new Series_conflict_checker(
            json_decode($provider['settings']['working_plan'] ?? '[]', true) ?: [],
            $this->working_plan_exceptions($provider),
            $this->CI->appointment_series_model->get_provider_events($provider_id, $first_date, $last_date),
            isset($this->CI->blocked_periods_model)
                ? $this->CI->blocked_periods_model->get_for_period($first_date, $last_date)
                : [],
        );

        // Occurrences of the same request must not overlap each other either (e.g. overnight appointments).
        $planned = [];

        foreach ($occurrences as &$occurrence) {
            $occurrence['conflicts'] = $checker->check(
                $occurrence['start_datetime'],
                $occurrence['end_datetime'],
                $exclude_ids,
            );

            foreach ($planned as $other) {
                if (
                    $occurrence['start_datetime'] < $other['end_datetime'] &&
                    $occurrence['end_datetime'] > $other['start_datetime']
                ) {
                    $occurrence['conflicts'][] = Series_conflict_checker::conflict(
                        Series_conflict_checker::APPOINTMENT,
                        'conflict_same_series',
                        ['sequence' => $other['sequence']],
                    );
                }
            }

            $planned[] = $occurrence;
        }

        unset($occurrence);

        return $occurrences;
    }

    /**
     * Occurrences that a scoped edit or delete applies to.
     *
     * @param array $current The appointment the user opened.
     * @param string $scope single|following|all.
     */
    public function scope_occurrences(int $series_id, array $current, string $scope): array
    {
        if ($scope === 'single') {
            return [['id' => (int) $current['id'], 'sequence' => 0, 'start_datetime' => $current['start_datetime']]];
        }

        return $this->CI->appointment_series_model->get_occurrences(
            $series_id,
            $scope === 'following' ? $current['start_datetime'] : null,
        );
    }

    /**
     * Sync, notify and trigger webhooks for saved occurrences, like Calendar::save_appointment does for one.
     */
    public function after_save(array $appointment_ids, string $notify, bool $manage_mode): void
    {
        $settings = $this->company_settings();

        foreach (array_values($appointment_ids) as $index => $appointment_id) {
            $appointment = $this->CI->appointments_model->find($appointment_id);
            $provider = $this->CI->providers_model->find($appointment['id_users_provider']);
            $customer = $this->CI->customers_model->find($appointment['id_users_customer']);
            $service = $this->CI->services_model->find($appointment['id_services']);

            $this->CI->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

            if ($notify === 'all' || ($notify === 'first' && $index === 0)) {
                $this->CI->notifications->notify_appointment_saved(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $manage_mode,
                );
            }

            $this->CI->webhooks_client->trigger(WEBHOOK_APPOINTMENT_SAVE, $appointment);
        }
    }

    /**
     * The fields of an appointment that every occurrence gets.
     */
    public function template(array $appointment): array
    {
        $template = [];

        foreach (self::TEMPLATE_FIELDS as $field) {
            $template[$field] = $appointment[$field] ?? null;
        }

        if (empty($template['id_users_customer'])) {
            throw new InvalidArgumentException(ra_lang('error_no_customer'));
        }

        return $template;
    }

    public function duration_minutes(array $appointment): int
    {
        $minimum = defined('EVENT_MINIMUM_DURATION') ? EVENT_MINIMUM_DURATION : 5;

        $duration = (int) round(
            (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60,
        );

        if ($duration < $minimum) {
            throw new InvalidArgumentException(ra_lang('error_duration', ['min' => $minimum]));
        }

        return $duration;
    }

    /**
     * Working plan exceptions keyed by date. Easy!Appointments 1.6 keeps them in a table, 1.5 in the provider settings.
     */
    private function working_plan_exceptions(array $provider): array
    {
        if (isset($this->CI->working_plan_exceptions_model) && $this->CI->db->table_exists('working_plan_exceptions')) {
            return $this->CI->working_plan_exceptions_model->get_by_provider((int) $provider['id']);
        }

        return json_decode($provider['settings']['working_plan_exceptions'] ?? '[]', true) ?: [];
    }

    private function company_settings(): array
    {
        $company_color = setting('company_color');
        $default_color = defined('DEFAULT_COMPANY_COLOR') ? DEFAULT_COMPANY_COLOR : null;

        return [
            'company_name' => setting('company_name'),
            'company_link' => setting('company_link'),
            'company_email' => setting('company_email'),
            'company_color' => !empty($company_color) && $company_color != $default_color ? $company_color : null,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ];
    }
}
