<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once APPPATH . 'libraries/Recurrence_rule.php';
require_once APPPATH . 'libraries/Recurring_series.php';

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
 * - recurring_appointments/markers  Which appointments on the calendar belong to a series (for the ↻ marker).
 * - recurring_appointments/install  Create the tables (admins only).
 *
 * The series work itself is in the Recurring_series library, shared with the REST API (Appointment_series_api_v1).
 *
 * CLI: php index.php recurring_appointments install|uninstall|status
 *
 * @package Controllers
 */
class Recurring_appointments extends EA_Controller
{
    public const VERSION = '1.0.0';

    private const CONFLICT_MODES = Recurring_series::CONFLICT_MODES;

    private const NOTIFY_MODES = Recurring_series::NOTIFY_MODES;

    private const SCOPES = Recurring_series::SCOPES;

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

    public function __construct()
    {
        parent::__construct();

        $this->load->helper('recurring_appointments');

        $this->load->model('appointment_series_model');
        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');

        // The series work itself (expand, conflicts, create, delete, sync and e-mails), shared with the REST API.
        $this->load->library('recurring_series');

        // Not every Easy!Appointments release ships this, load it only when present.
        ra_load_optional('library', 'permissions');
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

            [$rule, $occurrences] = $this->recurring_series->expand(request('rule'), $appointment);

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
            [$rule, $occurrences] = $this->recurring_series->expand(request('rule'), $appointment);

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

            $result = $this->recurring_series->create(
                $appointment,
                $rule,
                $occurrences,
                $conflict_mode,
                $notify,
                (int) session('user_id') ?: null,
            );

            json_response([
                'success' => true,
                'series_id' => $result['series_id'],
                'created' => count($result['created_ids']),
                'skipped' => $result['skipped'],
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

            // Only occurrences the user may see (a series booked with "any provider" can span several providers).
            $visible = $this->visible_provider_ids();

            $occurrences = array_values(
                array_map(
                    fn($row) => [
                        'id' => (int) $row['id'],
                        'sequence' => (int) $row['sequence'],
                        'start_datetime' => $row['start_datetime'],
                        'end_datetime' => $row['end_datetime'],
                    ],
                    array_filter(
                        $this->appointment_series_model->get_occurrences($series_id),
                        fn($row) => $visible === null || in_array((int) $row['id_users_provider'], $visible, true),
                    ),
                ),
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

            json_response([
                'success' => true,
                'series' => (object) $this->appointment_series_model->find_series_ids($ids, $this->visible_provider_ids()),
            ]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Apply the values of the appointment modal to several occurrences.
     *
     * The start time, duration and the other fields come from the modal. When the opened occurrence is moved to another
     * day, every occurrence in scope moves by the same number of days (see Recurring_series::plan_update()).
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

            $plan = $this->recurring_series->plan_update($series_id, $current, $appointment, $scope);

            // Every target needs to be writable by the current user, also when the provider changes.
            foreach ($plan['planned'] as $target) {
                ra_authorize_event_write($target['id'], $appointment['id_users_provider'] ?? null);
            }

            if ($plan['conflict_count'] && $conflict_mode === 'abort') {
                json_response([
                    'success' => false,
                    'conflict' => true,
                    'occurrences' => $plan['planned'],
                    'conflict_count' => $plan['conflict_count'],
                ]);

                return;
            }

            $appointment['id_users_customer'] = $this->save_customer(request('customer_data'), $appointment);

            $result = $this->recurring_series->apply_update(
                $series_id,
                $plan,
                $appointment,
                $conflict_mode,
                $notify,
                (int) session('user_id') ?: null,
            );

            json_response([
                'success' => true,
                'updated' => count($result['updated_ids']),
                'skipped' => $result['skipped'],
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

            $targets = $this->recurring_series->scope_occurrences($series_id, $current, $scope);

            foreach ($targets as $target) {
                ra_authorize_event_write($target['id'], null);
            }

            $deleted = $this->recurring_series->delete($series_id, $targets, $notify, $cancellation_reason);

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

    /**
     * Providers whose appointments the current user may see, like the calendar: providers their own, secretaries those
     * of their providers, everyone else all (null).
     *
     * @return int[]|null
     */
    private function visible_provider_ids(): ?array
    {
        if (session('role_slug') === DB_SLUG_PROVIDER) {
            return [(int) session('user_id')];
        }

        if (session('role_slug') === DB_SLUG_SECRETARY) {
            $this->load->model('secretaries_model');

            return array_map('intval', $this->secretaries_model->find((int) session('user_id'))['providers'] ?? []);
        }

        return null;
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
