<?php

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension installer
 *
 * Usage:
 *   php install.php /path/to/easyappointments [--no-db] [--dry-run]
 *   php install.php /path/to/easyappointments --uninstall [--keep-db] [--dry-run]
 *
 * Install copies everything under src/ into the installation, adds one guarded include line to
 * application/config/hooks.php (the pages) and one to application/config/routes.php (the REST API), and creates the
 * database tables. It is safe to run again, e.g. after an Easy!Appointments update or to upgrade the extension.
 *
 * Uninstall removes those lines and the copied files, and drops the tables and settings unless --keep-db is given.
 * The appointments themselves always stay; they become regular single appointments.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Core config file => the extension file it includes. Each include is guarded by is_file(), so removing the
 * extension's files never breaks the core config.
 */
const CONFIG_INCLUDES = [
    'application/config/hooks.php' => 'recurring_appointments_hooks.php',
    'application/config/routes.php' => 'recurring_appointments_routes.php',
];

const INCLUDE_COMMENT = '// Recurring appointments extension (remove these lines to disable it).';

function include_lines(string $file): array
{
    return [
        '',
        INCLUDE_COMMENT,
        "if (is_file(APPPATH . 'config/{$file}')) { include APPPATH . 'config/{$file}'; }",
    ];
}

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$arguments = array_slice($argv, 1);
$flags = array_values(array_filter($arguments, fn($argument) => str_starts_with($argument, '--')));
$positional = array_values(array_filter($arguments, fn($argument) => !str_starts_with($argument, '--')));

$unknown = array_diff($flags, ['--no-db', '--dry-run', '--uninstall', '--keep-db', '--help']);

if (empty($positional) || in_array('--help', $flags, true) || $unknown) {
    fwrite(
        STDERR,
        "Usage:\n" .
            "  php install.php /path/to/easyappointments [--no-db] [--dry-run]\n" .
            "  php install.php /path/to/easyappointments --uninstall [--keep-db] [--dry-run]\n",
    );
    exit($unknown ? 1 : (empty($positional) ? 1 : 0));
}

$target = rtrim(str_replace('\\', '/', $positional[0]), '/');
$source = str_replace('\\', '/', __DIR__) . '/src';
$dry_run = in_array('--dry-run', $flags, true);

foreach (['index.php', 'application/config/hooks.php', 'application/controllers/Calendar.php'] as $required) {
    if (!is_file($target . '/' . $required)) {
        fwrite(STDERR, "Not an Easy!Appointments installation (missing {$required}): {$target}\n");
        exit(1);
    }
}

$version = detect_version($target);

echo 'Easy!Appointments ' . ($version ?? 'version unknown') . " at {$target}\n";

if ($version !== null && !preg_match('/^1\.[56]\./', $version)) {
    echo "  Warning: this extension is built for Easy!Appointments 1.6 (and compatible with 1.5); continuing.\n";
}

$files = source_files($source);

if (in_array('--uninstall', $flags, true)) {
    uninstall($target, $files, in_array('--keep-db', $flags, true), $dry_run);
} else {
    install($target, $source, $files, in_array('--no-db', $flags, true), $dry_run);
}

echo $dry_run ? "Dry run, nothing was changed.\n" : "Done.\n";

function install(string $target, string $source, array $files, bool $no_db, bool $dry_run): void
{
    echo "Copying files\n";

    foreach ($files as $file) {
        echo "  {$file}\n";

        if ($dry_run) {
            continue;
        }

        $destination = $target . '/' . $file;

        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true)) {
            fail("Could not create the folder of {$file}.");
        }

        if (!copy($source . '/' . $file, $destination)) {
            fail("Could not copy {$file}. Check the file permissions of the installation.");
        }
    }

    foreach (CONFIG_INCLUDES as $config_file => $included) {
        $path = $target . '/' . $config_file;

        if (!is_file($path)) {
            echo "  Skipped {$config_file}: not found\n";
            continue;
        }

        $content = file_get_contents($path);

        if (str_contains($content, $included)) {
            echo "Already registered in {$config_file}\n";
            continue;
        }

        echo "Registering in {$config_file}\n";

        if (!$dry_run) {
            $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
            $content = rtrim($content) . $eol . implode($eol, include_lines($included)) . $eol;

            if (file_put_contents($path, $content) === false) {
                fail("Could not write {$config_file}.");
            }
        }
    }

    if ($no_db) {
        echo "Skipped the database step. Create the tables with \"php index.php recurring_appointments install\",\n" .
            "or log in as admin and use the \"Set up now\" button on the calendar page.\n";

        return;
    }

    echo "Creating the database tables\n";

    if (!$dry_run && !run_index($target, 'install')) {
        echo "  Could not create the tables from here (database not reachable from this shell?).\n" .
            "  Log in as admin and use the \"Set up now\" button on the calendar page instead,\n" .
            "  or run sql/install.sql in your database.\n";
    }
}

function uninstall(string $target, array $files, bool $keep_db, bool $dry_run): void
{
    if (!$keep_db && is_file($target . '/application/controllers/Recurring_appointments.php')) {
        echo "Dropping the database tables\n";

        if (!$dry_run && !run_index($target, 'uninstall')) {
            echo "  Could not drop the tables from here; run sql/uninstall.sql in your database if you want them gone.\n";
        }
    }

    foreach (CONFIG_INCLUDES as $config_file => $included) {
        $path = $target . '/' . $config_file;
        $content = is_file($path) ? file_get_contents($path) : '';

        if (!str_contains($content, $included)) {
            continue;
        }

        echo "Removing the line from {$config_file}\n";

        $lines = preg_split('/\r\n|\n/', $content);
        $kept = array_filter(
            $lines,
            fn($line) => !str_contains($line, $included) && !str_contains($line, INCLUDE_COMMENT),
        );

        if (!$dry_run) {
            $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
            file_put_contents($path, rtrim(implode($eol, $kept)) . $eol);
        }
    }

    echo "Removing files\n";

    foreach ($files as $file) {
        $path = $target . '/' . $file;

        if (is_file($path)) {
            echo "  {$file}\n";

            if (!$dry_run) {
                unlink($path);
            }
        }
    }
}

/**
 * All files under src/, relative and with forward slashes.
 */
function source_files(string $source): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $files[] = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($source)), '/');
    }

    sort($files);

    return $files;
}

function detect_version(string $target): ?string
{
    $app_config = @file_get_contents($target . '/application/config/app.php');

    if ($app_config && preg_match("/\\\$config\\['version'\\]\\s*=\\s*'([^']+)'/", $app_config, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Run "php index.php recurring_appointments <command>" inside the installation.
 */
function run_index(string $target, string $command): bool
{
    $process = proc_open(
        [PHP_BINARY, 'index.php', 'recurring_appointments', $command],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $target,
    );

    if (!is_resource($process)) {
        return false;
    }

    $output = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
    $exit_code = proc_close($process);

    if ($output !== '') {
        echo '  ' . str_replace("\n", "\n  ", $output) . "\n";
    }

    return $exit_code === 0 && !preg_match('/error|exception|denied/i', $output);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
