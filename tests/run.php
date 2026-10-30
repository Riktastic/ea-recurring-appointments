<?php

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Dependency-free tests (no PHPUnit, no database): recurrence engine, conflict checker, translations, installer.
 *
 * Usage: php tests/run.php
 * ---------------------------------------------------------------------------- */

const BASEPATH = __DIR__;
const APPPATH = __DIR__ . '/../src/application/';

require APPPATH . 'libraries/Recurrence_rule.php';
require APPPATH . 'libraries/Series_conflict_checker.php';

// Minimal stand-in for the Easy!Appointments config() helper, used by the translation helper.
$test_language = 'english';

function config(string $key): mixed
{
    global $test_language;

    return $key === 'language' ? $test_language : null;
}

require APPPATH . 'helpers/recurring_appointments_helper.php';

$failures = 0;
$passes = 0;

function test(string $name, callable $body): void
{
    global $failures, $passes;

    try {
        $body();
        $passes++;
        echo "  ok    {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n        " . $e->getMessage() . "\n";
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message ? $message . ': ' : '') .
                'expected ' .
                json_encode($expected) .
                ', got ' .
                json_encode($actual),
        );
    }
}

function assert_throws(callable $body, string $contains = ''): void
{
    try {
        $body();
    } catch (InvalidArgumentException $e) {
        if ($contains !== '' && !str_contains($e->getMessage(), $contains)) {
            throw new RuntimeException('Wrong exception message: ' . $e->getMessage());
        }

        return;
    }

    throw new RuntimeException('Expected an InvalidArgumentException');
}

function dates(array $rule, string $start): array
{
    return Recurrence_rule::from_array($rule, $start)->dates($start);
}

echo "Recurrence_rule\n";

test('every Wednesday, 4 times', function () {
    // 2026-11-04 is a Wednesday.
    assert_same(
        ['2026-11-04', '2026-11-11', '2026-11-18', '2026-11-25'],
        dates(['frequency' => 'weekly', 'weekdays' => [3], 'count' => 4], '2026-11-04'),
    );
});

test('weekly defaults to the weekday of the start date', function () {
    assert_same(
        ['2026-11-04', '2026-11-11'],
        dates(['frequency' => 'weekly', 'count' => 2], '2026-11-04'),
    );
});

test('every two weeks until an end date (inclusive)', function () {
    assert_same(
        ['2026-11-04', '2026-11-18', '2026-12-02', '2026-12-16'],
        dates(['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [3], 'until' => '2026-12-16'], '2026-11-04'),
    );
});

test('start date that does not match the pattern starts at the next match', function () {
    // 2026-11-03 is a Tuesday; the first Wednesday is the 4th.
    assert_same(
        ['2026-11-04', '2026-11-11'],
        dates(['frequency' => 'weekly', 'weekdays' => [3], 'count' => 2], '2026-11-03'),
    );
});

test('several weekdays per week, every 2 weeks', function () {
    // Monday 2026-11-02 start: Mon+Thu of week 0, week 2, ...
    assert_same(
        ['2026-11-02', '2026-11-05', '2026-11-16', '2026-11-19', '2026-11-30'],
        dates(['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [1, 4], 'count' => 5], '2026-11-02'),
    );
});

test('interval phase is anchored on the start week, not on the first match', function () {
    // Start on Friday 2026-11-06, every 2 weeks on Monday: the Monday of the start week (11-02) is before the start,
    // so the first match is two weeks later (11-16), not the following Monday (11-09).
    assert_same(
        ['2026-11-16', '2026-11-30'],
        dates(['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [1], 'count' => 2], '2026-11-06'),
    );
});

test('first Monday of every month', function () {
    assert_same(
        ['2026-11-02', '2026-12-07', '2027-01-04', '2027-02-01'],
        dates(['frequency' => 'monthly_weekday', 'nth' => 1, 'weekday' => 1, 'count' => 4], '2026-11-02'),
    );
});

test('first Monday: start mid-month skips to next month', function () {
    assert_same(
        ['2026-12-07', '2027-01-04'],
        dates(['frequency' => 'monthly_weekday', 'nth' => 1, 'weekday' => 1, 'count' => 2], '2026-11-10'),
    );
});

test('last Friday of every 2nd month', function () {
    assert_same(
        ['2026-11-27', '2027-01-29', '2027-03-26'],
        dates(['frequency' => 'monthly_weekday', 'interval' => 2, 'nth' => -1, 'weekday' => 5, 'count' => 3], '2026-11-01'),
    );
});

test('fifth weekday skips months without one', function () {
    // Fifth Sunday: Nov 2026 has 5 Sundays (1, 8, 15, 22, 29), Dec 2026 does not, Jan 2027 has (31).
    assert_same(
        ['2026-11-29', '2027-01-31'],
        dates(['frequency' => 'monthly_weekday', 'nth' => 5, 'weekday' => 7, 'count' => 2], '2026-11-01'),
    );
});

test('monthly on the 31st skips short months', function () {
    assert_same(
        ['2027-01-31', '2027-03-31', '2027-05-31'],
        dates(['frequency' => 'monthly_day', 'day' => 31, 'count' => 3], '2027-01-31'),
    );
});

test('monthly day across a leap year February', function () {
    assert_same(
        ['2028-01-29', '2028-02-29', '2028-03-29'],
        dates(['frequency' => 'monthly_day', 'day' => 29, 'count' => 3], '2028-01-29'),
    );
});

test('until date before the first match yields nothing', function () {
    assert_same([], dates(['frequency' => 'weekly', 'weekdays' => [3], 'until' => '2026-11-03'], '2026-11-02'));
});

test('to_array round trip', function () {
    $rule = Recurrence_rule::from_array(
        ['frequency' => 'weekly', 'interval' => '2', 'weekdays' => ['3', '1', '3'], 'until' => '2027-01-01'],
        '2026-11-04',
    );

    assert_same(
        ['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [1, 3], 'until' => '2027-01-01'],
        $rule->to_array(),
    );
});

test('requires exactly one end condition', function () {
    assert_throws(fn() => dates(['frequency' => 'weekly'], '2026-11-04'), 'end condition');
    assert_throws(
        fn() => dates(['frequency' => 'weekly', 'count' => 2, 'until' => '2026-12-01'], '2026-11-04'),
        'end condition',
    );
});

test('rejects invalid input', function () {
    assert_throws(fn() => dates(['frequency' => 'daily', 'count' => 2], '2026-11-04'), 'frequency');
    assert_throws(fn() => dates(['frequency' => 'weekly', 'interval' => 0, 'count' => 2], '2026-11-04'), 'interval');
    assert_throws(fn() => dates(['frequency' => 'weekly', 'weekdays' => [8], 'count' => 2], '2026-11-04'), 'Weekdays');
    assert_throws(fn() => dates(['frequency' => 'monthly_weekday', 'nth' => 6, 'count' => 2], '2026-11-04'), 'week of');
    assert_throws(fn() => dates(['frequency' => 'monthly_day', 'day' => 32, 'count' => 2], '2026-11-04'), 'day of');
    assert_throws(fn() => dates(['frequency' => 'weekly', 'until' => '2026-10-01'], '2026-11-04'), 'before');
    assert_throws(fn() => dates(['frequency' => 'weekly', 'until' => '2026-02-30'], '2026-11-04'), 'Invalid');
    assert_throws(fn() => dates(['frequency' => 'weekly', 'count' => 'abc'], '2026-11-04'), 'whole number');
});

test('refuses rules that exceed the maximum instead of truncating', function () {
    assert_throws(
        fn() => dates(['frequency' => 'weekly', 'weekdays' => [1, 2, 3, 4, 5], 'until' => '2028-12-31'], '2026-11-02'),
        'more than',
    );
    assert_throws(fn() => dates(['frequency' => 'weekly', 'count' => 261], '2026-11-04'), 'between 1 and 260');
});

test('maximum count is allowed', function () {
    assert_same(260, count(dates(['frequency' => 'weekly', 'count' => 260], '2026-11-04')));
});

test('nth_weekday_of_month helper', function () {
    $month = new DateTimeImmutable('2026-03-15');

    assert_same('2026-03-02', Recurrence_rule::nth_weekday_of_month($month, 1, 1)->format('Y-m-d'));
    assert_same('2026-03-31', Recurrence_rule::nth_weekday_of_month($month, -1, 2)->format('Y-m-d'));
    assert_same('2026-03-29', Recurrence_rule::nth_weekday_of_month($month, -1, 7)->format('Y-m-d'));
    assert_same(null, Recurrence_rule::nth_weekday_of_month($month, 5, 4));
});

test('shift moves a rule to another day', function () {
    // Wednesday → Thursday; Sunday wraps to Monday.
    assert_same(
        ['frequency' => 'weekly', 'interval' => 1, 'weekdays' => [1, 4], 'until' => '2026-12-04'],
        Recurrence_rule::shift(['frequency' => 'weekly', 'interval' => 1, 'weekdays' => [3, 7], 'until' => '2026-12-03'], 1),
    );
    // First Monday → first Sunday (one day back).
    assert_same(
        ['frequency' => 'monthly_weekday', 'interval' => 1, 'nth' => 1, 'weekday' => 7, 'count' => 3],
        Recurrence_rule::shift(['frequency' => 'monthly_weekday', 'interval' => 1, 'nth' => 1, 'weekday' => 1, 'count' => 3], -1),
    );
    // Day of the month moves, but not past 31.
    assert_same(16, Recurrence_rule::shift(['frequency' => 'monthly_day', 'day' => 15, 'count' => 2], 1)['day']);
    assert_same(31, Recurrence_rule::shift(['frequency' => 'monthly_day', 'day' => 31, 'count' => 2], 1)['day']);
    // A shifted rule still expands to the moved dates.
    $shifted = Recurrence_rule::shift(['frequency' => 'weekly', 'weekdays' => [3], 'count' => 3], 1);
    assert_same(['2026-11-05', '2026-11-12', '2026-11-19'], dates($shifted, '2026-11-05'));
});

echo "\nSeries_conflict_checker\n";

$working_plan = [
    'monday' => ['start' => '09:00', 'end' => '17:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]],
    'tuesday' => ['start' => '09:00', 'end' => '17:00', 'breaks' => []],
    'wednesday' => ['start' => '09:00', 'end' => '17:00', 'breaks' => []],
    'thursday' => null,
    'friday' => ['start' => '09:00', 'end' => '13:00', 'breaks' => []],
    'saturday' => null,
    'sunday' => null,
];

$exceptions = [
    '2026-11-11' => null, // Wednesday off.
    '2026-11-12' => ['start' => '10:00', 'end' => '12:00', 'breaks' => []], // Thursday, normally off.
];

$events = [
    ['id' => 10, 'start_datetime' => '2026-11-04 10:00:00', 'end_datetime' => '2026-11-04 11:00:00', 'is_unavailability' => 0],
    ['id' => 11, 'start_datetime' => '2026-11-18 14:00:00', 'end_datetime' => '2026-11-18 16:00:00', 'is_unavailability' => 1],
];

$blocked = [['name' => 'Holiday', 'start_datetime' => '2026-12-24 00:00:00', 'end_datetime' => '2026-12-27 00:00:00']];

$checker = new Series_conflict_checker($working_plan, $exceptions, $events, $blocked);

function types(array $conflicts): array
{
    return array_column($conflicts, 'type');
}

test('free slot', function () use ($checker) {
    assert_same([], $checker->check('2026-11-04 11:00:00', '2026-11-04 12:00:00'));
});

test('touching edges do not conflict', function () use ($checker) {
    assert_same([], $checker->check('2026-11-04 09:00:00', '2026-11-04 10:00:00'));
});

test('overlapping appointment', function () use ($checker) {
    assert_same(['appointment'], types($checker->check('2026-11-04 10:30:00', '2026-11-04 11:30:00')));
});

test('excluded appointment does not conflict with itself', function () use ($checker) {
    assert_same([], $checker->check('2026-11-04 10:30:00', '2026-11-04 11:30:00', [10]));
});

test('overlapping unavailability', function () use ($checker) {
    assert_same(['unavailability'], types($checker->check('2026-11-18 15:00:00', '2026-11-18 16:00:00')));
});

test('day off in the weekly plan', function () use ($checker) {
    assert_same(['outside_working_plan'], types($checker->check('2026-11-05 10:00:00', '2026-11-05 11:00:00')));
});

test('outside working hours', function () use ($checker) {
    assert_same(['outside_working_plan'], types($checker->check('2026-11-06 12:30:00', '2026-11-06 13:30:00')));
});

test('break', function () use ($checker) {
    assert_same(['break'], types($checker->check('2026-11-02 11:30:00', '2026-11-02 12:30:00')));
});

test('exception: day off overrides the weekly plan', function () use ($checker) {
    assert_same(['outside_working_plan'], types($checker->check('2026-11-11 10:00:00', '2026-11-11 11:00:00')));
});

test('exception: extra working day overrides the weekly plan', function () use ($checker) {
    assert_same([], $checker->check('2026-11-12 10:00:00', '2026-11-12 11:00:00'));
    assert_same(['outside_working_plan'], types($checker->check('2026-11-12 11:30:00', '2026-11-12 12:30:00')));
});

test('blocked period', function () use ($checker) {
    // 2026-12-24 is a Thursday (day off) and in the blocked period.
    assert_same(
        ['outside_working_plan', 'blocked_period'],
        types($checker->check('2026-12-24 10:00:00', '2026-12-24 11:00:00')),
    );
});

test('conflicts carry a translation key, params and an English message', function () use ($checker) {
    $conflict = $checker->check('2026-11-04 10:30:00', '2026-11-04 11:30:00')[0];

    assert_same('conflict_appointment', $conflict['key']);
    assert_same(['start' => '10:00', 'end' => '11:00'], $conflict['params']);
    assert_same('Overlaps another appointment (10:00 - 11:00).', $conflict['message']);
    assert_same(10, $conflict['id']);
});

echo "\nTranslations\n";

function language_file(string $language): array
{
    $lang = [];

    include APPPATH . 'language/' . $language . '/recurring_appointments_lang.php';

    return $lang;
}

function placeholders(string $text): array
{
    preg_match_all('/\{(\w+)\}/', $text, $matches);
    $names = $matches[1];
    sort($names);

    return $names;
}

$english = language_file('english');

foreach (glob(APPPATH . 'language/*/recurring_appointments_lang.php') as $file) {
    $language = basename(dirname($file));

    if ($language === 'english') {
        continue;
    }

    test("{$language}: same keys and placeholders as English", function () use ($language, $english) {
        $translated = language_file($language);

        assert_same([], array_values(array_diff(array_keys($english), array_keys($translated))), 'missing keys');
        assert_same([], array_values(array_diff(array_keys($translated), array_keys($english))), 'unknown keys');

        foreach ($english as $key => $text) {
            assert_same(placeholders($text), placeholders($translated[$key]), "placeholders of {$key}");
        }
    });
}

test('every rule error and conflict has an English string', function () use ($english) {
    $source = file_get_contents(APPPATH . 'libraries/Recurrence_rule.php');
    preg_match_all("/new Recurrence_rule_exception\\(\\s*'(\\w+)'/", $source, $matches);

    foreach (array_unique($matches[1]) as $key) {
        assert_same(true, isset($english['ra_' . $key]), "ra_{$key} in the English file");
    }

    foreach (Series_conflict_checker::MESSAGES as $key => $message) {
        assert_same($message, $english['ra_' . $key] ?? null, "ra_{$key} matches the checker message");
    }
});

test('every string key used in the code exists in English', function () use ($english) {
    $root = dirname(__DIR__) . '/src/';
    $used = [];

    // PHP: ra_lang('key'); JS: t('key'), tCount('key'), toggleSwitch(..., 'key').
    foreach (glob($root . 'application/{controllers,hooks,libraries,helpers}/*.php', GLOB_BRACE) as $file) {
        preg_match_all("/ra_lang\\('(\\w+)'/", file_get_contents($file), $matches);
        $used = array_merge($used, $matches[1]);
    }

    foreach (glob($root . 'assets/js/*.js') as $file) {
        preg_match_all("/\\b(?:t|tCount)\\('(\\w+)'|toggleSwitch\\('\\w+', '(\\w+)'\\)/", file_get_contents($file), $matches);
        $used = array_merge($used, array_filter($matches[1]), array_filter($matches[2]));
    }

    // Keys that the code builds from a prefix.
    foreach (['weekly', 'every_2_weeks', 'every_4_weeks', 'monthly'] as $pattern) {
        $used[] = 'booking_' . $pattern;
    }

    foreach (['summary_weekly', 'summary_monthly_weekday', 'summary_monthly_day'] as $summary) {
        $used[] = $summary;
        $used[] = $summary . '_one';
    }

    foreach (['series_created', 'series_skipped', 'series_updated', 'series_deleted'] as $count_key) {
        $used[] = $count_key . '_one';
    }

    assert_same(true, count($used) > 50, 'the scan found the keys');

    // A key ending in "_" is a prefix such as t('booking_' + pattern); its full keys are listed above.
    $missing = array_values(
        array_filter(array_unique($used), fn($key) => !str_ends_with($key, '_') && !isset($english['ra_' . $key])),
    );

    assert_same([], $missing, 'keys without an English string');
});

test('rule errors carry key and params', function () {
    try {
        dates(['frequency' => 'weekly', 'count' => 999], '2026-11-04');
    } catch (Recurrence_rule_exception $e) {
        assert_same('error_count', $e->key);
        assert_same(['max' => 260], $e->params);

        return;
    }

    throw new RuntimeException('Expected a Recurrence_rule_exception');
});

test('ra_lang translates, fills placeholders and falls back to English', function () {
    global $test_language;

    $test_language = 'dutch';
    assert_same('Deze afspraak herhalen', ra_lang('repeat_this'));
    assert_same('Het aantal keer moet tussen 1 en 260 liggen.', ra_lang('error_count', ['max' => 260]));

    // A language without a file of the extension (e.g. Greek) uses English.
    $test_language = 'greek';
    assert_same('Repeat this appointment', ra_lang('repeat_this'));

    // Unsafe language names never reach the file system.
    $test_language = '../../etc';
    assert_same('Repeat this appointment', ra_lang('repeat_this'));

    $test_language = 'english';
});

echo "\nRelease\n";

test('VERSION matches the newest CHANGELOG entry', function () {
    preg_match("/public const VERSION = '([^']+)'/", file_get_contents(APPPATH . 'controllers/Recurring_appointments.php'), $version);
    preg_match('/^## \[([^\]]+)\]/m', file_get_contents(__DIR__ . '/../CHANGELOG.md'), $changelog);

    assert_same($version[1] ?? null, $changelog[1] ?? null);
});

echo "\nInstaller\n";

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function run_installer(array $arguments): array
{
    $command = array_merge([PHP_BINARY, __DIR__ . '/../install.php'], $arguments);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

    return [proc_close($process), $output];
}

test('install twice, then uninstall, against a fake installation', function () {
    $target = sys_get_temp_dir() . '/ra-installer-test-' . getmypid();
    remove_tree($target);

    foreach (['application/config', 'application/controllers', 'application/language/english'] as $folder) {
        mkdir($target . '/' . $folder, 0777, true);
    }

    $original_hooks = "<?php\n\$hook['post_system'][] = [];\n";

    file_put_contents($target . '/index.php', '<?php');
    file_put_contents($target . '/application/controllers/Calendar.php', '<?php');
    file_put_contents($target . '/application/config/app.php', "<?php\n\$config['version'] = '1.6.1';\n");
    file_put_contents($target . '/application/config/hooks.php', $original_hooks);

    try {
        [$code, $output] = run_installer([$target, '--no-db']);
        assert_same(0, $code, $output);
        [$code, $output] = run_installer([$target, '--no-db']);
        assert_same(0, $code, $output);

        $hooks = file_get_contents($target . '/application/config/hooks.php');
        assert_same(1, substr_count($hooks, "include APPPATH . 'config/recurring_appointments_hooks.php'"), 'hook lines');

        foreach (
            [
                'application/controllers/Recurring_appointments.php',
                'application/helpers/recurring_appointments_helper.php',
                'application/language/dutch/recurring_appointments_lang.php',
                'assets/js/recurring_appointments.js',
            ]
            as $file
        ) {
            assert_same(true, is_file($target . '/' . $file), $file);
        }

        // The hook file must register correctly when hooks.php is evaluated (as CodeIgniter does).
        $hook = [];
        $register = function () use ($target, &$hook) {
            include $target . '/application/config/recurring_appointments_hooks.php';
        };
        $register();
        assert_same('recurring_appointments_inject_script', $hook['display_override'][0]['function'] ?? null);

        [$code, $output] = run_installer([$target, '--uninstall', '--keep-db']);
        assert_same(0, $code, $output);
        assert_same($original_hooks, file_get_contents($target . '/application/config/hooks.php'), 'hooks.php restored');
        assert_same(false, is_file($target . '/application/controllers/Recurring_appointments.php'), 'controller removed');
        assert_same(true, is_file($target . '/application/controllers/Calendar.php'), 'core file kept');

        [$code] = run_installer([$target . '/does-not-exist']);
        assert_same(1, $code, 'refuses a folder that is not an installation');
    } finally {
        remove_tree($target);
    }
});

echo "\n{$passes} passed, {$failures} failed\n";

exit($failures ? 1 : 0);
