<?php
/**
 * userdb-import.php
 *
 * Imports the SQL dumps for `userdb` from local `migration/userdb` if present,
 * otherwise downloads the SQL files from the GitHub repository and imports them
 * into the MySQL server specified by the project's .env (or sensible defaults).
 *
 * Exit codes:
 * 0 = success
 * 1 = minor usage/error
 * 2 = critical failure (couldn't connect/import)
 */

function parseEnvFile(string $path): array
{
    $vars = [];
    if (!is_readable($path)) {
        return $vars;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        $val = trim($val, " \t\"'");
        $vars[$key] = $val;
    }
    return $vars;
}

$cwd = getcwd();
$envPath = $cwd . DIRECTORY_SEPARATOR . '.env';
$env = [];
if (file_exists($envPath)) {
    $env = parseEnvFile($envPath);
}

$host = $env['USERDB_HOST'] ?? $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['USERDB_PORT'] ?? $env['DB_PORT'] ?? '3306';
$dbname = $env['USERDB_NAME'] ?? $env['DB_DATABASE'] ?? 'userdb';
$user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';

function safeEcho($line)
{
    fwrite(STDOUT, $line . PHP_EOL);
}

safeEcho('UserDB Import: Starting...');

$localA = $cwd . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'userdb' . DIRECTORY_SEPARATOR . 'userdb_users.sql';
$localB = $cwd . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'userdb' . DIRECTORY_SEPARATOR . 'userdb_userlogs.sql';

$files = [];
if (is_readable($localA) && is_readable($localB)) {
    $files[] = $localA;
    $files[] = $localB;
    safeEcho('UserDB Import: Using local SQL files from migration/userdb');
} else {
    safeEcho('UserDB Import: Local SQL files not found; downloading from GitHub...');
    $base = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/migration/userdb';
    $tmpA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'userdb_users.sql';
    $tmpB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'userdb_userlogs.sql';

    $ok = true;
    $ch = curl_init($base . '/userdb_users.sql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $data = curl_exec($ch);
    if ($data === false) { $ok = false; }
    else { file_put_contents($tmpA, $data); }
    curl_close($ch);

    $ch = curl_init($base . '/userdb_userlogs.sql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $data = curl_exec($ch);
    if ($data === false) { $ok = false; }
    else { file_put_contents($tmpB, $data); }
    curl_close($ch);

    if (! $ok) {
        safeEcho('UserDB Import: Failed to download SQL files from GitHub.');
        exit(2);
    }
    $files[] = $tmpA;
    $files[] = $tmpB;
}

// Connect to server (no DB) using mysqli so we can run multi-query SQL dumps
$mysqli = @new mysqli($host, $user, $pass, '', (int)$port);
if ($mysqli->connect_errno) {
    safeEcho('UserDB Import: Failed to connect to MySQL server: ' . $mysqli->connect_error);
    exit(2);
}

foreach ($files as $f) {
    safeEcho('UserDB Import: Importing ' . basename($f) . ' ...');
    $sql = file_get_contents($f);
    if ($sql === false) {
        safeEcho('UserDB Import: Failed to read ' . $f);
        exit(2);
    }

    if (! $mysqli->multi_query($sql)) {
        safeEcho('UserDB Import: Import failed: ' . $mysqli->error);
        // flush any results
        while ($mysqli->more_results() && $mysqli->next_result()) { }
        $mysqli->close();
        exit(2);
    }

    // consume and ignore results to ensure full execution
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
}

$mysqli->close();
safeEcho('UserDB Import: Completed successfully.');
exit(0);
