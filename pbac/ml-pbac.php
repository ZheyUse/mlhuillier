<?php
/**
 * pbac/ml-pbac.php
 *
 * CLI helper to create a Permission-Based Access Control table for a project
 * and apply PBAC scaffold files into the generated project.
 *
 * Usage:
 *   php pbac/ml-pbac.php <project_name>
 *   php pbac/ml-pbac.php            # prompts for table name
 */

declare(strict_types=1);

function parseEnvFile(string $path): array
{
    $vars = [];
    if (!is_readable($path)) {
        return $vars;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        $val = trim($val, " \t\"'");
        $vars[$key] = $val;
    }

    return $vars;
}

function downloadRemotePhp(string $url, string $destPath): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "User-Agent: ml-cli\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || trim((string) $raw) === '') {
        return false;
    }

    return file_put_contents($destPath, (string) $raw) !== false;
}

function askProceedConfirmation(bool $assumeYes = false): bool
{
    if ($assumeYes) {
        return true;
    }

    fwrite(STDOUT, "Permission Base Access Control works best for a newly generated project using ml create <project_name>\n");
    fwrite(STDOUT, "Are you sure you want to continue (Y/N): ");

    $line = fgets(STDIN);
    if ($line === false) {
        return false;
    }

    $answer = strtoupper(substr(trim($line), 0, 1));
    return $answer === 'Y';
}

function applyRemotePbacScaffold(string $projectName): int
{
    $cacheBust = (string) (random_int(100000, 999999) . random_int(100000, 999999));
    $url = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/package/pbac/pbac-application.php?t=' . $cacheBust;
    $tmpFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ml-pbac-application.php';

    if (!downloadRemotePhp($url, $tmpFile)) {
        fwrite(STDERR, "Warning: Failed to fetch remote PBAC application script.\n");
        return 2;
    }

    try {
        require $tmpFile;

        if (!function_exists('applyPbacScaffold')) {
            fwrite(STDERR, "Warning: Remote PBAC application script is invalid.\n");
            return 2;
        }

        $result = applyPbacScaffold($projectName, false);
        $report = isset($result['report']) && is_array($result['report']) ? $result['report'] : [];
        foreach ($report as $line) {
            fwrite(STDOUT, (string) $line . PHP_EOL);
        }

        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, "PBAC scaffold failed: " . (string) ($result['message'] ?? 'Unknown error') . "\n");
            return 2;
        }

        fwrite(STDOUT, "PBAC scaffold successfully applied.\n");
        return 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "Warning: PBAC scaffold exception: " . $e->getMessage() . "\n");
        return 2;
    } finally {
        @unlink($tmpFile);
    }
}

$argv = $_SERVER['argv'] ?? [];
$args = array_slice($argv, 1);

$assumeYes = false;
$project = null;

foreach ($args as $arg) {
    $lower = strtolower((string) $arg);
    if ($lower === '--yes' || $lower === '-y') {
        $assumeYes = true;
        continue;
    }
    if (strpos($arg, '-') === 0) {
        continue;
    }
    if (in_array($lower, ['ml', 'create', 'add'], true)) {
        continue;
    }
    $project = $arg;
    break;
}

if ((getenv('ML_PBAC_ASSUME_Y') ?: '') === '1') {
    $assumeYes = true;
}

if ($project === null) {
    fwrite(STDOUT, "To create a new Permission Based Access Control table for your project, please provide a table name.\n\nTable name: ");
    $line = trim((string) fgets(STDIN));
    $project = $line;
}

$project = trim((string) $project);
if ($project === '') {
    fwrite(STDERR, "Error: no table name provided.\n");
    exit(1);
}

$sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $project);
if ($sanitized === '') {
    fwrite(STDERR, "Error: invalid table name. Use letters, numbers or underscore only.\n");
    exit(1);
}
if ($sanitized !== $project) {
    fwrite(STDOUT, "Note: table name sanitized to '{$sanitized}'.\n");
}
$project = $sanitized;

if (!askProceedConfirmation($assumeYes)) {
    fwrite(STDOUT, "PBAC creation cancelled.\n");
    exit(0);
}

$cwd = getcwd() ?: '.';
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

$pbacTable = $project . '_pbac';
$pk = $project . '_no';
$fkName = 'fk_' . $project . '_pbac_user';

$dsnServer = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
$dsnDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
];

try {
    $pdoServer = new PDO($dsnServer, $user, $pass, $options);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (int) $e->getCode();
    if (stripos($msg, 'Access denied') !== false || $code === 1045) {
        fwrite(STDERR, "Invalid database credentials\n");
        exit(2);
    }
    if (stripos($msg, 'Connection refused') !== false || stripos($msg, 'No such file or directory') !== false || stripos($msg, 'SQLSTATE[HY000] [2002]') !== false) {
        fwrite(STDERR, "Cannot connect to the server\n");
        exit(2);
    }
    fwrite(STDERR, "Cannot connect to the server\n");
    exit(2);
}

try {
    $pdo = new PDO($dsnDb, $user, $pass, $options);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (int) $e->getCode();
    if ($code === 1049 || stripos($msg, 'Unknown database') !== false) {
        fwrite(STDERR, "Cannot connect to the database\n");
        exit(2);
    }
    if (stripos($msg, 'Access denied') !== false || $code === 1045) {
        fwrite(STDERR, "Invalid database credentials\n");
        exit(2);
    }
    fwrite(STDERR, "Cannot connect to the database\n");
    exit(2);
}

$sql = sprintf(
    "CREATE TABLE `%s` (
  `%s` int NOT NULL AUTO_INCREMENT,
  `id_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_level` int NOT NULL,
  `permissions` text,
  PRIMARY KEY (`%s`),
  KEY `%s` (`id_number`),
  CONSTRAINT `%s` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;",
    $pbacTable,
    $pk,
    $pk,
    $fkName,
    $fkName
);

$tableCreatedOrExists = false;

try {
    $pdo->exec($sql);
    fwrite(STDOUT, sprintf("%s has been successfully added to your database (%s)\n", $pbacTable, $dbname));
    $tableCreatedOrExists = true;
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'already exists') !== false) {
        fwrite(STDOUT, sprintf("%s already exists in database (%s)\n", $pbacTable, $dbname));
        $tableCreatedOrExists = true;
    } else {
        fwrite(STDERR, "Failed to create table: " . $msg . "\n");
        exit(3);
    }
}

if ($tableCreatedOrExists) {
    fwrite(STDOUT, "Applying PBAC scaffold files to project...\n");
    $scaffoldRc = applyRemotePbacScaffold($project);
    if ($scaffoldRc !== 0) {
        fwrite(STDERR, "PBAC table was created, but scaffold application did not fully complete.\n");
        exit(4);
    }
}

exit(0);
