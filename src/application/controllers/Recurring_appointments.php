<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once APPPATH . 'libraries/Recurrence_rule.php';
require_once APPPATH . 'libraries/Series_conflict_checker.php';

/**
 * Recurring appointments controller.
 *
 * Every occurrence of a series is a regular appointment row, saved through Appointments_model, so the calendar,
 * notifications, webhooks and Google/CalDAV synchronization treat it like any other appointment. This controller only
 * adds the series bookkeeping on top.
 *
 * Endpoints (all POST + CSRF, backend session required):
 *
 * - recurring_appointments/preview  Expand a rule and report conflicts, writes nothing.
 * - recurring_appointments/create   Create a series.
 * - recurring_appointments/info     Series details of an appointment.
 * - recurring_appointments/update   Apply modal changes to this and following / all occurrences.
 * - recurring_appointments/delete   Delete one, this and following, or all occurrences.
 * - recurring_appointments/detach   Turn one occurrence into a regular single appointment.
 * - recurring_appointments/install  Create the tables (admins only).
 *
 * CLI: php index.php recurring_appointments install|uninstall|status
 *
 * @package Controllers
 */
class Recurring_appointments extends EA_Controller
{
    public const VERSION = '1.0.0';

    private const CONFLICT_MODES = ['abort', 'skip', 'force'];

    private const NOTIFY_MODES = ['none', 'first', 'all'];

    private const SCOPES = ['single', 'following', 'all'];

    /**
     * Same whitelist as Calendar::$allowed_customer_fields.
     */
    private array $allowed_customer_fields = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'address',
        'city',
        'state',
        'zip_code',
        'timezone',
        'language',
        'notes',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'custom_field_5',
    ];

    /**
     * Appointment fields that are copied to every occurrence.
     */
    private array $template_fields = [
        'location',
        'meeting_link',
        'notes',
        'color',
        'status',
        'id_users_provider',
        'id_users_customer',
        'id_services',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->helper('recurring_appointments');

        $this->load->model('appointment_series_model');
        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');

        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('webhooks_client');

        // Not every Easy!Appointments release ships these, load them only when present.
        ra_load_optional('model', 'blocked_periods_model');
        ra_load_optional('model', 'working_plan_exceptions_model');
        ra_load_optional('library', 'permissions');
        ra_load_optional('library', 'jitsi_client');
    }

    /**
     * Create the extension tables. CLI, or a logged in admin (POST).
     */
    public function install(): void
    {
        try {
            if (!is_cli()) {
                method('post');
            }

            $this->require_cli_or_admin();

            $this->appointment_series_model->install();

            $this->respond_cli_or_json(ra_lang('installed'));
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Drop the extension tables (CLI only). Existing occurrences stay as regular appointments.
     */
    public function uninstall(): void
    {
        if (!is_cli()) {
            abort(403, 'Forbidden');
        }

        $this->appointment_series_model->uninstall();

        echo 'Recurring appointments tables are removed. The appointments themselves were kept.' . PHP_EOL;
    }

    public function status(): void
    {
        $this->require_cli_or_admin();

        $installed = $this->appointment_series_model->is_installed();

        if (is_cli()) {
            echo 'Recurring appointments ' .
                self::VERSION .
                ': ' .
                ($installed ? 'installed' : 'tables missing, run "php index.php recurring_appointments install"') .
                PHP_EOL;

            return;
        }

        json_response(['success' => true, 'version' => self::VERSION, 'installed' => $installed]);
    }

    /**
     * Expand a rule and report conflicts, without writing anything.
     */
    public function preview(): void
    {
        try {
            method('post');

            $this->require_installed();

            check('appointment_data', 'array');
            check('rule', 'array');

            if (cannot('add', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            // Settings → Booking: "Staff can create repeating appointments".
            if (ra_setting('staff_enabled') === '0') {
                throw new RuntimeException(ra_lang('error_staff_disabled'));
            }

            $appointment = request('appointment_data');

            ra_authorize_event_write(null, $appointment['id_users_provider'] ?? null);

            [$rule, $occurrences] = $this->expand(request('rule'), $appointment);

            json_response([
                'success' => true,
                'rule' => $rule->to_array(),
                'occurrences' => $occurrences,
                'conflict_count' => count(array_filter($occurrences, fn($o) => !empty($o['conflicts']))),
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Create a series.
     *
     * Request: customer_data (array|null), appointment_data (array, the first occurrence as filled in the modal), rule
     * (array), conflict_mode (abort|skip|force), notify (none|first|all).
     */
    public function create(): void
    {
        try {
            method('post');

            $this->require_installed();

            check('customer_data', 'array|null');
            check('appointment_data', 'array');
            check('rule', 'array');
            check('conflict_mode', 'string|null');
            check('notify', 'string|null');

            if (cannot('add', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            // Settings → Booking: "Staff can create repeating appointments".
            if (ra_setting('staff_enabled') === '0') {
                throw new RuntimeException(ra_lang('error_staff_disabled'));
            }

            $conflict_mode = $this->enum(request('conflict_mode') ?: 'abort', self::CONFLICT_MODES, 'conflict_mode');
            $notify = $this->enum(request('notify') ?: 'first', self::NOTIFY_MODES, 'notify');

            $appointment = request('appointment_data');

            if (!empty($appointment['id'])) {
                throw new InvalidArgumentException(ra_lang('error_existing'));
            }

            ra_authorize_event_write(null, $appointment['id_users_provider'] ?? null);

            // Validate the rule before a new customer gets written.
            [$rule, $occurrences] = $this->expand(request('rule'), $appointment);

            if (empty($occurrences)) {
                throw new InvalidArgumentException(ra_lang('error_no_occurrences'));
            }

            $conflicting = array_values(array_filter($occurrences, fn($o) => !empty($o['conflicts'])));

            if ($conflicting && $conflict_mode === 'abort') {
                json_response([
                    'success' => false,
                    'conflict' => true,
                    'occurrences' => $occurrences,
                    'conflict_count' => count($conflicting),
                ]);

                return;
            }

            if ($conflict_mode === 'skip' && count($conflicting) === count($occurrences)) {
                throw new RuntimeException(ra_lang('error_all_conflict'));
            }

            $appointment['id_users_customer'] = $this->save_customer(request('customer_data'), $appointment);

            $template = $this->template($appointment);

            // Jitsi integration, same as the core calendar: one meeting link for the whole series.
            if (
                isset($this->jitsi_client) &&
                setting('jitsi_enabled') === '1' &&
                empty($template['meeting_link'])
            ) {
                $template['meeting_link'] = $this->jitsi_client->generate_link();
            }

            $start = new DateTimeImmutable($appointment['start_datetime']);
            $duration = $this->duration_minutes($appointment);

            $created_ids = [];
            $skipped = [];

            $this->db->trans_begin();

            try {
                $series_id = $this->appointment_series_model->insert_series([
                    'rule' => json_encode($rule->to_array()),
                    'start_date' => $start->format('Y-m-d'),
                    'start_time' => $start->format('H:i:s'),
                    'duration' => $duration,
                    'id_users_provider' => (int) $template['id_users_provider'],
                    'id_users_customer' => (int) $template['id_users_customer'],
                    'id_services' => (int) $template['id_services'],
                    'id_users_created_by' => (int) session('user_id') ?: null,
                ]);

                foreach ($occurrences as $occurrence) {
                    if (!empty($occurrence['conflicts']) && $conflict_mode === 'skip') {
                        $skipped[] = $occurrence;
                        continue;
                    }

                    $record = array_merge($template, [
                        'start_datetime' => $occurrence['start_datetime'],
                        'end_datetime' => $occurrence['end_datetime'],
                        'is_unavailability' => false,
                    ]);

                    $appointment_id = $this->appointments_model->save($record);

                    $this->appointment_series_model->link(
                        $series_id,
                        $appointment_id,
                        $occurrence['sequence'],
                        $occurrence['date'],
                    );

                    $created_ids[] = $appointment_id;
                }

                $this->db->trans_commit();
            } catch (Throwable $e) {
                $this->db->trans_rollback();

                throw $e;
            }

            // External side effects only after the commit, so that a rollback cannot leave orphan calendar events.
            $this->after_save($created_ids, $notify, false);

            json_response([
                'success' => true,
                'series_id' => $series_id,
                'created' => count($created_ids),
                'skipped' => $skipped,
                'forced' => $conflict_mode === 'force' ? count($conflicting) : 0,
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Series details of an appointment. Returns series = null for a regular appointment.
     */
    public function info(): void
    {
        try {
            method('post');

            check('appointment_id', 'numeric');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            if (!$this->appointment_series_model->is_installed()) {
                json_response(['success' => true, 'installed' => false, 'series' => null]);

                return;
            }

            $appointment_id = (int) request('appointment_id');

            ra_authorize_event_write($appointment_id, null);

            $this->appointment_series_model->cleanup_orphans();

            $series_id = $this->appointment_series_model->find_series_id($appointment_id);

            if (!$series_id) {
                json_response(['success' => true, 'installed' => true, 'series' => null]);

                return;
            }

            $series = $this->appointment_series_model->find($series_id);

            $occurrences = array_map(
                fn($row) => [
                    'id' => (int) $row['id'],
                    'sequence' => (int) $row['sequence'],
                    'start_datetime' => $row['start_datetime'],
                    'end_datetime' => $row['end_datetime'],
                ],
                $this->appointment_series_model->get_occurrences($series_id),
            );

            $position = array_search($appointment_id, array_column($occurrences, 'id'), true);

            json_response([
                'success' => true,
                'installed' => true,
                'series' => [
                    'id' => $series['id'],
                    'rule' => $series['rule'],
                    'start_date' => $series['start_date'],
                    'position' => $position === false ? null : $position + 1,
                    'total' => count($occurrences),
                    'occurrences' => $occurrences,
                ],
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Which of the appointments shown on the calendar belong to a series, so that the calendar can mark them.
     *
     * Request: appointment_ids (array). Response: series (object of appointment ID => series ID).
     */
    public function markers(): void
    {
        try {
            method('post');

            check('appointment_ids', 'array|null');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            if (!$this->appointment_series_model->is_installed()) {
                json_response(['success' => true, 'series' => (object) []]);

                return;
            }

            $ids = array_slice(array_values(array_unique(array_map('intval', request('appointment_ids') ?: []))), 0, 2000);

            // Same visibility as the calendar: providers see their own, secretaries those of their providers.
            $provider_ids = null;

            if (session('role_slug') === DB_SLUG_PROVIDER) {
                $provider_ids = [(int) session('user_id')];
            } elseif (session('role_slug') === DB_SLUG_SECRETARY) {
                $this->load->model('secretaries_model');
                $provider_ids = array_map(
                    'intval',
                    $this->secretaries_model->find((int) session('user_id'))['providers'] ?? [],
                );
            }

            json_response([
                'success' => true,
                'series' => (object) $this->appointment_series_model->find_series_ids($ids, $provider_ids),
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Apply the values of the appointment modal to several occurrences.
     *
     * The start time, duration and the other fields come from the modal. When the opened occurrence is moved to another
     * day, every occurrence in scope moves by the same number of days (see move_series()).
     *
     * Request: appointment_data (array with id), customer_data (array|null), scope (following|all),
     * conflict_mode (abort|skip|force), notify (none|first|all).
     */
    public function update(): void
    {
        try {
            method('post');

            $this->require_installed();

            check('appointment_data', 'array');
            check('customer_data', 'array|null');
            check('scope', 'string');
            check('conflict_mode', 'string|null');
            check('notify', 'string|null');

            if (cannot('edit', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            $scope = $this->enum(request('scope'), ['following', 'all'], 'scope');
            $conflict_mode = $this->enum(request('conflict_mode') ?: 'abort', self::CONFLICT_MODES, 'conflict_mode');
            $notify = $this->enum(request('notify') ?: 'none', self::NOTIFY_MODES, 'notify');

            $appointment = request('appointment_data');
            $appointment_id = (int) ($appointment['id'] ?? 0);

            ra_authorize_event_write($appointment_id, $appointment['id_users_provider'] ?? null);

            $series_id = $this->require_series_id($appointment_id);

            $current = $this->appointments_model->find($appointment_id);

            $targets = $this->scope_occurrences($series_id, $current, $scope);

            $new_start = new DateTimeImmutable($appointment['start_datetime']);
            $duration = $this->duration_minutes($appointment);

            // Moving the opened occurrence to another day moves every target by the same number of days.
            $day_offset = (int) (new DateTimeImmutable(substr($current['start_datetime'], 0, 10)))
                ->diff(new DateTimeImmutable($new_start->format('Y-m-d')))
                ->format('%r%a');

            // Every target needs to be writable by the current user, also when the provider changes.
            foreach ($targets as $target) {
                ra_authorize_event_write($target['id'], $appointment['id_users_provider'] ?? null);
            }

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

            $conflicting = array_values(array_filter($planned, fn($o) => !empty($o['conflicts'])));

            if ($conflicting && $conflict_mode === 'abort') {
                json_response([
                    'success' => false,
                    'conflict' => true,
                    'occurrences' => $planned,
                    'conflict_count' => count($conflicting),
                ]);

                return;
            }

            $appointment['id_users_customer'] = $this->save_customer(request('customer_data'), $appointment);

            $template = $this->template($appointment);

            $updated_ids = [];
            $skipped = [];

            // Moving to another provider removes the events from the old provider's synced calendar. This talks to
            // external calendars, so it runs outside the transaction and only for occurrences that will be saved.
            foreach ($planned as $occurrence) {
                if (
                    (empty($occurrence['conflicts']) || $conflict_mode !== 'skip') &&
                    method_exists($this->synchronization, 'remove_appointment_on_provider_change')
                ) {
                    $this->synchronization->remove_appointment_on_provider_change(
                        $occurrence['id'],
                        $template['id_users_provider'],
                    );
                }
            }

            $this->db->trans_begin();

            try {
                foreach ($planned as $occurrence) {
                    if (!empty($occurrence['conflicts']) && $conflict_mode === 'skip') {
                        $skipped[] = $occurrence;
                        continue;
                    }

                    $this->appointments_model->save(
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
                    'start_time' => $new_start->format('H:i:s'),
                    'duration' => $duration,
                    'id_users_provider' => (int) $template['id_users_provider'],
                    'id_users_customer' => (int) $template['id_users_customer'],
                    'id_services' => (int) $template['id_services'],
                ];

                if ($day_offset === 0) {
                    // The edited values become the template of the series.
                    $this->appointment_series_model->update_series($series_id, $series_values);
                } else {
                    $this->move_series($series_id, $planned, $updated_ids, $day_offset, $series_values);
                }

                $this->db->trans_commit();
            } catch (Throwable $e) {
                $this->db->trans_rollback();

                throw $e;
            }

            $this->after_save($updated_ids, $notify, true);

            json_response([
                'success' => true,
                'updated' => count($updated_ids),
                'skipped' => $skipped,
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Delete occurrences.
     *
     * Request: appointment_id, scope (single|following|all), notify (none|first|all), cancellation_reason.
     */
    public function delete(): void
    {
        try {
            method('post');

            $this->require_installed();

            check('appointment_id', 'numeric');
            check('scope', 'string');
            check('notify', 'string|null');
            check('cancellation_reason', 'string|null');

            if (cannot('delete', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            $appointment_id = (int) request('appointment_id');
            $scope = $this->enum(request('scope'), self::SCOPES, 'scope');
            $notify = $this->enum(request('notify') ?: 'none', self::NOTIFY_MODES, 'notify');
            $cancellation_reason = (string) request('cancellation_reason');

            ra_authorize_event_write($appointment_id, null);

            $series_id = $this->require_series_id($appointment_id);

            $current = $this->appointments_model->find($appointment_id);

            $targets = $this->scope_occurrences($series_id, $current, $scope);

            foreach ($targets as $target) {
                ra_authorize_event_write($target['id'], null);
            }

            $settings = $this->company_settings();
            $deleted = 0;

            foreach (array_values($targets) as $index => $target) {
                $appointment = $this->appointments_model->find((int) $target['id']);
                $provider = $this->providers_model->find($appointment['id_users_provider']);

                $this->appointments_model->delete($appointment['id']);
                $this->appointment_series_model->unlink((int) $appointment['id']);
                $deleted++;

                if ($notify === 'all' || ($notify === 'first' && $index === 0)) {
                    $this->notifications->notify_appointment_deleted(
                        $appointment,
                        $this->services_model->find($appointment['id_services']),
                        $provider,
                        $this->customers_model->find($appointment['id_users_customer']),
                        $settings,
                        $cancellation_reason,
                    );
                }

                $this->synchronization->sync_appointment_deleted($appointment, $provider);

                $this->webhooks_client->trigger(WEBHOOK_APPOINTMENT_DELETE, $appointment);
            }

            $this->appointment_series_model->delete_if_empty($series_id);

            json_response([
                'success' => true,
                'deleted' => $deleted,
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Turn one occurrence into a regular appointment, so that series edits no longer touch it.
     */
    public function detach(): void
    {
        try {
            method('post');

            $this->require_installed();

            check('appointment_id', 'numeric');

            if (cannot('edit', PRIV_APPOINTMENTS)) {
                throw new RuntimeException(ra_lang('error_permission'));
            }

            $appointment_id = (int) request('appointment_id');

            ra_authorize_event_write($appointment_id, null);

            $series_id = $this->require_series_id($appointment_id);

            $this->appointment_series_model->unlink($appointment_id);
            $this->appointment_series_model->delete_if_empty($series_id);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Expand the rule into occurrences (with conflicts) for the appointment that was filled in the modal.
     *
     * @return array{0: Recurrence_rule, 1: array}
     */
    private function expand(array $raw_rule, array $appointment): array
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

        $rule = Recurrence_rule::from_array($raw_rule, $start->format('Y-m-d'));

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
     * Add a "conflicts" list to every planned occurrence, using one query per data source for the whole range.
     *
     * @param int[] $exclude_ids Appointments that are being moved and must not conflict with themselves.
     */
    private function attach_conflicts(int $provider_id, array $occurrences, array $exclude_ids = []): array
    {
        if (empty($occurrences)) {
            return $occurrences;
        }

        $first_date = min(array_column($occurrences, 'date'));
        $last_date = substr(max(array_column($occurrences, 'end_datetime')), 0, 10);

        $provider = $this->providers_model->find($provider_id);

        $checker = new Series_conflict_checker(
            json_decode($provider['settings']['working_plan'] ?? '[]', true) ?: [],
            $this->working_plan_exceptions($provider),
            $this->appointment_series_model->get_provider_events($provider_id, $first_date, $last_date),
            isset($this->blocked_periods_model)
                ? $this->blocked_periods_model->get_for_period($first_date, $last_date)
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
     * Working plan exceptions keyed by date. Easy!Appointments 1.6 keeps them in a table, 1.5 in the provider settings.
     */
    private function working_plan_exceptions(array $provider): array
    {
        if (isset($this->working_plan_exceptions_model) && $this->db->table_exists('working_plan_exceptions')) {
            return $this->working_plan_exceptions_model->get_by_provider((int) $provider['id']);
        }

        return json_decode($provider['settings']['working_plan_exceptions'] ?? '[]', true) ?: [];
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
    ): void {
        $series = $this->appointment_series_model->find($series_id);
        $moved = array_values(array_filter($planned, fn($o) => in_array($o['id'], $moved_ids, true)));

        if (empty($moved)) {
            return;
        }

        $remaining = count($this->appointment_series_model->get_occurrences($series_id)) - count($moved);

        $rule = Recurrence_rule::shift($series['rule'], $day_offset);
        $series_values['start_date'] = $moved[0]['date'];

        if (isset($rule['count'])) {
            $rule['count'] = count($moved);
        }

        $series_values['rule'] = json_encode($rule);

        if ($remaining === 0) {
            $this->appointment_series_model->update_series($series_id, $series_values);
            $target_series_id = $series_id;
        } else {
            $target_series_id = $this->appointment_series_model->insert_series(
                $series_values + ['id_users_created_by' => (int) session('user_id') ?: null],
            );

            if (isset($series['rule']['count'])) {
                $old_rule = $series['rule'];
                $old_rule['count'] = $remaining;
                $this->appointment_series_model->update_series($series_id, ['rule' => json_encode($old_rule)]);
            }
        }

        foreach ($moved as $index => $occurrence) {
            $this->appointment_series_model->link($target_series_id, $occurrence['id'], $index + 1, $occurrence['date']);
        }
    }

    /**
     * Occurrences that a scoped edit or delete applies to.
     */
    private function scope_occurrences(int $series_id, array $current, string $scope): array
    {
        if ($scope === 'single') {
            return [['id' => (int) $current['id'], 'sequence' => 0, 'start_datetime' => $current['start_datetime']]];
        }

        return $this->appointment_series_model->get_occurrences(
            $series_id,
            $scope === 'following' ? $current['start_datetime'] : null,
        );
    }

    /**
     * Sync, notify and trigger webhooks for saved occurrences, like Calendar::save_appointment does for one.
     */
    private function after_save(array $appointment_ids, string $notify, bool $manage_mode): void
    {
        $settings = $this->company_settings();

        foreach (array_values($appointment_ids) as $index => $appointment_id) {
            $appointment = $this->appointments_model->find($appointment_id);
            $provider = $this->providers_model->find($appointment['id_users_provider']);
            $customer = $this->customers_model->find($appointment['id_users_customer']);
            $service = $this->services_model->find($appointment['id_services']);

            $this->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

            if ($notify === 'all' || ($notify === 'first' && $index === 0)) {
                $this->notifications->notify_appointment_saved(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $manage_mode,
                );
            }

            $this->webhooks_client->trigger(WEBHOOK_APPOINTMENT_SAVE, $appointment);
        }
    }

    /**
     * Save the customer of the modal with the same rules as Calendar::save_appointment.
     *
     * @return int Customer ID.
     */
    private function save_customer(?array $customer_data, array $appointment): int
    {
        if (!$customer_data) {
            $customer_id = (int) ($appointment['id_users_customer'] ?? 0);

            if (!$customer_id) {
                throw new InvalidArgumentException(ra_lang('error_no_customer'));
            }

            $this->require_customer_access($customer_id);

            return $customer_id;
        }

        $customer = $customer_data;

        $required_permissions = !empty($customer['id']) ? can('edit', PRIV_CUSTOMERS) : can('add', PRIV_CUSTOMERS);

        if (!$required_permissions) {
            throw new RuntimeException(ra_lang('error_permission'));
        }

        $this->customers_model->only($customer, $this->allowed_customer_fields);

        // Reuse existing customers by email (same behavior as the core calendar).
        if (empty($customer['id']) && $this->customers_model->exists($customer)) {
            $customer['id'] = $this->customers_model->find_record_id($customer);
        }

        if (!empty($customer['id'])) {
            $this->require_customer_access((int) $customer['id']);
        }

        return (int) $this->customers_model->save($customer);
    }

    private function require_customer_access(int $customer_id): void
    {
        if (
            isset($this->permissions) &&
            method_exists($this->permissions, 'has_customer_access') &&
            !$this->permissions->has_customer_access((int) session('user_id'), $customer_id)
        ) {
            abort(403, 'Forbidden');
        }
    }

    private function template(array $appointment): array
    {
        $template = [];

        foreach ($this->template_fields as $field) {
            $template[$field] = $appointment[$field] ?? null;
        }

        if (empty($template['id_users_customer'])) {
            throw new InvalidArgumentException(ra_lang('error_no_customer'));
        }

        return $template;
    }

    private function duration_minutes(array $appointment): int
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

    private function require_series_id(int $appointment_id): int
    {
        $series_id = $this->appointment_series_model->find_series_id($appointment_id);

        if (!$series_id) {
            throw new InvalidArgumentException(ra_lang('error_not_series'));
        }

        return $series_id;
    }

    private function require_installed(): void
    {
        if (!$this->appointment_series_model->is_installed()) {
            throw new RuntimeException(ra_lang('error_not_installed'));
        }

        // Appointments can be deleted outside of this extension (calendar, API, cleanup), drop their links first.
        $this->appointment_series_model->cleanup_orphans();
    }

    private function require_cli_or_admin(): void
    {
        if (!is_cli() && session('role_slug') !== DB_SLUG_ADMIN) {
            abort(403, 'Forbidden');
        }
    }

    private function respond_cli_or_json(string $message): void
    {
        if (is_cli()) {
            echo $message . PHP_EOL;

            return;
        }

        json_response(['success' => true, 'message' => $message]);
    }

    /**
     * Send an error response, translating recurrence rule errors into the user's language.
     */
    private function fail(Throwable $e): void
    {
        if ($e instanceof Recurrence_rule_exception) {
            $e = new InvalidArgumentException(ra_lang($e->key, $e->params), 0, $e);
        }

        json_exception($e);
    }

    private function enum(mixed $value, array $allowed, string $name): string
    {
        $value = (string) $value;

        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(ra_lang('error_invalid_request', ['detail' => $name . ' = ' . $value]));
        }

        return $value;
    }
}
