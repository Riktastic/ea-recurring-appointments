<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Series conflict checker.
 *
 * Pure class (no CodeIgniter dependencies): it receives the provider's working plan, working plan exceptions, existing
 * events and blocked periods as plain arrays and reports why a candidate time slot cannot be booked cleanly.
 *
 * @package Libraries
 */
class Series_conflict_checker
{
    public const OUTSIDE_WORKING_PLAN = 'outside_working_plan';

    public const BREAK_TIME = 'break';

    public const APPOINTMENT = 'appointment';

    public const UNAVAILABILITY = 'unavailability';

    public const BLOCKED_PERIOD = 'blocked_period';

    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * English messages, kept in sync with the ra_conflict_* keys of the English language file (a test checks this).
     */
    public const MESSAGES = [
        'conflict_day_off' => 'The provider does not work on this day.',
        'conflict_outside_hours' => 'Outside working hours ({start} - {end}).',
        'conflict_break' => 'Overlaps a break ({start} - {end}).',
        'conflict_appointment' => 'Overlaps another appointment ({start} - {end}).',
        'conflict_unavailability' => 'Overlaps an unavailability ({start} - {end}).',
        'conflict_blocked_period' => 'Falls in the blocked period "{name}".',
        'conflict_same_series' => 'Overlaps occurrence #{sequence} of this series.',
    ];

    /**
     * @param array $working_plan Weekly plan as stored in the provider settings, keyed by lowercase weekday name, each
     * value null (day off) or ['start' => 'H:i', 'end' => 'H:i', 'breaks' => [['start' => .., 'end' => ..], ..]].
     * @param array $exceptions Working plan exceptions keyed by Y-m-d, same value shape as a working plan day (null is
     * a day off). This is the format of Working_plan_exceptions_model::get_by_provider().
     * @param array $events Existing appointment rows (id, start_datetime, end_datetime, is_unavailability) of the
     * provider.
     * @param array $blocked_periods Blocked period rows (name, start_datetime, end_datetime), they apply to everyone.
     */
    public function __construct(
        private readonly array $working_plan,
        private readonly array $exceptions,
        private readonly array $events,
        private readonly array $blocked_periods,
    ) {
    }

    /**
     * Check one candidate slot.
     *
     * @param string $start_datetime Y-m-d H:i:s.
     * @param string $end_datetime Y-m-d H:i:s.
     * @param int[] $exclude_ids Appointment IDs to ignore (the records that are being moved).
     *
     * @return array List of conflicts, each ['type' => .., 'message' => .., 'id' => ?int]. Empty when the slot is free.
     */
    public function check(string $start_datetime, string $end_datetime, array $exclude_ids = []): array
    {
        $conflicts = [];

        $start = new DateTimeImmutable($start_datetime);
        $end = new DateTimeImmutable($end_datetime);
        $date = $start->format('Y-m-d');

        $day_plan = $this->day_plan($date);

        if (!$day_plan) {
            $conflicts[] = self::conflict(self::OUTSIDE_WORKING_PLAN, 'conflict_day_off', []);
        } else {
            $day_start = new DateTimeImmutable($date . ' ' . $day_plan['start']);
            $day_end = new DateTimeImmutable($date . ' ' . $day_plan['end']);

            if ($start < $day_start || $end > $day_end) {
                $conflicts[] = self::conflict(self::OUTSIDE_WORKING_PLAN, 'conflict_outside_hours', [
                    'start' => $day_start->format('H:i'),
                    'end' => $day_end->format('H:i'),
                ]);
            }

            foreach ($day_plan['breaks'] ?? [] as $break) {
                $break_start = new DateTimeImmutable($date . ' ' . $break['start']);
                $break_end = new DateTimeImmutable($date . ' ' . $break['end']);

                if (self::overlaps($start, $end, $break_start, $break_end)) {
                    $conflicts[] = self::conflict(self::BREAK_TIME, 'conflict_break', [
                        'start' => $break_start->format('H:i'),
                        'end' => $break_end->format('H:i'),
                    ]);
                }
            }
        }

        $exclude_ids = array_map('intval', $exclude_ids);

        foreach ($this->events as $event) {
            if (in_array((int) $event['id'], $exclude_ids, true)) {
                continue;
            }

            $event_start = new DateTimeImmutable($event['start_datetime']);
            $event_end = new DateTimeImmutable($event['end_datetime']);

            if (!self::overlaps($start, $end, $event_start, $event_end)) {
                continue;
            }

            $is_unavailability = filter_var($event['is_unavailability'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $conflicts[] = self::conflict(
                $is_unavailability ? self::UNAVAILABILITY : self::APPOINTMENT,
                $is_unavailability ? 'conflict_unavailability' : 'conflict_appointment',
                ['start' => $event_start->format('H:i'), 'end' => $event_end->format('H:i')],
                (int) $event['id'],
            );
        }

        foreach ($this->blocked_periods as $blocked_period) {
            $blocked_start = new DateTimeImmutable($blocked_period['start_datetime']);
            $blocked_end = new DateTimeImmutable($blocked_period['end_datetime']);

            if (self::overlaps($start, $end, $blocked_start, $blocked_end)) {
                $conflicts[] = self::conflict(self::BLOCKED_PERIOD, 'conflict_blocked_period', [
                    'name' => (string) ($blocked_period['name'] ?? ''),
                ]);
            }
        }

        return $conflicts;
    }

    /**
     * Build a conflict entry.
     *
     * "key" and "params" let the UI translate the reason (see the ra_conflict_* language keys); "message" is the
     * English text for logs and API consumers.
     */
    public static function conflict(string $type, string $key, array $params, ?int $id = null): array
    {
        $message = self::MESSAGES[$key] ?? $key;

        foreach ($params as $name => $value) {
            $message = str_replace('{' . $name . '}', (string) $value, $message);
        }

        return [
            'type' => $type,
            'key' => $key,
            'params' => $params,
            'message' => $message,
            'id' => $id,
        ];
    }

    /**
     * Working plan of a specific date, taking exceptions into account. Null means the provider is off.
     */
    private function day_plan(string $date): ?array
    {
        if (array_key_exists($date, $this->exceptions)) {
            return $this->exceptions[$date] ?: null;
        }

        $weekday = self::WEEKDAYS[(int) (new DateTimeImmutable($date))->format('N') - 1];

        $plan = $this->working_plan[$weekday] ?? null;

        return !empty($plan['start']) && !empty($plan['end']) ? $plan : null;
    }

    /**
     * Half-open interval overlap: touching edges (10:00-11:00 and 11:00-12:00) do not overlap.
     */
    private static function overlaps(
        DateTimeImmutable $a_start,
        DateTimeImmutable $a_end,
        DateTimeImmutable $b_start,
        DateTimeImmutable $b_end,
    ): bool {
        return $a_start < $b_end && $a_end > $b_start;
    }
}
