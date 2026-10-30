<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Recurrence rule.
 *
 * Pure value object (no CodeIgniter dependencies) that expands a recurrence rule into a list of dates. Kept free of
 * framework code so that it can be unit tested with plain PHP.
 *
 * Supported rules (array form, as stored in the "rule" column of the series table):
 *
 * - Weekly:          ['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [3]]          every 2nd Wednesday
 * - Monthly weekday: ['frequency' => 'monthly_weekday', 'interval' => 1, 'nth' => 1, 'weekday' => 1]
 *                                                                                           first Monday of the month
 *                    'nth' => -1 means the last weekday of the month.
 * - Monthly day:     ['frequency' => 'monthly_day', 'interval' => 1, 'day' => 15]           the 15th of every month
 *
 * Every rule needs exactly one end condition: 'until' => 'Y-m-d' (inclusive) or 'count' => int.
 *
 * Weekdays use ISO-8601 numbers: 1 = Monday ... 7 = Sunday.
 *
 * @package Libraries
 */
class Recurrence_rule
{
    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY_WEEKDAY = 'monthly_weekday';

    public const FREQUENCY_MONTHLY_DAY = 'monthly_day';

    /**
     * Hard limit of occurrences per series, so that a typo in the end date cannot create thousands of rows.
     */
    public const MAX_OCCURRENCES = 260;

    public const MAX_INTERVAL = 52;

    public readonly string $frequency;

    public readonly int $interval;

    /** @var int[] */
    public readonly array $weekdays;

    public readonly ?int $nth;

    public readonly ?int $weekday;

    public readonly ?int $day;

    public readonly ?string $until;

    public readonly ?int $count;

    private function __construct(array $rule)
    {
        $this->frequency = $rule['frequency'];
        $this->interval = $rule['interval'];
        $this->weekdays = $rule['weekdays'];
        $this->nth = $rule['nth'];
        $this->weekday = $rule['weekday'];
        $this->day = $rule['day'];
        $this->until = $rule['until'];
        $this->count = $rule['count'];
    }

    /**
     * Validate and normalize a rule array.
     *
     * @param array $rule Raw rule (e.g. from the request).
     * @param string $start_date First date of the series (Y-m-d), used to fill in defaults.
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function from_array(array $rule, string $start_date): self
    {
        $start = self::parse_date($start_date, 'start date');

        $frequency = (string) ($rule['frequency'] ?? '');

        if (
            !in_array(
                $frequency,
                [self::FREQUENCY_WEEKLY, self::FREQUENCY_MONTHLY_WEEKDAY, self::FREQUENCY_MONTHLY_DAY],
                true,
            )
        ) {
            throw new Recurrence_rule_exception('error_frequency', [], 'Unsupported recurrence frequency: ' . $frequency);
        }

        $interval = self::int_or_null($rule['interval'] ?? 1) ?? 1;

        if ($interval < 1 || $interval > self::MAX_INTERVAL) {
            throw new Recurrence_rule_exception(
                'error_interval',
                ['max' => self::MAX_INTERVAL],
                'The recurrence interval must be between 1 and ' . self::MAX_INTERVAL . '.',
            );
        }

        $normalized = [
            'frequency' => $frequency,
            'interval' => $interval,
            'weekdays' => [],
            'nth' => null,
            'weekday' => null,
            'day' => null,
            'until' => null,
            'count' => null,
        ];

        if ($frequency === self::FREQUENCY_WEEKLY) {
            $weekdays = array_map('intval', (array) ($rule['weekdays'] ?? []));
            $weekdays = array_values(array_unique($weekdays));
            sort($weekdays);

            if (empty($weekdays)) {
                $weekdays = [(int) $start->format('N')];
            }

            foreach ($weekdays as $weekday) {
                if ($weekday < 1 || $weekday > 7) {
                    throw new Recurrence_rule_exception(
                        'error_weekday',
                        [],
                        'Weekdays must be between 1 (Monday) and 7 (Sunday).',
                    );
                }
            }

            $normalized['weekdays'] = $weekdays;
        }

        if ($frequency === self::FREQUENCY_MONTHLY_WEEKDAY) {
            $nth = self::int_or_null($rule['nth'] ?? null) ?? (int) ceil((int) $start->format('j') / 7);
            $weekday = self::int_or_null($rule['weekday'] ?? null) ?? (int) $start->format('N');

            if (!in_array($nth, [1, 2, 3, 4, 5, -1], true)) {
                throw new Recurrence_rule_exception(
                    'error_nth',
                    [],
                    'The week of the month must be 1-5 or -1 (last).',
                );
            }

            if ($weekday < 1 || $weekday > 7) {
                throw new Recurrence_rule_exception(
                    'error_weekday',
                    [],
                    'Weekdays must be between 1 (Monday) and 7 (Sunday).',
                );
            }

            $normalized['nth'] = $nth;
            $normalized['weekday'] = $weekday;
        }

        if ($frequency === self::FREQUENCY_MONTHLY_DAY) {
            $day = self::int_or_null($rule['day'] ?? null) ?? (int) $start->format('j');

            if ($day < 1 || $day > 31) {
                throw new Recurrence_rule_exception('error_day', [], 'The day of the month must be between 1 and 31.');
            }

            $normalized['day'] = $day;
        }

        $until = isset($rule['until']) && $rule['until'] !== '' ? (string) $rule['until'] : null;
        $count = self::int_or_null($rule['count'] ?? null);

        if (($until === null) === ($count === null)) {
            throw new Recurrence_rule_exception(
                'error_end_condition',
                [],
                'Provide exactly one end condition: an end date or a number of occurrences.',
            );
        }

        if ($until !== null) {
            $until_date = self::parse_date($until, 'end date');

            if ($until_date < $start) {
                throw new Recurrence_rule_exception(
                    'error_until_before_start',
                    [],
                    'The end date cannot be before the start date.',
                );
            }

            $normalized['until'] = $until_date->format('Y-m-d');
        }

        if ($count !== null) {
            if ($count < 1 || $count > self::MAX_OCCURRENCES) {
                throw new Recurrence_rule_exception(
                    'error_count',
                    ['max' => self::MAX_OCCURRENCES],
                    'The number of occurrences must be between 1 and ' . self::MAX_OCCURRENCES . '.',
                );
            }

            $normalized['count'] = $count;
        }

        return new self($normalized);
    }

    /**
     * Array form of the rule, suitable for JSON storage.
     */
    public function to_array(): array
    {
        return array_filter(
            [
                'frequency' => $this->frequency,
                'interval' => $this->interval,
                'weekdays' => $this->frequency === self::FREQUENCY_WEEKLY ? $this->weekdays : null,
                'nth' => $this->nth,
                'weekday' => $this->weekday,
                'day' => $this->day,
                'until' => $this->until,
                'count' => $this->count,
            ],
            fn($value) => $value !== null,
        );
    }

    /**
     * Move a stored rule by a number of days, for when a series is moved to another day (e.g. Wednesday → Thursday).
     *
     * Weekdays turn along, "first Monday" becomes "first Tuesday", the day of the month moves (when it stays within
     * 1-31) and the end date moves as well.
     */
    public static function shift(array $rule, int $days): array
    {
        if ($days === 0) {
            return $rule;
        }

        $turn = fn(int $weekday) => ((($weekday - 1 + $days) % 7) + 7) % 7 + 1;

        if (!empty($rule['weekdays'])) {
            $rule['weekdays'] = array_map($turn, array_map('intval', $rule['weekdays']));
            sort($rule['weekdays']);
        }

        if (isset($rule['weekday'])) {
            $rule['weekday'] = $turn((int) $rule['weekday']);
        }

        if (isset($rule['day']) && $rule['day'] + $days >= 1 && $rule['day'] + $days <= 31) {
            $rule['day'] = (int) $rule['day'] + $days;
        }

        if (!empty($rule['until'])) {
            $rule['until'] = self::parse_date($rule['until'], 'end date')
                ->modify(sprintf('%+d days', $days))
                ->format('Y-m-d');
        }

        return $rule;
    }

    /**
     * Expand the rule into the dates of its occurrences.
     *
     * Dates before $start_date are never returned. A start date that does not match the pattern itself (e.g. a
     * Tuesday for a "every Wednesday" rule) is not included either; the series starts at the first matching date.
     *
     * @param string $start_date Y-m-d.
     *
     * @return string[] Y-m-d dates, ascending.
     *
     * @throws InvalidArgumentException When the rule produces more than MAX_OCCURRENCES dates.
     */
    public function dates(string $start_date): array
    {
        $start = self::parse_date($start_date, 'start date');
        $until = $this->until !== null ? self::parse_date($this->until, 'end date') : null;
        // With an until-date, expand one past the maximum so that an over-long rule is refused instead of truncated.
        $limit = $this->count ?? self::MAX_OCCURRENCES + 1;

        $dates = [];

        // Each step yields the candidate dates of one period (week or month), in ascending order.
        for ($period = 0; ; $period += $this->interval) {
            $candidates = $this->candidates_for_period($start, $period);

            // The first candidate of a period is the earliest one, so an until-date past it ends the expansion.
            $period_start = $this->period_start($start, $period);

            if ($until !== null && $period_start > $until) {
                break;
            }

            foreach ($candidates as $candidate) {
                if ($candidate < $start) {
                    continue;
                }

                if ($until !== null && $candidate > $until) {
                    break 2;
                }

                $dates[] = $candidate->format('Y-m-d');

                if (count($dates) >= $limit) {
                    break 2;
                }
            }

            // Safety net against rules that never yield a date (cannot happen with validated rules).
            if ($period > self::MAX_OCCURRENCES * self::MAX_INTERVAL) {
                break;
            }
        }

        if (count($dates) > self::MAX_OCCURRENCES) {
            throw new Recurrence_rule_exception(
                'error_too_many',
                ['max' => self::MAX_OCCURRENCES],
                'This rule creates more than ' . self::MAX_OCCURRENCES . ' occurrences, choose an earlier end date.',
            );
        }

        return $dates;
    }

    private function period_start(DateTimeImmutable $start, int $period): DateTimeImmutable
    {
        if ($this->frequency === self::FREQUENCY_WEEKLY) {
            return self::week_start($start)->modify('+' . $period . ' weeks');
        }

        return $start->modify('first day of this month')->modify('+' . $period . ' months');
    }

    /**
     * @return DateTimeImmutable[]
     */
    private function candidates_for_period(DateTimeImmutable $start, int $period): array
    {
        if ($this->frequency === self::FREQUENCY_WEEKLY) {
            $week_start = self::week_start($start)->modify('+' . $period . ' weeks');

            return array_map(fn(int $weekday) => $week_start->modify('+' . ($weekday - 1) . ' days'), $this->weekdays);
        }

        $month_start = $start->modify('first day of this month')->modify('+' . $period . ' months');

        if ($this->frequency === self::FREQUENCY_MONTHLY_DAY) {
            if ($this->day > (int) $month_start->format('t')) {
                return []; // e.g. the 31st in April: skip that month instead of rolling over.
            }

            return [$month_start->setDate((int) $month_start->format('Y'), (int) $month_start->format('n'), $this->day)];
        }

        // Monthly on the nth weekday.
        $date = self::nth_weekday_of_month($month_start, $this->nth, $this->weekday);

        return $date ? [$date] : [];
    }

    /**
     * Find the nth (1-5, or -1 for last) ISO weekday of the month that $month_start belongs to.
     */
    public static function nth_weekday_of_month(DateTimeImmutable $month_start, int $nth, int $weekday): ?DateTimeImmutable
    {
        $first = $month_start->modify('first day of this month');

        if ($nth === -1) {
            $last = $first->modify('last day of this month');
            $offset = ((int) $last->format('N') - $weekday + 7) % 7;

            return $last->modify('-' . $offset . ' days');
        }

        $offset = ($weekday - (int) $first->format('N') + 7) % 7;
        $date = $first->modify('+' . ($offset + ($nth - 1) * 7) . ' days');

        // A fifth weekday does not exist in every month; skip that month.
        if ($date->format('Y-m') !== $first->format('Y-m')) {
            return null;
        }

        return $date;
    }

    /**
     * Monday of the ISO week that contains $date.
     */
    private static function week_start(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->modify('-' . ((int) $date->format('N') - 1) . ' days');
    }

    private static function parse_date(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new Recurrence_rule_exception(
                'error_invalid_date',
                ['value' => $value],
                'Invalid ' . $label . ': ' . $value,
            );
        }

        return $date;
    }

    private static function int_or_null(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !is_numeric($value) || (int) $value != $value) {
            $shown = is_scalar($value) ? (string) $value : gettype($value);

            throw new Recurrence_rule_exception(
                'error_number',
                ['value' => $shown],
                'Expected a whole number, got: ' . $shown,
            );
        }

        return (int) $value;
    }
}

/**
 * Validation error of a recurrence rule.
 *
 * The message is English (for logs and the API); $key and $params let the caller show a translated message with
 * ra_lang($key, $params).
 */
class Recurrence_rule_exception extends InvalidArgumentException
{
    public function __construct(
        public readonly string $key,
        public readonly array $params,
        string $message,
    ) {
        parent::__construct($message);
    }
}
