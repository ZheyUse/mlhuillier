<?php
/**
 * script/user-migrate.php
 *
 * Usage:
 *   php script/user-migrate.php -db <databasename>
 *   ml migrate -db <databasename>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the terminal.\n");
    exit(1);
}

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function parseEnvFile(string $path): array
{
    $vars = [];
    if (!is_readable($path)) {
        return $vars;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $vars;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\"'");
        $vars[$key] = $value;
    }

    return $vars;
}

function hasScaffoldRoot(string $path): bool
{
    return is_dir($path)
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php')
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php')
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'sidebar.php');
}

function resolveProjectRoot(): ?string
{
    $cwd = getcwd();
    if ($cwd === false) {
        return null;
    }

    $current = realpath($cwd);
    while (is_string($current) && $current !== '' && $current !== dirname($current)) {
        if (hasScaffoldRoot($current)) {
            return $current;
        }
        $current = dirname($current);
    }

    return hasScaffoldRoot($cwd) ? realpath($cwd) : null;
}

function usage(): void
{
    out('Usage: ml migrate -db <databasename>');
}

function parseTargetDb(array $argv): string
{
    $args = array_slice($argv, 1);
    $target = '';

    for ($i = 0; $i < count($args); $i++) {
        $arg = trim((string) $args[$i]);
        if ($arg === '' || in_array(strtolower($arg), ['ml', 'migrate'], true)) {
            continue;
        }

        if (strtolower($arg) === '-db') {
            $target = trim((string) ($args[$i + 1] ?? ''));
            break;
        }

        if (stripos($arg, '-db=') === 0) {
            $target = substr($arg, 4);
            break;
        }
    }

    $target = preg_replace('/[^A-Za-z0-9_]/', '', (string) $target) ?? '';
    return $target;
}

function tableExists(PDO $pdo, string $dbName, string $tableName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl');
    $stmt->execute(['db' => $dbName, 'tbl' => $tableName]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function scanProjectForConvertedTables(string $projectRoot): array
{
    $results = [];
    $scanFiles = [
        $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'login-handler.php',
        $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'pbac-session.php',
        $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'usercontroller.php',
    ];

    foreach ($scanFiles as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = (string) file_get_contents($file);
        if (preg_match_all('/([A-Za-z0-9_]+_(?:pbac|rbac))/', $raw, $m)) {
            foreach ($m[1] as $found) {
                $results[] = strtolower((string) $found);
            }
        }
    }

    return array_values(array_unique($results));
}

function detectConvertedTables(PDO $pdo, string $projectRoot, string $sourceDb, string $projectName): array
{
    $tables = scanProjectForConvertedTables($projectRoot);

    $projectCandidates = [
        strtolower($projectName . '_pbac'),
        strtolower($projectName . '_rbac'),
    ];

    foreach ($projectCandidates as $candidate) {
        $tables[] = $candidate;
    }

    $tables = array_values(array_unique(array_filter($tables, 'strlen')));
    $existing = [];
    foreach ($tables as $tbl) {
        if (tableExists($pdo, $sourceDb, $tbl)) {
            $existing[] = $tbl;
        }
    }

    return $existing;
}

function askConfirmation(string $prompt): bool
{
    fwrite(STDOUT, $prompt);
    $line = fgets(STDIN);
    if ($line === false) {
        return false;
    }
    return strtoupper(substr(trim($line), 0, 1)) === 'Y';
}

function resolveMigrationSql(string $fileName): ?string
{
    $root = dirname(__DIR__);
    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'userdb' . DIRECTORY_SEPARATOR . $fileName,
        $root . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . $fileName,
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function extractCreateTableSql(string $sqlFile, string $tableName): ?string
{
    $raw = (string) file_get_contents($sqlFile);
    if ($raw === '') {
        return null;
    }

    $pattern = '/CREATE TABLE\s+`' . preg_quote($tableName, '/') . '`\s*\(.*?\)\s*ENGINE=.*?;/is';
    if (!preg_match($pattern, $raw, $m)) {
        return null;
    }

    $create = trim((string) $m[0]);
    $create = preg_replace('/^CREATE TABLE\s+`' . preg_quote($tableName, '/') . '`/i', 'CREATE TABLE IF NOT EXISTS `' . $tableName . '`', $create) ?? $create;
    return $create;
}

function createTableFromSqlFile(PDO $pdo, string $targetDb, string $sqlFile, string $tableName): void
{
    $create = extractCreateTableSql($sqlFile, $tableName);
    if ($create === null) {
        throw new RuntimeException('Unable to extract CREATE TABLE for ' . $tableName . ' from ' . $sqlFile);
    }

    $pdo->exec('USE `' . $targetDb . '`');
    $pdo->exec($create);
}

function cloneTableStructure(PDO $pdo, string $sourceDb, string $targetDb, string $tableName): void
{
    $stmt = $pdo->query('SHOW CREATE TABLE `' . $sourceDb . '`.`' . $tableName . '`');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !isset($row['Create Table'])) {
        throw new RuntimeException('Cannot read source structure for ' . $sourceDb . '.' . $tableName);
    }

    $create = (string) $row['Create Table'];
    $create = preg_replace('/^CREATE TABLE\s+`[^`]+`/i', 'CREATE TABLE IF NOT EXISTS `' . $tableName . '`', $create) ?? $create;
    $create = rtrim($create, ';') . ';';

    $pdo->exec('USE `' . $targetDb . '`');
    $pdo->exec($create);
}

function copyTableData(PDO $pdo, string $sourceDb, string $targetDb, string $tableName): int
{
    $pdo->exec('DELETE FROM `' . $targetDb . '`.`' . $tableName . '`');
    return (int) $pdo->exec('INSERT INTO `' . $targetDb . '`.`' . $tableName . '` SELECT * FROM `' . $sourceDb . '`.`' . $tableName . '`');
}

function relativePath(string $basePath, string $fullPath): string
{
    $base = rtrim(str_replace('\\', '/', $basePath), '/');
    $full = str_replace('\\', '/', $fullPath);
    if (strpos($full, $base . '/') === 0) {
        return substr($full, strlen($base) + 1);
    }
    return $full;
}

function rewriteDbReferences(string $projectRoot, string $sourceDb, string $targetDb): array
{
    $skipDirs = ['vendor', 'node_modules', '.git', '.idea', '.vscode', 'tmp', 'uploads'];
    $allowedExt = ['php', 'env', 'sql', 'js', 'json', 'md', 'txt', 'ini', 'htaccess'];
    $skipRelativePrefixes = ['migration/userdb/'];
    $changed = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        if (strtolower($fileInfo->getBasename()) === '.env') {
            continue;
        }

        $relPath = str_replace('\\', '/', relativePath($projectRoot, $path));
        foreach ($skipRelativePrefixes as $prefix) {
            if (stripos($relPath, $prefix) === 0) {
                continue 2;
            }
        }

        foreach ($skipDirs as $skip) {
            $needle = DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR;
            if (strpos($path, $needle) !== false) {
                continue 2;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && !in_array($ext, $allowedExt, true)) {
            continue;
        }

        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            continue;
        }

        $pattern = '/\b' . preg_quote($sourceDb, '/') . '\b/i';
        $updated = preg_replace($pattern, $targetDb, $raw, -1, $count);
        if ($updated === null || $count === 0) {
            continue;
        }

        if (@file_put_contents($path, $updated) !== false) {
            $changed[] = $relPath;
        }
    }

    sort($changed);
    return $changed;
}

function updateProjectEnv(string $projectRoot, string $targetDb): bool
{
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return false;
    }

    $raw = (string) file_get_contents($envPath);
    if ($raw === '') {
        return false;
    }

    $lines = preg_split('/\r\n|\n|\r/', $raw);
    if (!is_array($lines)) {
        return false;
    }

    $updated = [];
    $dbDatabaseFound = false;
    $changed = false;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);

        if (stripos($trimmed, '# Authentication schema name') === 0) {
            $changed = true;
            continue;
        }

        if (preg_match('/^USERDB_[A-Z0-9_]*\s*=/i', $trimmed)) {
            $changed = true;
            continue;
        }

        if (preg_match('/^DB_DATABASE\s*=/i', $trimmed)) {
            $line = 'DB_DATABASE=' . $targetDb;
            $dbDatabaseFound = true;
            $changed = true;
        }

        $updated[] = $line;
    }

    if (!$dbDatabaseFound) {
        $updated[] = 'DB_DATABASE=' . $targetDb;
        $changed = true;
    }

    if (!$changed) {
        return false;
    }

    $newContent = rtrim(implode(PHP_EOL, $updated)) . PHP_EOL;
    return file_put_contents($envPath, $newContent) !== false;
}

function writeMigrationLog(
    string $projectRoot,
    string $sourceDb,
    string $targetDb,
    array $tableRows,
    array $rewrittenFiles,
    bool $envUpdated
): void {
    $logPath = $projectRoot . DIRECTORY_SEPARATOR . 'migration-log.md';

    $lines = [];
    $lines[] = '## Migration - ' . date('Y-m-d H:i:s');
    $lines[] = '';
    $lines[] = '- Source database: ' . $sourceDb;
    $lines[] = '- Target database: ' . $targetDb;
    $lines[] = '- .env updated: ' . ($envUpdated ? 'yes' : 'no');
    $lines[] = '';
    $lines[] = '### Copied table rows';
    if (count($tableRows) === 0) {
        $lines[] = '- none';
    } else {
        foreach ($tableRows as $table => $rows) {
            $lines[] = '- ' . $table . ': ' . (string) $rows;
        }
    }
    $lines[] = '';
    $lines[] = '### Rewritten files';
    if (count($rewrittenFiles) === 0) {
        $lines[] = '- none';
    } else {
        foreach ($rewrittenFiles as $file) {
            $lines[] = '- ' . $file;
        }
    }
    $lines[] = '';

    file_put_contents($logPath, implode(PHP_EOL, $lines), FILE_APPEND);
}

$targetDb = parseTargetDb($argv);
if ($targetDb === '') {
    usage();
    exit(1);
}

$projectRoot = resolveProjectRoot();
if ($projectRoot === null) {
    err('Error: run this command inside a scaffolded project directory.');
    exit(2);
}

$projectName = basename($projectRoot);
$env = parseEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env');

$host = $env['USERDB_HOST'] ?? $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['USERDB_PORT'] ?? $env['DB_PORT'] ?? '3306';
$user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';
$charset = 'utf8mb4';
$sourceDb = $env['USERDB_NAME'] ?? 'userdb';

if (strtolower($targetDb) === strtolower($sourceDb)) {
    err('Target database must be different from source database.');
    exit(2);
}

$dsnServer = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);

try {
    $pdo = new PDO($dsnServer, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 8,
    ]);
} catch (Throwable $e) {
    err('Failed to connect to MySQL server: ' . $e->getMessage());
    exit(2);
}

if (!tableExists($pdo, $sourceDb, 'users') || !tableExists($pdo, $sourceDb, 'userlogs')) {
    err('Source tables ' . $sourceDb . '.users and ' . $sourceDb . '.userlogs must exist before migration.');
    exit(2);
}

$convertedTables = detectConvertedTables($pdo, $projectRoot, $sourceDb, (string) $projectName);
$hasConverted = count($convertedTables) > 0;

if (!$hasConverted) {
    $ok = askConfirmation('Warning: You are migrating to decentralize database without RBAC / PBAC Conversion, do you want to proceed? (Y/N): ');
} else {
    $convertedLabel = $convertedTables[0];
    $ok = askConfirmation('Warning: Attempting migration of userdb tables to ' . $targetDb . ' with its connected ' . $convertedLabel . ' converted project, do you want to proceed? (Y/N): ');
}

if (!$ok) {
    out('Migration cancelled.');
    exit(0);
}

try {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $targetDb . '`');

    $usersSql = resolveMigrationSql('userdb_users.sql');
    $logsSql = resolveMigrationSql('userdb_userlogs.sql');
    if ($usersSql === null || $logsSql === null) {
        throw new RuntimeException('Migration SQL files were not found.');
    }

    createTableFromSqlFile($pdo, $targetDb, $usersSql, 'users');
    createTableFromSqlFile($pdo, $targetDb, $logsSql, 'userlogs');

    foreach ($convertedTables as $tableName) {
        if (!tableExists($pdo, $sourceDb, $tableName)) {
            continue;
        }
        cloneTableStructure($pdo, $sourceDb, $targetDb, $tableName);
    }

    $tablesToCopy = ['users'];
    foreach ($convertedTables as $tableName) {
        $tablesToCopy[] = $tableName;
    }
    $tablesToCopy[] = 'userlogs';
    $tablesToCopy = array_values(array_unique($tablesToCopy));

    $copiedRows = [];
    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($tablesToCopy as $tableName) {
            if (!tableExists($pdo, $sourceDb, $tableName) || !tableExists($pdo, $targetDb, $tableName)) {
                continue;
            }
            $copiedRows[$tableName] = copyTableData($pdo, $sourceDb, $targetDb, $tableName);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
    } catch (Throwable $copyError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $copyError;
    }

    $rewrittenFiles = rewriteDbReferences($projectRoot, $sourceDb, $targetDb);
    $envUpdated = updateProjectEnv($projectRoot, $targetDb);
    $logFiles = $rewrittenFiles;
    if ($envUpdated) {
        $logFiles[] = '.env';
        sort($logFiles);
    }

    writeMigrationLog($projectRoot, $sourceDb, $targetDb, $copiedRows, $logFiles, $envUpdated);

    out('Migration complete.');
    out('Migrated structure and data: ' . $sourceDb . '.users -> ' . $targetDb . '.users');
    out('Migrated structure and data: ' . $sourceDb . '.userlogs -> ' . $targetDb . '.userlogs');
    foreach ($convertedTables as $tableName) {
        out('Migrated structure and data: ' . $sourceDb . '.' . $tableName . ' -> ' . $targetDb . '.' . $tableName);
    }
    foreach ($copiedRows as $tableName => $rowCount) {
        out('Copied rows: ' . $tableName . ' = ' . (string) $rowCount);
    }
    out('Rewritten files: ' . (string) count($logFiles));
    out('Migration log: migration-log.md');
    exit(0);
} catch (Throwable $e) {
    err('Migration failed: ' . $e->getMessage());
    exit(3);
}
