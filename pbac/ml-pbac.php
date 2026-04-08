<?php
/**
 * pbac/ml-pbac.php
 *
 * CLI helper to create a Permission-Based Access Control table for a project.
 * Usage:
 *  php pbac/ml-pbac.php <project_name>
 *  php pbac/ml-pbac.php        # prompts for table name
 */

function parseEnvFile(string $path): array
{
    $vars = [];
    if (!is_readable($path)) return $vars;
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

// Determine project name from CLI args (skip flags like --pbac or create)
$argv = $_SERVER['argv'] ?? [];
$args = array_slice($argv, 1);
$project = null;
foreach ($args as $a) {
    if (strpos($a, '-') === 0) continue;
    // skip common words if present
    if (in_array(strtolower($a), ['ml', 'create', 'add'], true)) continue;
    $project = $a;
    break;
}

if ($project === null) {
    fwrite(STDOUT, "To create a new Permission Based Access Control table for your project, please provide a table name.\n\nTable name: ");
    $line = trim(fgets(STDIN));
    $project = $line;
}

$project = trim($project ?? '');
if ($project === '') {
    fwrite(STDERR, "Error: no table name provided.\n");
    exit(1);
}

// Basic validation: allow alphanumeric and underscore only
$sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $project);
if ($sanitized === '') {
    fwrite(STDERR, "Error: invalid table name. Use letters, numbers or underscore only.\n");
    exit(1);
}
if ($sanitized !== $project) {
    fwrite(STDOUT, "Note: table name sanitized to '{$sanitized}'.\n");
}
$project = $sanitized;

$cwd = getcwd();
$envPath = $cwd . DIRECTORY_SEPARATOR . '.env';
$env = [];
if (file_exists($envPath)) $env = parseEnvFile($envPath);

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

// 1) Check server reachability / credentials by connecting without selecting DB
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

// 2) Connect to the userdb database
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

try {
    $pdo->exec($sql);
    fwrite(STDOUT, sprintf("%s has been successfully added to your database (%s)\n", $pbacTable, $dbname));
    exit(0);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'already exists') !== false) {
        fwrite(STDOUT, sprintf("%s already exists in database (%s)\n", $pbacTable, $dbname));
        exit(0);
    }
    fwrite(STDERR, "Failed to create table: " . $msg . "\n");
    exit(3);
}
