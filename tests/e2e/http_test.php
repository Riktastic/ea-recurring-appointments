<?php

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * End-to-end test against a running Easy!Appointments with the extension installed. It logs in over HTTP like a
 * browser does (session cookie + CSRF token) and checks the results in the database.
 *
 * ONLY run this against a throwaway test installation: it rewrites the working plan of a provider, changes user
 * languages and passwords, and creates and deletes appointments.
 *
 * Usage (see tests/e2e/README.md for the Docker setup):
 *   php tests/e2e/http_test.php --url=http://localhost --db-host=db --db-user=ea --db-pass=ea-test --db-name=easyappointments
 * ---------------------------------------------------------------------------- */

$options = getopt('', [
    'url:',
    'db-host:',
    'db-user:',
    'db-pass:',
    'db-name:',
    'db-prefix::',
    'admin-user::',
    'admin-pass::',
    'ea-path::',
]);

foreach (['url', 'db-host', 'db-user', 'db-pass', 'db-name'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing --{$required}\n");
        exit(1);
    }
}

$base_url = rtrim($options['url'], '/') . '/index.php/';
$prefix = $options['db-prefix'] ?? 'ea_';
$admin_user = $options['admin-user'] ?? 'administrator';
$admin_pass = $options['admin-pass'] ?? 'administrator';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($options['db-host'], $options['db-user'], $options['db-pass'], $options['db-name']);
$db->set_charset('utf8mb4');

$passes = 0;
$failures = 0;

function test(string $name, callable $body): void
{
    global $passes, $failures;

    try {
        $body();
        $passes++;
        echo "  ok    {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n        " . str_replace("\n", "\n        ", $e->getMessage()) . "\n";
    }
}

function check(bool $condition, string $message, mixed $context = null): void
{
    if (!$condition) {
        throw new RuntimeException($message . ($context !== null ? "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''));
    }
}

function q(string $sql, array $params = []): array
{
    global $db;

    $statement = $db->prepare($sql);

    if ($params) {
        $statement->bind_param(str_repeat('s', count($params)), ...array_map('strval', $params));
    }

    $statement->execute();
    $result = $statement->get_result();

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function t(string $table): string
{
    global $prefix;

    return '`' . $prefix . $table . '`';
}

/**
 * Minimal browser: cookie jar, CSRF token from the csrf_cookie.
 */
final class Client
{
    private string $jar;

    public function __construct(private readonly string $base_url)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'ra-e2e-');
    }

    public function get(string $path): array
    {
        return $this->request($path, null);
    }

    public function post(string $path, array $data): array
    {
        return $this->request($path, array_merge(['csrf_token' => $this->csrf()], $data));
    }

    public function login(string $username, string $password): void
    {
        $this->get('login');
        $response = $this->post('login/validate', ['username' => $username, 'password' => $password]);

        check(($response['json']['success'] ?? false) === true, "Login as {$username} failed", $response);
    }

    private function csrf(): string
    {
        foreach (file($this->jar) ?: [] as $line) {
            $fields = explode("\t", trim($line));

            if (count($fields) === 7 && $fields[5] === 'csrf_cookie') {
                return $fields[6];
            }
        }

        return '';
    }

    private function request(string $path, ?array $data): array
    {
        $curl = curl_init($this->base_url . $path);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ]);

        if ($data !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        // Release the cookie jar to disk for the next request.
        return ['status' => $status, 'body' => (string) $body, 'json' => json_decode((string) $body, true)];
    }
}

/**
 * Call the REST API with Basic auth (admin credentials) and a JSON body.
 */
function api_request(string $base_url, string $username, string $password, string $method, string $path, ?array $body = null): array
{
    $curl = curl_init($base_url . $path);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);

    if ($username !== '') {
        curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
    }

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = (string) curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return ['status' => $status, 'body' => $response, 'json' => json_decode($response, true)];
}

/**
 * The core rate limiter counts requests per IP address in its file cache. The suite sends far more requests than a
 * person would, so it clears those counters (only when --ea-path is given, i.e. on a throwaway copy).
 */
function reset_rate_limit(): void
{
    global $options;

    if (empty($options['ea-path'])) {
        return;
    }

    foreach (glob(rtrim($options['ea-path'], '/') . '/storage/cache/rate_limit*') ?: [] as $file) {
        unlink($file);
    }
}

// --------------------------------------------------------------------------------------------------------------------
// Fixture
// --------------------------------------------------------------------------------------------------------------------

/**
 * Run "php index.php ..." in the Easy!Appointments folder.
 */
function ea_cli(string $ea_path, array $arguments): string
{
    $process = proc_open(
        array_merge([PHP_BINARY, 'index.php'], $arguments),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $ea_path,
    );

    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($process);

    return $output;
}

if (!empty($options['ea-path'])) {
    echo "Reset\n";

    reset_rate_limit();

    // With the extension installed, the core "migrate fresh" (all migrations down and up again) must still work: the
    // extension may never block core migrations.
    test('core reinstall (migrate fresh) works with the extension installed', function () use ($options) {
        $output = ea_cli($options['ea-path'], ['console', 'install']);

        check(str_contains($output, 'Installation completed'), 'console install failed', $output);
    });

    test('extension install is idempotent', function () use ($options) {
        foreach ([1, 2] as $run) {
            $output = ea_cli($options['ea-path'], ['recurring_appointments', 'install']);
            check(!preg_match('/error|exception/i', $output), "install run {$run} failed", $output);
        }

        check(str_contains(ea_cli($options['ea-path'], ['recurring_appointments', 'status']), 'installed'), 'status');
    });

    echo "\n";
}

$provider_id = (int) q(
    'SELECT u.id FROM ' . t('users') . ' u JOIN ' . t('roles') . " r ON r.id = u.id_roles WHERE r.slug = 'provider' ORDER BY u.id LIMIT 1",
)[0]['id'];
$customer_id = (int) q(
    'SELECT u.id FROM ' . t('users') . ' u JOIN ' . t('roles') . " r ON r.id = u.id_roles WHERE r.slug = 'customer' ORDER BY u.id LIMIT 1",
)[0]['id'];
$service_id = (int) q('SELECT id FROM ' . t('services') . ' ORDER BY id LIMIT 1')[0]['id'];

$day = ['start' => '09:00', 'end' => '17:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]];
$working_plan = json_encode([
    'monday' => $day,
    'tuesday' => $day,
    'wednesday' => $day,
    'thursday' => $day,
    'friday' => $day,
    'saturday' => null,
    'sunday' => null,
]);

q('UPDATE ' . t('user_settings') . ' SET working_plan = ? WHERE id_users = ?', [$working_plan, $provider_id]);
q('DELETE FROM ' . t('appointments') . ' WHERE id_users_provider = ?', [$provider_id]);
q('UPDATE ' . t('users') . " SET language = 'dutch' WHERE id = (SELECT id_users FROM " . t('user_settings') . ' WHERE username = ?)', [$admin_user]);

// The provider gets a known password for the permission test.
q('UPDATE ' . t('user_settings') . " SET username = 'ra-provider', password = ? WHERE id_users = ?", [
    password_hash('ra-provider-pass', PASSWORD_BCRYPT),
    $provider_id,
]);

// A Wednesday at least two weeks ahead, so that nothing is in the past.
$wednesday = new DateTimeImmutable('next wednesday +2 weeks');
$base = $wednesday->format('Y-m-d');

function appointment(string $date, string $start, string $end): array
{
    global $provider_id, $service_id, $customer_id;

    return [
        'id_services' => $service_id,
        'id_users_provider' => $provider_id,
        'id_users_customer' => $customer_id,
        'start_datetime' => "{$date} {$start}:00",
        'end_datetime' => "{$date} {$end}:00",
        'location' => 'Room 1',
        'notes' => 'e2e',
        'status' => '',
        'is_unavailability' => 0,
    ];
}

function customer(): array
{
    global $customer_id;

    $row = q('SELECT * FROM ' . t('users') . ' WHERE id = ?', [$customer_id])[0];

    return [
        'id' => $customer_id,
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'email' => $row['email'],
        'phone_number' => $row['phone_number'] ?: '0123456789',
        'timezone' => $row['timezone'] ?: 'UTC',
        'language' => $row['language'] ?: 'english',
    ];
}

$admin = new Client($base_url);
$admin->login($admin_user, $admin_pass);

$series_id = null;
$occurrence_ids = [];

echo "Calendar page\n";

test('calendar page loads the script with Dutch strings', function () use ($admin) {
    $page = $admin->get('calendar');

    check($page['status'] === 200, 'calendar status ' . $page['status']);
    check(str_contains($page['body'], 'assets/js/recurring_appointments.js'), 'script tag missing');
    check(str_contains($page['body'], 'Deze afspraak herhalen'), 'Dutch strings missing');
    check(str_contains($page['body'], '"installed":true'), 'installed flag missing');
});

test('other pages are left alone', function () use ($admin) {
    $page = $admin->get('customers');

    check($page['status'] === 200, 'customers status ' . $page['status']);
    check(!str_contains($page['body'], 'recurring_appointments.js'), 'script injected on another page');
});

echo "\nCreate\n";

test('preview: every Wednesday, 4 times, no conflicts', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'weekdays' => [3], 'count' => 4],
    ]);

    $occurrences = $response['json']['occurrences'] ?? [];

    check(count($occurrences) === 4, 'expected 4 occurrences', $response['json'] ?? $response['body']);
    check(($response['json']['conflict_count'] ?? -1) === 0, 'expected no conflicts', $response['json']);

    foreach ($occurrences as $index => $occurrence) {
        $expected = (new DateTimeImmutable($base))->modify("+{$index} weeks")->format('Y-m-d') . ' 10:00:00';
        check($occurrence['start_datetime'] === $expected, "occurrence {$index}", $occurrence);
    }
});

test('create: 4 real appointment rows linked to one series', function () use ($admin, $base, &$series_id, &$occurrence_ids) {
    $response = $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'weekdays' => [3], 'count' => 4],
        'notify' => 'first',
    ]);

    check(($response['json']['success'] ?? false) === true, 'create failed', $response['json'] ?? $response['body']);
    check($response['json']['created'] === 4, 'expected 4 created', $response['json']);

    $series_id = (int) $response['json']['series_id'];

    $rows = q(
        'SELECT a.id, a.start_datetime, a.hash, o.sequence FROM ' .
            t('appointment_series_occurrences') .
            ' o JOIN ' .
            t('appointments') .
            ' a ON a.id = o.id_appointments WHERE o.id_appointment_series = ? ORDER BY a.start_datetime',
        [$series_id],
    );

    check(count($rows) === 4, 'expected 4 linked rows', $rows);
    check(count(array_unique(array_column($rows, 'hash'))) === 4, 'every occurrence needs its own hash', $rows);
    check(array_map('intval', array_column($rows, 'sequence')) === [1, 2, 3, 4], 'sequence', $rows);

    $occurrence_ids = array_map('intval', array_column($rows, 'id'));
});

test('occurrences show up in the core calendar endpoint', function () use ($admin, $base, &$occurrence_ids) {
    global $provider_id;

    $response = $admin->post('calendar/get_calendar_appointments', [
        'record_id' => $provider_id,
        'filter_type' => 'provider',
        'start_date' => $base,
        'end_date' => (new DateTimeImmutable($base))->modify('+4 weeks')->format('Y-m-d'),
    ]);

    $ids = array_map('intval', array_column($response['json']['appointments'] ?? [], 'id'));

    check(!array_diff($occurrence_ids, $ids), 'calendar misses occurrences', $ids);
});

test('info: position and total', function () use ($admin, &$occurrence_ids) {
    $response = $admin->post('recurring_appointments/info', ['appointment_id' => $occurrence_ids[1]]);
    $series = $response['json']['series'] ?? null;

    check($series !== null, 'no series info', $response['json'] ?? $response['body']);
    check($series['position'] === 2 && $series['total'] === 4, 'position/total', $series);
    check($series['rule']['frequency'] === 'weekly' && $series['rule']['weekdays'] === [3], 'rule', $series);
});

test('info: a regular appointment has no series', function () use ($admin, $base) {
    global $provider_id, $service_id, $customer_id;

    $response = $admin->post('calendar/save_appointment', [
        'appointment_data' => appointment((new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d'), '15:00', '16:00'),
        'customer_data' => customer(),
        'notify_users' => 0,
    ]);

    check(($response['json']['success'] ?? false) === true, 'core save failed', $response['json'] ?? $response['body']);

    $id = (int) q('SELECT id FROM ' . t('appointments') . " WHERE notes = 'e2e' AND start_datetime LIKE ?", [
        (new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d') . '%',
    ])[0]['id'];

    $info = $admin->post('recurring_appointments/info', ['appointment_id' => $id]);

    check(array_key_exists('series', $info['json'] ?? []) && $info['json']['series'] === null, 'expected null', $info['json']);
});

echo "\nPatterns and conflicts\n";

test('every two weeks', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '14:00', '15:00'),
        'rule' => ['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [3], 'count' => 3],
    ]);

    $dates = array_column($response['json']['occurrences'] ?? [], 'date');
    $expected = [0, 2, 4];

    check(
        $dates === array_map(fn($weeks) => (new DateTimeImmutable($base))->modify("+{$weeks} weeks")->format('Y-m-d'), $expected),
        'dates',
        $dates,
    );
});

test('first Monday of the month', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '14:00', '15:00'),
        'rule' => ['frequency' => 'monthly_weekday', 'nth' => 1, 'weekday' => 1, 'count' => 3],
    ]);

    $dates = array_column($response['json']['occurrences'] ?? [], 'date');

    check(count($dates) === 3, 'count', $response['json'] ?? $response['body']);

    foreach ($dates as $date) {
        $object = new DateTimeImmutable($date);
        check($object->format('N') === '1' && (int) $object->format('j') <= 7, "{$date} is not a first Monday");
    }
});

test('overlap with the series is reported with a translatable key', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($base, '10:30', '11:30'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'weekdays' => [3], 'count' => 2],
    ]);

    check(($response['json']['conflict'] ?? false) === true, 'expected a conflict response', $response['json'] ?? $response['body']);
    check($response['json']['occurrences'][0]['conflicts'][0]['key'] === 'conflict_appointment', 'key', $response['json']);
    check(
        (int) q('SELECT COUNT(*) AS c FROM ' . t('appointments') . " WHERE start_datetime = ?", ["{$base} 10:30:00"])[0]['c'] === 0,
        'nothing may be written on a conflict',
    );
});

test('skip mode creates only the free dates', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($base, '10:30', '11:30'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'weekdays' => [3], 'count' => 6],
        'conflict_mode' => 'skip',
        'notify' => 'none',
    ]);

    check(($response['json']['success'] ?? false) === true, 'skip failed', $response['json'] ?? $response['body']);
    check($response['json']['created'] === 2 && count($response['json']['skipped']) === 4, 'created/skipped', $response['json']);
});

test('day off, break and working hours', function () use ($admin, $base) {
    $sunday = (new DateTimeImmutable($base))->modify('next sunday')->format('Y-m-d');

    $off = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($sunday, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);
    $break = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment((new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d'), '12:30', '13:30'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);
    $late = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment((new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d'), '16:30', '17:30'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);

    check(array_column($off['json']['occurrences'][0]['conflicts'] ?? [], 'key') === ['conflict_day_off'], 'day off', $off['json']);
    check(array_column($break['json']['occurrences'][0]['conflicts'] ?? [], 'key') === ['conflict_break'], 'break', $break['json']);
    check(array_column($late['json']['occurrences'][0]['conflicts'] ?? [], 'key') === ['conflict_outside_hours'], 'hours', $late['json']);
});

test('validation errors are translated (Dutch)', function () use ($admin, $base) {
    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 999],
    ]);

    check(
        str_contains($response['json']['message'] ?? '', 'Het aantal keer moet tussen 1 en 400 liggen'),
        'Dutch error',
        $response['json'] ?? $response['body'],
    );
});

echo "\nEdit and delete\n";

test('save this and following moves only later occurrences', function () use ($admin, $base, &$occurrence_ids) {
    $second = q('SELECT * FROM ' . t('appointments') . ' WHERE id = ?', [$occurrence_ids[1]])[0];
    $date = substr($second['start_datetime'], 0, 10);

    $response = $admin->post('recurring_appointments/update', [
        'appointment_data' => array_merge(appointment($date, '14:00', '15:30'), ['id' => $occurrence_ids[1], 'notes' => 'moved']),
        'customer_data' => customer(),
        'scope' => 'following',
        'conflict_mode' => 'abort',
    ]);

    check(($response['json']['success'] ?? false) === true, 'update failed', $response['json'] ?? $response['body']);
    check($response['json']['updated'] === 3, 'expected 3 updated', $response['json']);

    $rows = q(
        'SELECT id, start_datetime, end_datetime, notes FROM ' . t('appointments') . ' WHERE id IN (' . implode(',', $occurrence_ids) . ') ORDER BY id',
    );

    check(substr($rows[0]['start_datetime'], 11) === '10:00:00' && $rows[0]['notes'] === 'e2e', 'first untouched', $rows);

    foreach (array_slice($rows, 1) as $index => $row) {
        $expected_date = (new DateTimeImmutable($base))->modify('+' . ($index + 1) . ' weeks')->format('Y-m-d');
        check($row['start_datetime'] === "{$expected_date} 14:00:00", 'start', $row);
        check($row['end_datetime'] === "{$expected_date} 15:30:00", 'end', $row);
        check($row['notes'] === 'moved', 'notes', $row);
    }
});

test('delete this and following', function () use ($admin, &$occurrence_ids, &$series_id) {
    $response = $admin->post('recurring_appointments/delete', [
        'appointment_id' => $occurrence_ids[2],
        'scope' => 'following',
        'notify' => 'none',
    ]);

    check(($response['json']['deleted'] ?? 0) === 2, 'expected 2 deleted', $response['json'] ?? $response['body']);
    check(
        (int) q('SELECT COUNT(*) AS c FROM ' . t('appointment_series_occurrences') . ' WHERE id_appointment_series = ?', [$series_id])[0]['c'] === 2,
        'two left in the series',
    );
});

test('detach keeps the appointment but removes the link', function () use ($admin, &$occurrence_ids) {
    $response = $admin->post('recurring_appointments/detach', ['appointment_id' => $occurrence_ids[0]]);

    check(($response['json']['success'] ?? false) === true, 'detach failed', $response['json'] ?? $response['body']);
    check(count(q('SELECT id FROM ' . t('appointments') . ' WHERE id = ?', [$occurrence_ids[0]])) === 1, 'appointment kept');
    check(
        count(q('SELECT * FROM ' . t('appointment_series_occurrences') . ' WHERE id_appointments = ?', [$occurrence_ids[0]])) === 0,
        'link removed',
    );
});

test('core delete button on the last occurrence: link and series are cleaned up', function () use ($admin, &$occurrence_ids, &$series_id) {
    $response = $admin->post('calendar/delete_appointment', ['appointment_id' => $occurrence_ids[1], 'notify_users' => 0]);

    check(($response['json']['success'] ?? false) === true, 'core delete failed', $response['json'] ?? $response['body']);

    // The next request of the extension removes the stale link and the now empty series.
    $admin->post('recurring_appointments/info', ['appointment_id' => $occurrence_ids[0]]);

    check(
        count(q('SELECT * FROM ' . t('appointment_series_occurrences') . ' WHERE id_appointments = ?', [$occurrence_ids[1]])) === 0,
        'stale link should be removed',
    );
    check(count(q('SELECT * FROM ' . t('appointment_series') . ' WHERE id = ?', [$series_id])) === 0, 'empty series removed');
});

test('a stale link never attaches to another appointment with a re-used ID', function () use ($admin, $base) {
    $series = $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment((new DateTimeImmutable($base))->modify('+2 days')->format('Y-m-d'), '09:00', '10:00'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
        'notify' => 'none',
    ]);

    check(($series['json']['success'] ?? false) === true, 'create failed', $series['json'] ?? $series['body']);

    $link = q('SELECT * FROM ' . t('appointment_series_occurrences') . ' WHERE id_appointment_series = ?', [$series['json']['series_id']])[0];

    // Simulate an ID that got re-used by an unrelated appointment: same ID, other hash.
    q('UPDATE ' . t('appointments') . " SET hash = 'someoneelse1' WHERE id = ?", [$link['id_appointments']]);

    $info = $admin->post('recurring_appointments/info', ['appointment_id' => $link['id_appointments']]);

    check(
        is_array($info['json']) && array_key_exists('series', $info['json']) && $info['json']['series'] === null,
        'unrelated appointment shown as part of a series',
        $info['json'] ?? $info['body'],
    );
});

echo "\nMoving and deleting a whole series\n";

// A fresh weekly series of 4 Wednesdays, far enough ahead to be clear of the appointments above.
$move_base = (new DateTimeImmutable($base))->modify('+30 weeks')->format('Y-m-d');
$move_ids = [];

function series_rows(array $ids): array
{
    return q(
        'SELECT a.id, a.start_datetime, a.end_datetime, o.id_appointment_series, o.sequence FROM ' . t('appointments') .
            ' a LEFT JOIN ' . t('appointment_series_occurrences') . ' o ON o.id_appointments = a.id WHERE a.id IN (' .
            implode(',', $ids) . ') ORDER BY a.start_datetime',
    );
}

test('create a series to move', function () use ($admin, $move_base, &$move_ids) {
    $response = $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($move_base, '10:00', '11:00'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'weekdays' => [3], 'count' => 4],
        'notify' => 'none',
    ]);

    check(($response['json']['created'] ?? 0) === 4, 'create failed', $response['json'] ?? $response['body']);

    $move_ids = array_map(
        'intval',
        array_column(q('SELECT id_appointments FROM ' . t('appointment_series_occurrences') . ' WHERE id_appointment_series = ? ORDER BY sequence', [$response['json']['series_id']]), 'id_appointments'),
    );
});

test('save all with only a new time keeps every date', function () use ($admin, $move_base, &$move_ids) {
    $response = $admin->post('recurring_appointments/update', [
        'appointment_data' => array_merge(appointment($move_base, '11:00', '12:00'), ['id' => $move_ids[0]]),
        'customer_data' => customer(),
        'scope' => 'all',
    ]);

    check(($response['json']['updated'] ?? 0) === 4, 'update failed', $response['json'] ?? $response['body']);

    foreach (series_rows($move_ids) as $index => $row) {
        $date = (new DateTimeImmutable($move_base))->modify("+{$index} weeks")->format('Y-m-d');
        check($row['start_datetime'] === "{$date} 11:00:00", 'time not changed', $row);
    }
});

test('save all after moving one occurrence a day later moves the whole series', function () use ($admin, $move_base, &$move_ids) {
    $second_thursday = (new DateTimeImmutable($move_base))->modify('+1 week +1 day')->format('Y-m-d');

    $response = $admin->post('recurring_appointments/update', [
        'appointment_data' => array_merge(appointment($second_thursday, '11:00', '12:00'), ['id' => $move_ids[1]]),
        'customer_data' => customer(),
        'scope' => 'all',
    ]);

    check(($response['json']['updated'] ?? 0) === 4, 'update failed', $response['json'] ?? $response['body']);

    $rows = series_rows($move_ids);

    foreach ($rows as $index => $row) {
        $date = (new DateTimeImmutable($move_base))->modify("+{$index} weeks +1 day")->format('Y-m-d');
        check($row['start_datetime'] === "{$date} 11:00:00", 'not moved to Thursday', $row);
    }

    check(count(array_unique(array_column($rows, 'id_appointment_series'))) === 1, 'should stay one series', $rows);

    $info = $admin->post('recurring_appointments/info', ['appointment_id' => $move_ids[0]]);

    check(($info['json']['series']['rule']['weekdays'] ?? null) === [4], 'rule should say Thursday', $info['json'] ?? $info['body']);
});

test('save this and following after moving a day splits the series', function () use ($admin, $move_base, &$move_ids) {
    // Third occurrence (a Thursday now) moves to Friday.
    $third_friday = (new DateTimeImmutable($move_base))->modify('+2 weeks +2 days')->format('Y-m-d');

    $response = $admin->post('recurring_appointments/update', [
        'appointment_data' => array_merge(appointment($third_friday, '11:00', '12:00'), ['id' => $move_ids[2]]),
        'customer_data' => customer(),
        'scope' => 'following',
    ]);

    check(($response['json']['updated'] ?? 0) === 2, 'update failed', $response['json'] ?? $response['body']);

    $rows = series_rows($move_ids);
    $weekdays = array_map(fn($row) => (new DateTimeImmutable($row['start_datetime']))->format('N'), $rows);

    check($weekdays === ['4', '4', '5', '5'], 'expected Thu, Thu, Fri, Fri', $rows);
    check($rows[0]['id_appointment_series'] === $rows[1]['id_appointment_series'], 'first two stay together', $rows);
    check($rows[2]['id_appointment_series'] === $rows[3]['id_appointment_series'], 'last two together', $rows);
    check($rows[0]['id_appointment_series'] !== $rows[2]['id_appointment_series'], 'should be split', $rows);
    check(array_map('intval', array_column($rows, 'sequence')) === [1, 2, 1, 2], 'sequences', $rows);

    $old = $admin->post('recurring_appointments/info', ['appointment_id' => $move_ids[0]])['json']['series'] ?? [];
    $new = $admin->post('recurring_appointments/info', ['appointment_id' => $move_ids[3]])['json']['series'] ?? [];

    check(($old['rule']['weekdays'] ?? null) === [4] && ($old['rule']['count'] ?? null) === 2 && $old['total'] === 2, 'old series', $old);
    check(($new['rule']['weekdays'] ?? null) === [5] && ($new['rule']['count'] ?? null) === 2 && $new['position'] === 2, 'new series', $new);
});

test('calendar markers: only series appointments are reported', function () use ($admin, &$move_ids, &$occurrence_ids) {
    $response = $admin->post('recurring_appointments/markers', [
        'appointment_ids' => array_merge($move_ids, [$occurrence_ids[0], 999999]),
    ]);

    $series = (array) ($response['json']['series'] ?? []);

    check(($response['json']['success'] ?? false) === true, 'markers failed', $response['json'] ?? $response['body']);
    check(count($series) === 4, 'expected the 4 moved occurrences', $series);
    check(!isset($series[$occurrence_ids[0]]), 'detached appointment marked', $series);
});

test('delete only this occurrence', function () use ($admin, &$move_ids) {
    $response = $admin->post('recurring_appointments/delete', ['appointment_id' => $move_ids[0], 'scope' => 'single']);

    check(($response['json']['deleted'] ?? 0) === 1, 'delete failed', $response['json'] ?? $response['body']);
    check(count(q('SELECT id FROM ' . t('appointments') . ' WHERE id IN (?, ?)', [$move_ids[0], $move_ids[1]])) === 1, 'only one gone');
});

test('delete entire series removes every occurrence and the series', function () use ($admin, &$move_ids) {
    $series_id = (int) series_rows([$move_ids[3]])[0]['id_appointment_series'];

    $response = $admin->post('recurring_appointments/delete', ['appointment_id' => $move_ids[2], 'scope' => 'all']);

    check(($response['json']['deleted'] ?? 0) === 2, 'delete failed', $response['json'] ?? $response['body']);
    check(count(q('SELECT id FROM ' . t('appointments') . ' WHERE id IN (?, ?)', [$move_ids[2], $move_ids[3]])) === 0, 'appointments left');
    check(count(q('SELECT id FROM ' . t('appointment_series') . ' WHERE id = ?', [$series_id])) === 0, 'series row left');
    check(count(q('SELECT id FROM ' . t('appointments') . ' WHERE id = ?', [$move_ids[1]])) === 1, 'other series untouched');
});

echo "\nPermissions and languages\n";
reset_rate_limit();

test('a provider cannot create a series for another provider', function () use ($base_url, $base) {
    global $provider_id;

    $provider = new Client($base_url);
    $provider->login('ra-provider', 'ra-provider-pass');

    $response = $provider->post('recurring_appointments/preview', [
        'appointment_data' => array_merge(appointment($base, '10:00', '11:00'), ['id_users_provider' => $provider_id + 1000]),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);

    check($response['status'] === 403, 'expected 403, got ' . $response['status'], $response['body']);

    $own = $provider->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '15:00', '16:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);

    check(($own['json']['success'] ?? false) === true, 'own provider should work', $own['json'] ?? $own['body']);
});

test('logged out requests are refused', function () use ($base_url, $base) {
    $anonymous = new Client($base_url);
    $anonymous->get('login');

    $response = $anonymous->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);

    check(($response['json']['success'] ?? false) !== true, 'anonymous request succeeded', $response['json'] ?? $response['body']);
});

test('German user: German strings and errors, English fallback in the page config', function () use ($base_url, $base, $admin_user, $admin_pass) {
    q('UPDATE ' . t('users') . " SET language = 'german' WHERE id = (SELECT id_users FROM " . t('user_settings') . ' WHERE username = ?)', [$admin_user]);

    $german = new Client($base_url);
    $german->login($admin_user, $admin_pass);

    $page = $german->get('calendar');
    check(str_contains($page['body'], 'Diesen Termin wiederholen'), 'German strings missing');

    $response = $german->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 999],
    ]);

    check(str_contains($response['json']['message'] ?? '', 'Die Anzahl muss zwischen 1 und 400 liegen'), 'German error', $response['json'] ?? $response['body']);
});

test('language without extension translations (Greek) falls back to English', function () use ($base_url, $admin_user, $admin_pass) {
    q('UPDATE ' . t('users') . " SET language = 'greek' WHERE id = (SELECT id_users FROM " . t('user_settings') . ' WHERE username = ?)', [$admin_user]);

    $greek = new Client($base_url);
    $greek->login($admin_user, $admin_pass);

    $page = $greek->get('calendar');
    check($page['status'] === 200, 'calendar status ' . $page['status']);
    check(str_contains($page['body'], 'Repeat this appointment'), 'English fallback missing');

    q('UPDATE ' . t('users') . " SET language = 'dutch' WHERE id = (SELECT id_users FROM " . t('user_settings') . ' WHERE username = ?)', [$admin_user]);
});

echo "\nSettings and customer bookings\n";

/**
 * Store an extension setting the way the core Save button on Settings → Booking does (null removes it).
 */
function set_setting(string $name, ?string $value): void
{
    q('DELETE FROM ' . t('settings') . ' WHERE name = ?', ['recurring_appointments_' . $name]);

    if ($value !== null) {
        q('INSERT INTO ' . t('settings') . ' (name, value) VALUES (?, ?)', ['recurring_appointments_' . $name, $value]);
    }
}

/**
 * Book like a customer on the public booking page, optionally with a repeat choice.
 */
function book_as_customer(Client $client, string $start, ?array $repeat, bool $manage_mode = false, bool $any_provider = false): array
{
    global $provider_id, $service_id;

    $client->get('');

    // The booking page sends the end too (the core uses it to check the customer's other appointments).
    $duration = (int) q('SELECT duration FROM ' . t('services') . ' WHERE id = ?', [$service_id])[0]['duration'];
    $end = (new DateTimeImmutable($start))->modify("+{$duration} minutes")->format('Y-m-d H:i:s');

    $data = [
        'post_data' => [
            'appointment' => [
                'id_services' => $service_id,
                'id_users_provider' => $any_provider ? 'any-provider' : $provider_id,
                'start_datetime' => $start,
                'end_datetime' => $end,
            ],
            'customer' => [
                'first_name' => 'Booking',
                'last_name' => 'Customer',
                'email' => 'booking.customer@example.org',
                'phone_number' => '+10000000099',
                'timezone' => 'UTC',
            ],
            'manage_mode' => $manage_mode ? '1' : '0',
        ],
    ];

    if ($repeat !== null) {
        $data['ra_repeat'] = $repeat;
    }

    return $client->post('booking/register', $data);
}

foreach (['staff_enabled', 'staff_default_on', 'booking_enabled', 'booking_max', 'booking_notify'] as $name) {
    set_setting($name, null);
}

// A Thursday that is free in the tests above, at 13:00 (right after the break).
$thursday = (new DateTimeImmutable($base))->modify('+1 day +3 weeks');

test('defaults: staff on, customers off', function () use ($admin, $base_url) {
    $page = $admin->get('calendar');
    check(str_contains($page['body'], '"staff_enabled":"1"') && str_contains($page['body'], '"booking_enabled":"0"'), 'defaults', substr($page['body'], 0, 300));

    $booking = (new Client($base_url))->get('');
    check($booking['status'] === 200 && !str_contains($booking['body'], 'recurring_appointments_booking.js'), 'booking page should be unchanged');
});

test('settings page gets the settings section', function () use ($admin) {
    $page = $admin->get('booking_settings');

    check(str_contains($page['body'], 'recurring_appointments_settings.js'), 'settings script missing');
});

test('staff switched off: series are refused with a clear message', function () use ($admin, $base) {
    set_setting('staff_enabled', '0');

    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 2],
    ]);

    set_setting('staff_enabled', null);

    check(($response['json']['success'] ?? true) === false, 'should be refused', $response['json'] ?? $response['body']);
    check(str_contains($response['json']['message'] ?? '', '→'), 'message should point to the settings page', $response['json']);
});

test('customers switched off: a repeat in the request is ignored', function () use ($base_url, $thursday) {
    $response = book_as_customer(new Client($base_url), $thursday->format('Y-m-d') . ' 15:00:00', ['pattern' => 'weekly', 'count' => 3]);

    check(!empty($response['json']['appointment_hash']), 'core booking failed', $response['json'] ?? $response['body']);
    check(!isset($response['json']['recurring']), 'repeat should be ignored', $response['json']);
});

test('customer books every week, a taken date is skipped and shown', function () use ($admin, $base_url, $thursday) {
    set_setting('booking_enabled', '1');

    // The second Thursday is taken.
    $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($thursday->modify('+1 week')->format('Y-m-d'), '13:00', '14:00'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
        'notify' => 'none',
    ]);

    $customer = new Client($base_url);

    check(str_contains($customer->get('')['body'], 'recurring_appointments_booking.js'), 'booking page script missing');

    $response = book_as_customer($customer, $thursday->format('Y-m-d') . ' 13:00:00', ['pattern' => 'weekly', 'count' => 3]);

    check(($response['json']['recurring'] ?? null) === ['booked' => 2, 'skipped' => 1], 'booked/skipped', $response['json'] ?? $response['body']);

    $rows = q(
        'SELECT a.start_datetime, a.id_users_customer FROM ' . t('appointments') . ' a JOIN ' . t('appointment_series_occurrences') .
            ' o ON o.id_appointments = a.id JOIN ' . t('appointments') . ' f ON f.hash = ? JOIN ' . t('appointment_series_occurrences') .
            ' fo ON fo.id_appointments = f.id AND fo.id_appointment_series = o.id_appointment_series ORDER BY a.start_datetime',
        [$response['json']['appointment_hash']],
    );

    check(
        array_column($rows, 'start_datetime') === [
            $thursday->format('Y-m-d') . ' 13:00:00',
            $thursday->modify('+2 weeks')->format('Y-m-d') . ' 13:00:00',
        ],
        'expected the first and third Thursday',
        $rows,
    );
    check(count(array_unique(array_column($rows, 'id_users_customer'))) === 1, 'same customer', $rows);

    $confirmation = $customer->get('booking_confirmation/of/' . $response['json']['appointment_hash']);

    check(str_contains($confirmation['body'], 'ra-booking-result'), 'confirmation list missing');
});

test('customer requests are limited: unknown pattern ignored, count capped at the maximum', function () use ($base_url, $thursday) {
    set_setting('booking_max', '2');

    $tampered = book_as_customer(new Client($base_url), $thursday->format('Y-m-d') . ' 09:00:00', ['pattern' => 'hourly', 'count' => 50]);
    check(!empty($tampered['json']['appointment_hash']) && !isset($tampered['json']['recurring']), 'unknown pattern', $tampered['json'] ?? $tampered['body']);

    $capped = book_as_customer(new Client($base_url), $thursday->format('Y-m-d') . ' 10:00:00', ['pattern' => 'weekly', 'count' => 50]);
    check(($capped['json']['recurring']['booked'] ?? 0) + ($capped['json']['recurring']['skipped'] ?? 0) === 2, 'count should be capped at 2', $capped['json'] ?? $capped['body']);

    set_setting('booking_max', null);
});

test('rescheduling (manage mode) never adds a repeat', function () use ($base_url, $thursday) {
    // A manage mode request without a valid appointment is refused by the core; the extension must not act either way.
    $response = book_as_customer(new Client($base_url), $thursday->format('Y-m-d') . ' 11:00:00', ['pattern' => 'weekly', 'count' => 3], true);

    check(!isset($response['json']['recurring']), 'manage mode extended', $response['json'] ?? $response['body']);

    set_setting('booking_enabled', null);
});

echo "\nDaily, yearly and the maximum\n";
reset_rate_limit();

test('every day and every year', function () use ($admin, $base) {
    $daily = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'daily', 'count' => 5],
    ]);

    $dates = array_column($daily['json']['occurrences'] ?? [], 'date');
    check(count($dates) === 5 && $dates[1] === (new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d'), 'daily', $daily['json'] ?? $daily['body']);

    // The weekend days are reported as days off, so nothing is booked silently.
    $weekend = array_filter($daily['json']['occurrences'], fn($o) => in_array((new DateTimeImmutable($o['date']))->format('N'), ['6', '7'], true));
    check(count($weekend) === 2 && array_column(array_merge(...array_column($weekend, 'conflicts')), 'key') === ['conflict_day_off', 'conflict_day_off'], 'weekend conflicts', $weekend);

    $yearly = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'yearly', 'count' => 3],
    ]);

    check(
        array_column($yearly['json']['occurrences'] ?? [], 'date') === [
            $base,
            (new DateTimeImmutable($base))->modify('+1 year')->format('Y-m-d'),
            (new DateTimeImmutable($base))->modify('+2 years')->format('Y-m-d'),
        ],
        'yearly',
        $yearly['json'] ?? $yearly['body'],
    );
});

test('the maximum per series follows the setting', function () use ($admin, $base) {
    set_setting('max_occurrences', '5');

    $response = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'daily', 'count' => 6],
    ]);

    set_setting('max_occurrences', null);

    check(($response['json']['success'] ?? true) === false && str_contains($response['json']['message'] ?? '', '5'), 'refused with the maximum in the message', $response['json'] ?? $response['body']);
});

echo "\nCustomers: weekdays, end date, any provider\n";

test('customer picks two weekdays and an end date', function () use ($base_url, $thursday) {
    set_setting('booking_enabled', '1');

    // Thursday + Friday at 16:00 (a free time in the tests above), until the Friday a week later. Dates stay within
    // the default future booking limit of 90 days.
    $first = $thursday->modify('+1 week');
    $until = $first->modify('+8 days')->format('Y-m-d');

    $response = book_as_customer(new Client($base_url), $first->format('Y-m-d') . ' 16:00:00', [
        'pattern' => 'weekly',
        'weekdays' => [5],
        'until' => $until,
    ]);

    $rows = q(
        'SELECT a.start_datetime FROM ' . t('appointments') . ' a JOIN ' . t('appointment_series_occurrences') . ' o ON o.id_appointments = a.id' .
            ' WHERE o.id_appointment_series = (SELECT fo.id_appointment_series FROM ' . t('appointment_series_occurrences') . ' fo JOIN ' . t('appointments') .
            ' f ON f.id = fo.id_appointments WHERE f.hash = ?) ORDER BY a.start_datetime',
        [$response['json']['appointment_hash'] ?? ''],
    );

    $weekdays = array_map(fn($row) => (new DateTimeImmutable($row['start_datetime']))->format('D'), $rows);
    check($weekdays === ['Thu', 'Fri', 'Thu', 'Fri'], 'Thu, Fri, Thu, Fri', $rows);
});

test('an end date asking for more than the maximum is capped and reported', function () use ($base_url, $thursday) {
    set_setting('booking_max', '3');

    $first = $thursday;

    // 15:30 is free in the tests above on this and the next Thursdays.
    $response = book_as_customer(new Client($base_url), $first->format('Y-m-d') . ' 15:30:00', [
        'pattern' => 'weekly',
        'until' => $first->modify('+10 weeks')->format('Y-m-d'),
    ]);

    set_setting('booking_max', null);

    check(($response['json']['recurring']['booked'] ?? 0) === 3, 'three booked', $response['json'] ?? $response['body']);
});

test('customer books every day: the day off is skipped', function () use ($base_url, $thursday) {
    // Thursday, Friday, Saturday (not a working day) at 09:30.
    $response = book_as_customer(new Client($base_url), $thursday->modify('+3 weeks')->format('Y-m-d') . ' 09:30:00', [
        'pattern' => 'daily',
        'count' => 3,
    ]);

    check(($response['json']['recurring'] ?? null) === ['booked' => 2, 'skipped' => 1], 'Thu + Fri booked, Sat skipped', $response['json'] ?? $response['body']);
});

test('customer books every year: dates past the future booking limit are skipped', function () use ($base_url, $thursday) {
    $response = book_as_customer(new Client($base_url), $thursday->modify('+3 weeks')->format('Y-m-d') . ' 10:30:00', [
        'pattern' => 'yearly',
        'count' => 2,
    ]);

    check(($response['json']['recurring'] ?? null) === ['booked' => 1, 'skipped' => 1], 'next year is past the 90 day limit', $response['json'] ?? $response['body']);
});

$second_provider_id = 0;

test('any provider: a taken date goes to another provider of the service', function () use ($admin, $base_url, $thursday, $working_plan, $service_id, &$second_provider_id, $admin_user, $admin_pass) {
    // A second provider through the core REST API, with the same working plan and service.
    $created = api_request($base_url, $admin_user, $admin_pass, 'POST', 'api/v1/providers', [
        'firstName' => 'Second',
        'lastName' => 'Provider',
        'email' => 'second.provider@example.org',
        'phone' => '+10000000077',
        'timezone' => 'UTC',
        'services' => [$service_id],
        'settings' => [
            'username' => 'ra-second-provider',
            'password' => 'ra-second-pass-1',
            'workingPlan' => json_decode($working_plan, true),
            'notifications' => false,
            'calendarView' => 'default',
        ],
    ]);

    check($created['status'] === 201, 'second provider not created', $created);
    $second_provider_id = (int) ($created['json']['id'] ?? 0);

    $first = $thursday->modify('+2 weeks');

    // The first provider is taken on the second Thursday.
    $admin->post('recurring_appointments/create', [
        'appointment_data' => appointment($first->modify('+1 week')->format('Y-m-d'), '14:00', '15:00'),
        'customer_data' => customer(),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
        'notify' => 'none',
    ]);

    $response = book_as_customer(new Client($base_url), $first->format('Y-m-d') . ' 14:00:00', ['pattern' => 'weekly', 'count' => 2], false, true);

    check(($response['json']['recurring'] ?? null) === ['booked' => 2, 'skipped' => 0], 'both booked', $response['json'] ?? $response['body']);

    $second = q('SELECT id_users_provider FROM ' . t('appointments') . ' WHERE start_datetime = ? AND id_users_provider = ?', [
        $first->modify('+1 week')->format('Y-m-d') . ' 14:00:00',
        $second_provider_id,
    ]);

    check(count($second) === 1, 'second date should go to the second provider');

    set_setting('booking_enabled', null);
});

echo "\nREST API\n";
reset_rate_limit();

test('API: create, list, show and delete a series', function () use ($base_url, $admin_user, $admin_pass, $provider_id, $service_id, $customer_id, $thursday) {
    $start = $thursday->modify('+12 weeks')->format('Y-m-d');

    $created = api_request($base_url, $admin_user, $admin_pass, 'POST', 'api/v1/appointment_series', [
        'start' => "{$start} 09:00:00",
        'end' => "{$start} 10:00:00",
        'serviceId' => $service_id,
        'providerId' => $provider_id,
        'customerId' => $customer_id,
        'notes' => 'api',
        'rule' => ['frequency' => 'weekly', 'count' => 3],
    ]);

    check($created['status'] === 201 && count($created['json']['occurrences'] ?? []) === 3, 'create', $created);

    $id = (int) $created['json']['id'];

    $list = api_request($base_url, $admin_user, $admin_pass, 'GET', 'api/v1/appointment_series?providerId=' . $provider_id);
    check(in_array($id, array_column($list['json'] ?? [], 'id'), true), 'list', $list);

    $show = api_request($base_url, $admin_user, $admin_pass, 'GET', "api/v1/appointment_series/{$id}");
    check(($show['json']['rule']['frequency'] ?? '') === 'weekly' && ($show['json']['occurrenceCount'] ?? 0) === 3, 'show', $show);

    $conflict = api_request($base_url, $admin_user, $admin_pass, 'POST', 'api/v1/appointment_series', [
        'start' => "{$start} 09:00:00",
        'end' => "{$start} 10:00:00",
        'serviceId' => $service_id,
        'providerId' => $provider_id,
        'customerId' => $customer_id,
        'rule' => ['frequency' => 'weekly', 'count' => 2],
    ]);
    check($conflict['status'] === 409, 'conflict answers 409', $conflict);

    $from = (new DateTimeImmutable($start))->modify('+1 week')->format('Y-m-d');
    $deleted = api_request($base_url, $admin_user, $admin_pass, 'DELETE', "api/v1/appointment_series/{$id}?from={$from}");
    check($deleted['status'] === 204, 'delete following', $deleted);

    $after = api_request($base_url, $admin_user, $admin_pass, 'GET', "api/v1/appointment_series/{$id}");
    check(($after['json']['occurrenceCount'] ?? 0) === 1, 'one left', $after);

    api_request($base_url, $admin_user, $admin_pass, 'DELETE', "api/v1/appointment_series/{$id}");
    $gone = api_request($base_url, $admin_user, $admin_pass, 'GET', "api/v1/appointment_series/{$id}");
    check($gone['status'] === 404, 'series gone', $gone);
});

test('API: change the time of a whole series, then move the following ones a day', function () use ($base_url, $admin_user, $admin_pass, $provider_id, $service_id, $customer_id, $thursday) {
    $start = $thursday->modify('+13 weeks')->format('Y-m-d');

    $created = api_request($base_url, $admin_user, $admin_pass, 'POST', 'api/v1/appointment_series', [
        'start' => "{$start} 10:00:00",
        'end' => "{$start} 11:00:00",
        'serviceId' => $service_id,
        'providerId' => $provider_id,
        'customerId' => $customer_id,
        'rule' => ['frequency' => 'weekly', 'count' => 3],
    ]);

    check($created['status'] === 201, 'create', $created);
    $id = (int) $created['json']['id'];

    // New time for all: 13:00, duration kept, notes changed.
    $changed = api_request($base_url, $admin_user, $admin_pass, 'PUT', "api/v1/appointment_series/{$id}", [
        'start' => "{$start} 13:00:00",
        'notes' => 'changed by API',
    ]);

    check($changed['status'] === 200, 'update all', $changed);
    check(
        array_map(fn($o) => substr($o['start'], 11) . '-' . substr($o['end'], 11), $changed['json']['occurrences'] ?? []) ===
            ['13:00:00-14:00:00', '13:00:00-14:00:00', '13:00:00-14:00:00'],
        'times',
        $changed['json'] ?? $changed['body'],
    );

    $notes = q('SELECT DISTINCT notes FROM ' . t('appointments') . ' WHERE id IN (' . implode(',', array_column($changed['json']['occurrences'], 'id')) . ')');
    check(array_column($notes, 'notes') === ['changed by API'], 'notes', $notes);

    // From the second date on, one day later (Thursday → Friday): those become a series of their own.
    $second = (new DateTimeImmutable($start))->modify('+1 week');

    $moved = api_request($base_url, $admin_user, $admin_pass, 'PUT', "api/v1/appointment_series/{$id}", [
        'from' => $second->format('Y-m-d'),
        'start' => $second->modify('+1 day')->format('Y-m-d') . ' 13:00:00',
    ]);

    check($moved['status'] === 200 && ($moved['json']['id'] ?? 0) !== $id, 'split into a new series', $moved);
    check(($moved['json']['rule']['weekdays'] ?? null) === [5] && count($moved['json']['occurrences'] ?? []) === 2, 'new series on Friday', $moved['json'] ?? $moved['body']);

    $old = api_request($base_url, $admin_user, $admin_pass, 'GET', "api/v1/appointment_series/{$id}");
    check(($old['json']['occurrenceCount'] ?? 0) === 1 && ($old['json']['rule']['weekdays'] ?? null) === [4], 'old series keeps its Thursday', $old['json'] ?? $old['body']);

    // A change into a conflict (the break) answers 409 and changes nothing.
    $conflict = api_request($base_url, $admin_user, $admin_pass, 'PUT', "api/v1/appointment_series/{$id}", ['start' => "{$start} 12:00:00"]);
    check($conflict['status'] === 409, 'conflict answers 409', $conflict);
});

test('API: refused without credentials', function () use ($base_url) {
    $response = api_request($base_url, '', '', 'GET', 'api/v1/appointment_series');

    check($response['status'] === 401, 'expected 401, got ' . $response['status']);
});

echo "\nSecurity: SQL injection and XSS attempts\n";
reset_rate_limit();

test('SQL injection in IDs, dates and filters changes nothing', function () use ($admin, $base_url, $admin_user, $admin_pass, $base) {
    $count = fn() => (int) q('SELECT COUNT(*) AS c FROM ' . t('appointments'))[0]['c'];
    $series_count = fn() => (int) q('SELECT COUNT(*) AS c FROM ' . t('appointment_series'))[0]['c'];
    $before = [$count(), $series_count()];

    $markers = $admin->post('recurring_appointments/markers', [
        'appointment_ids' => ["1 OR 1=1", "1) OR (1=1", "0 UNION SELECT 1,2"],
    ]);
    check(($markers['json']['success'] ?? false) === true && (array) ($markers['json']['series'] ?? []) === [], 'markers', $markers['json'] ?? $markers['body']);

    $info = $admin->post('recurring_appointments/info', ['appointment_id' => "1 OR 1=1"]);
    check(($info['json']['success'] ?? null) !== true || ($info['json']['series'] ?? null) === null, 'info', $info['json'] ?? $info['body']);

    $preview = $admin->post('recurring_appointments/preview', [
        'appointment_data' => array_merge(appointment($base, '10:00', '11:00'), ['start_datetime' => "{$base} 10:00:00' OR '1'='1"]),
        'rule' => ['frequency' => 'weekly', 'count' => "2; DROP TABLE ea_appointments"],
    ]);
    check(($preview['json']['success'] ?? true) === false, 'preview refused', $preview['json'] ?? $preview['body']);

    $delete = $admin->post('recurring_appointments/delete', ['appointment_id' => "1; DELETE FROM ea_appointments", 'scope' => 'all']);
    check(($delete['json']['success'] ?? true) !== true, 'delete refused', $delete['json'] ?? $delete['body']);

    $list = api_request($base_url, $admin_user, $admin_pass, 'GET', 'api/v1/appointment_series?providerId=' . rawurlencode('1 OR 1=1'));
    check($list['status'] === 200 && is_array($list['json']), 'API list', $list);

    $series = q('SELECT id FROM ' . t('appointment_series') . ' ORDER BY id LIMIT 1');

    if ($series) {
        $id = (int) $series[0]['id'];
        $api_delete = api_request($base_url, $admin_user, $admin_pass, 'DELETE', "api/v1/appointment_series/{$id}?from=" . rawurlencode("2000-01-01' OR '1'='1"));
        check($api_delete['status'] !== 204, 'API delete with injected date refused', $api_delete);
    }

    $customer = book_as_customer(new Client($base_url), (new DateTimeImmutable($base))->modify('+1 day')->format('Y-m-d') . ' 16:30:00', [
        'pattern' => "weekly' OR '1'='1",
        'until' => "2030-01-01' OR '1'='1",
        'weekdays' => ["1 OR 1=1"],
        'count' => "3 OR 1=1",
    ]);
    check(!isset($customer['json']['recurring']), 'booking repeat with injection ignored', $customer['json'] ?? $customer['body']);

    // The customer booking above added exactly one appointment (the core one); nothing else changed.
    check([$count(), $series_count()] === [$before[0] + (empty($customer['json']['appointment_hash']) ? 0 : 1), $before[1]], 'row counts', [$before, $count(), $series_count()]);
    check(count(q("SHOW TABLES LIKE 'ea_appointments'")) === 1, 'appointments table still there');
});

test('script in settings and translations cannot break out of the page config', function () use ($admin) {
    $payload = '</script><script>window.raXss=1</script>"\'';
    set_setting('booking_notify', $payload);
    set_setting('max_occurrences', '5" autofocus onfocus="window.raXss=1');

    foreach (['calendar', 'booking_settings'] as $page) {
        $body = $admin->get($page)['body'];
        check(!str_contains($body, '<script>window.raXss=1</script>'), "{$page}: raw script tag in the page");
        check(!str_contains($body, 'onfocus="window.raXss'), "{$page}: raw attribute in the page");
    }

    set_setting('booking_notify', null);
    set_setting('max_occurrences', null);
});

test('a blocked period is reported, and script in its name is only sent as JSON data', function () use ($admin, $base, $base_url, $admin_user, $admin_pass) {
    $name = '<img src=x onerror=window.raXss=1>';

    // Directly in the database: the core API is rate limited, and this suite already calls it many times.
    q(
        'INSERT INTO ' . t('blocked_periods') . ' (name, start_datetime, end_datetime, notes, create_datetime, update_datetime) VALUES (?, ?, ?, ?, NOW(), NOW())',
        [$name, "{$base} 00:00:00", "{$base} 23:59:00", ''],
    );

    check(count(q('SELECT id FROM ' . t('blocked_periods') . ' WHERE name = ?', [$name])) === 1, 'blocked period not created');

    $preview = $admin->post('recurring_appointments/preview', [
        'appointment_data' => appointment($base, '10:00', '11:00'),
        'rule' => ['frequency' => 'weekly', 'count' => 1],
    ]);

    q('DELETE FROM ' . t('blocked_periods') . ' WHERE name = ?', [$name]);

    $conflicts = $preview['json']['occurrences'][0]['conflicts'] ?? [];
    $blocked = array_values(array_filter($conflicts, fn($c) => $c['key'] === 'conflict_blocked_period'))[0] ?? [];

    check(($blocked['params']['name'] ?? '') === $name, 'blocked period reported, name sent as data', $preview['json'] ?? $preview['body']);
    check(str_starts_with($preview['body'], '{'), 'JSON response, not HTML');
});

echo "\n{$passes} passed, {$failures} failed\n";

exit($failures ? 1 : 0);
