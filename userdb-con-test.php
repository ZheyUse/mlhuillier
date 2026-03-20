<?php
/**
 * userdb-con-test.php
 *
 * Simple connection test for the `userdb` database.
 * - Reads credentials from a project .env (if present) using keys:
 *   USERDB_HOST, USERDB_NAME, USERDB_PORT, DB_USERNAME, DB_PASSWORD
 * - Falls back to common DB_* keys if USERDB_* are not available.
 * - Prints a clean result and exits with code 0 on success, 2 on failure.
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
$charset = 'utf8mb4';

function safeEcho($line)
{
    fwrite(STDOUT, $line . PHP_EOL);
}

safeEcho('UserDB Connection: Connecting...');

$dsnServer = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
$dsnDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
];

// 1) Try to connect to the server (without selecting a database) to check reachability/auth
try {
    $pdoServer = new PDO($dsnServer, $user, $pass, $options);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (int) $e->getCode();

    if (stripos($msg, 'Access denied') !== false || $code === 1045) {
        $reason = 'Authentication failed (incorrect username or password)';
    } elseif (stripos($msg, 'Connection refused') !== false || stripos($msg, 'No such file or directory') !== false || stripos($msg, 'SQLSTATE[HY000] [2002]') !== false) {
        $reason = 'Database server not reachable (host/port incorrect or server down)';
    } else {
        $reason = 'Database server error: ' . $msg;
    }

    safeEcho('UserDB Connection: FAILED');
    safeEcho('Cause: ' . $reason);
    safeEcho('Hint: Check .env or src/config/db.php for host/port and credentials; do not share credentials.');
    exit(2);
}

// 2) Check whether the database/schema exists
try {
    $stmt = $pdoServer->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db');
    $stmt->execute([':db' => $dbname]);
    $exists = (bool) $stmt->fetchColumn();
} catch (PDOException $e) {
    // If querying INFORMATION_SCHEMA fails (rare), fall back to attempting to connect to the DB directly
    $exists = null;
}

if ($exists === false) {
    safeEcho('UserDB Connection: FAILED');
    safeEcho('Cause: Database does not exist');
    safeEcho('Hint: Create the database or check `USERDB_NAME` in .env or src/config/db.php');
    exit(2);
}

// 3) If schema reported present (or we couldn't check), try connecting to the specific database to verify access
try {
    $pdo = new PDO($dsnDb, $user, $pass, $options);
    safeEcho('UserDB Connection: OK');
    exit(0);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (int) $e->getCode();

    if (stripos($msg, 'Access denied') !== false || $code === 1045) {
        if ($exists === true) {
            $reason = 'Database exists, but authentication failed (insufficient privileges for this database)';
        } else {
            $reason = 'Authentication failed (incorrect username or password)';
        }
    } elseif ($code === 1049 || stripos($msg, 'Unknown database') !== false) {
        $reason = 'Database does not exist';
    } elseif (stripos($msg, 'Connection refused') !== false || stripos($msg, 'No such file or directory') !== false || stripos($msg, 'SQLSTATE[HY000] [2002]') !== false) {
        $reason = 'Database server not reachable (host/port incorrect or server down)';
    } else {
        $reason = 'Database error: ' . $msg;
    }

    safeEcho('UserDB Connection: FAILED');
    safeEcho('Cause: ' . $reason);
    safeEcho('Hint: Check .env or src/config/db.php for connection values; do not share credentials.');
    exit(2);
}
