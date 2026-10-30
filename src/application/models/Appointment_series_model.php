<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Appointment series model.
 *
 * The extension keeps its data in two tables of its own, so that the core tables are never altered:
 *
 * - appointment_series:             one row per series (rule, template values, who created it).
 * - appointment_series_occurrences: links a regular appointment row to its series, by appointment ID and hash.
 *
 * There are deliberately no foreign keys into core tables: those would block core migrations that change or drop the
 * referenced columns (e.g. "php index.php console migrate fresh", or a future upgrade). Instead every read joins on the
 * appointment ID *and* its unique hash, so a link to a deleted (or re-used) appointment ID never matches, and
 * cleanup_orphans() removes such links on the next write.
 *
 * The schema is managed by install() / uninstall() instead of a numbered core migration, because the core migration
 * numbers belong to Easy!Appointments and a future release would collide with ours.
 *
 * @package Models
 */
class Appointment_series_model extends EA_Model
{
    protected array $casts = [
        'id' => 'integer',
        'duration' => 'integer',
        'id_users_provider' => 'integer',
        'id_users_customer' => 'integer',
        'id_services' => 'integer',
        'id_users_created_by' => 'integer',
    ];

    /**
     * Whether the extension tables exist.
     */
    public function is_installed(): bool
    {
        return $this->db->table_exists('appointment_series') &&
            $this->db->table_exists('appointment_series_occurrences');
    }

    /**
     * Create the extension tables (idempotent).
     */
    public function install(): void
    {
        $this->load->dbforge();

        if (!$this->db->table_exists('appointment_series')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'rule' => ['type' => 'TEXT', 'null' => false],
                'start_date' => ['type' => 'DATE', 'null' => false],
                'start_time' => ['type' => 'TIME', 'null' => false],
                'duration' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'id_users_provider' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'id_users_customer' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'id_services' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'id_users_created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'create_datetime' => ['type' => 'DATETIME', 'null' => true],
                'update_datetime' => ['type' => 'DATETIME', 'null' => true],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_users_provider');

            $this->dbforge->create_table('appointment_series', true, ['engine' => 'InnoDB']);
        }

        if (!$this->db->table_exists('appointment_series_occurrences')) {
            $this->dbforge->add_field([
                'id_appointments' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'appointment_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
                'id_appointment_series' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'sequence' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'occurrence_date' => ['type' => 'DATE', 'null' => false],
            ]);

            $this->dbforge->add_key('id_appointments', true);
            $this->dbforge->add_key('id_appointment_series');

            $this->dbforge->create_table('appointment_series_occurrences', true, ['engine' => 'InnoDB']);

            // Only a foreign key between the extension's own tables.
            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('appointment_series_occurrences') .
                    '` ADD CONSTRAINT `appointment_series_occurrences_series` FOREIGN KEY (`id_appointment_series`) REFERENCES `' .
                    $this->db->dbprefix('appointment_series') .
                    '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            );
        }
    }

    /**
     * Drop the extension tables and its settings. The appointments themselves stay, they become regular single
     * appointments.
     */
    public function uninstall(): void
    {
        $this->load->dbforge();

        $this->dbforge->drop_table('appointment_series_occurrences', true);
        $this->dbforge->drop_table('appointment_series', true);

        $this->db->like('name', 'recurring_appointments_', 'after')->delete('settings');
    }

    /**
     * Remove links to appointments that no longer exist (deleted through the core calendar, the API or a cleanup job)
     * and series without any occurrence left.
     */
    public function cleanup_orphans(): void
    {
        $occurrences = $this->db->dbprefix('appointment_series_occurrences');
        $appointments = $this->db->dbprefix('appointments');
        $series = $this->db->dbprefix('appointment_series');

        $this->db->query(
            "DELETE o FROM `{$occurrences}` o
                LEFT JOIN `{$appointments}` a ON a.id = o.id_appointments AND a.hash = o.appointment_hash
                WHERE a.id IS NULL",
        );

        $this->db->query(
            "DELETE s FROM `{$series}` s
                LEFT JOIN `{$occurrences}` o ON o.id_appointment_series = s.id
                WHERE o.id_appointment_series IS NULL",
        );
    }

    /**
     * Insert a series row.
     *
     * @return int Series ID.
     */
    public function insert_series(array $series): int
    {
        $series['create_datetime'] = date('Y-m-d H:i:s');
        $series['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->insert('appointment_series', $series)) {
            throw new RuntimeException('Could not insert the appointment series.');
        }

        return (int) $this->db->insert_id();
    }

    public function update_series(int $series_id, array $fields): void
    {
        $fields['update_datetime'] = date('Y-m-d H:i:s');

        $this->db->update('appointment_series', $fields, ['id' => $series_id]);
    }

    public function link(int $series_id, int $appointment_id, int $sequence, string $occurrence_date): void
    {
        $hash = $this->db->select('hash')->get_where('appointments', ['id' => $appointment_id])->row_array()['hash'] ?? '';

        if ($hash === '') {
            throw new RuntimeException('Could not link the appointment to its series: ' . $appointment_id);
        }

        // A stale link with the same appointment ID (from a deleted appointment) is replaced.
        $this->db->delete('appointment_series_occurrences', ['id_appointments' => $appointment_id]);

        if (
            !$this->db->insert('appointment_series_occurrences', [
                'id_appointments' => $appointment_id,
                'appointment_hash' => $hash,
                'id_appointment_series' => $series_id,
                'sequence' => $sequence,
                'occurrence_date' => $occurrence_date,
            ])
        ) {
            throw new RuntimeException('Could not link the appointment to its series: ' . $appointment_id);
        }
    }

    /**
     * Detach a single occurrence from its series; it stays on the calendar as a regular appointment.
     */
    public function unlink(int $appointment_id): void
    {
        $this->db->delete('appointment_series_occurrences', ['id_appointments' => $appointment_id]);
    }

    public function find(int $series_id): array
    {
        $series = $this->db->get_where('appointment_series', ['id' => $series_id])->row_array();

        if (!$series) {
            throw new InvalidArgumentException('The appointment series was not found: ' . $series_id);
        }

        $this->cast($series);

        $series['rule'] = json_decode($series['rule'], true) ?: [];

        return $series;
    }

    /**
     * Series ID of an appointment, or null when it is not part of a series.
     */
    public function find_series_id(int $appointment_id): ?int
    {
        $row = $this->linked_query()
            ->select('appointment_series_occurrences.id_appointment_series')
            ->where('appointment_series_occurrences.id_appointments', $appointment_id)
            ->get()
            ->row_array();

        return $row ? (int) $row['id_appointment_series'] : null;
    }

    /**
     * Which of the given appointments belong to a series.
     *
     * @param int[] $appointment_ids Appointment IDs.
     * @param int[]|null $provider_ids Only appointments of these providers, or null for all.
     *
     * @return array<int, int> Appointment ID => series ID.
     */
    public function find_series_ids(array $appointment_ids, ?array $provider_ids = null): array
    {
        if (empty($appointment_ids) || $provider_ids === []) {
            return [];
        }

        $query = $this->linked_query()
            ->select('appointment_series_occurrences.id_appointments, appointment_series_occurrences.id_appointment_series')
            ->where_in('appointment_series_occurrences.id_appointments', $appointment_ids);

        if ($provider_ids !== null) {
            $query->where_in('appointments.id_users_provider', $provider_ids);
        }

        $series_ids = [];

        foreach ($query->get()->result_array() as $row) {
            $series_ids[(int) $row['id_appointments']] = (int) $row['id_appointment_series'];
        }

        return $series_ids;
    }

    /**
     * Occurrences of a series joined with their appointment rows, ordered by start time.
     *
     * @param int $series_id Series ID.
     * @param string|null $from_datetime Only occurrences that start at or after this moment.
     */
    public function get_occurrences(int $series_id, ?string $from_datetime = null): array
    {
        $query = $this->linked_query()
            ->select('appointments.*, appointment_series_occurrences.sequence, appointment_series_occurrences.occurrence_date')
            ->where('appointment_series_occurrences.id_appointment_series', $series_id)
            ->order_by('appointments.start_datetime', 'ASC');

        if ($from_datetime !== null) {
            $query->where('appointments.start_datetime >=', $from_datetime);
        }

        return $query->get()->result_array();
    }

    /**
     * Remove the series row once it has no (existing) occurrences left.
     */
    public function delete_if_empty(int $series_id): void
    {
        $count = $this->linked_query()
            ->where('appointment_series_occurrences.id_appointment_series', $series_id)
            ->count_all_results();

        if ($count === 0) {
            $this->db->delete('appointment_series', ['id' => $series_id]);
        }
    }

    /**
     * Provider events (appointments and unavailabilities) that touch the given date range.
     */
    public function get_provider_events(int $provider_id, string $start_date, string $end_date): array
    {
        return $this->db
            ->select('id, start_datetime, end_datetime, is_unavailability')
            ->from('appointments')
            ->where('id_users_provider', $provider_id)
            ->where('start_datetime <', date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00')
            ->where('end_datetime >', $start_date . ' 00:00:00')
            ->get()
            ->result_array();
    }

    /**
     * Occurrence links joined with the appointments they still point to (same ID and same hash).
     */
    private function linked_query(): CI_DB_query_builder
    {
        return $this->db
            ->from('appointment_series_occurrences')
            ->join(
                'appointments',
                'appointments.id = appointment_series_occurrences.id_appointments AND ' .
                    'appointments.hash = appointment_series_occurrences.appointment_hash',
                'inner',
            );
    }
}
