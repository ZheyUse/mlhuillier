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
    out('Usage:');
    out('  ml migrate check [--json]  - Check migration compatibility (use --json for machine output)');
    out('  ml migrate -db <databasename>  - Migrate to target database');
    out('  ml migrate global         - Centralize back to userdb');
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

        if (strtolower($arg) === 'global') {
            return '__global__';
        }

        if (strtolower($arg) === 'check') {
            return '__check__';
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
    $roots = [];

    $envRoot = trim((string) getenv('ML_CLI_ROOT'));
    if ($envRoot !== '') {
        $roots[] = $envRoot;
    }

    $roots[] = dirname(__DIR__);
    $roots[] = 'C:\\ML CLI\\Tools';

    $cwd = getcwd();
    if (is_string($cwd) && $cwd !== '') {
        $current = $cwd;
        while ($current !== dirname($current)) {
            $roots[] = $current;
            $current = dirname($current);
        }
        $roots[] = $current;
    }

    $seen = [];
    foreach ($roots as $root) {
        $resolved = realpath($root);
        $base = is_string($resolved) ? $resolved : $root;
        $base = rtrim($base, "\\/");
        if ($base === '') {
            continue;
        }

        $key = strtolower($base);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $candidates = [
            $base . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'userdb' . DIRECTORY_SEPARATOR . $fileName,
            $base . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . $fileName,
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
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

function validateProjectFiles(string $projectRoot): array
{
    $issues = [];
    $warnings = [];

    $requiredFiles = [
        'src/config/env.php' => 'Environment config',
        'src/config/db.php' => 'Database config',
    ];
    foreach ($requiredFiles as $file => $label) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            $issues[] = "[FAIL] {$label} missing: {$file}";
        }
    }

    $optionalFiles = [
        'src/config/login-handler.php' => 'Login handler',
        'src/config/pbac-session.php' => 'PBAC session',
        'src/controllers/usercontroller.php' => 'User controller',
        '.env' => 'Environment file',
    ];
    foreach ($optionalFiles as $file => $label) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            $warnings[] = "[WARN] {$label} missing: {$file}";
        }
    }

    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envPath)) {
        $env = parseEnvFile($envPath);
        $requiredEnvVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
        foreach ($requiredEnvVars as $var) {
            if (!isset($env[$var]) || $env[$var] === '') {
                $issues[] = "[FAIL] .env missing '{$var}' configuration";
            }
        }
    }

    return [
        'file_issues' => $issues,
        'file_warnings' => $warnings,
    ];
}

function checkDatabaseConflicts(PDO $pdo, string $targetDb, string $sourceDb, string $projectName): array
{
    $warnings = [];

    $stmt = $pdo->query("SHOW DATABASES LIKE '{$targetDb}'");
    if ($stmt->fetch()) {
        $warnings[] = "[WARN] Target database '{$targetDb}' already exists - will be reused";

        if (tableExists($pdo, $targetDb, 'users')) {
            $warnings[] = "[WARN] '{$targetDb}.users' exists - data will be preserved (INSERT)";
        }
    }

    $pattern = '%' . $projectName . '_%';
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$pattern}'");
    $similarDbs = [];
    while ($row = $stmt->fetch()) {
        $db = $row['Database'] ?? '';
        if (!empty($db) && strtolower($db) !== strtolower($targetDb)) {
            $similarDbs[] = $db;
        }
    }
    if (count($similarDbs) > 0) {
        $warnings[] = "[INFO] Related project databases found: " . implode(', ', $similarDbs);
    }

    return $warnings;
}

function checkCompatibility(PDO $pdo, string $projectRoot, string $projectName, array $env, string $outputFormat = 'text'): array
{
    $results = [
        'project' => $projectName,
        'timestamp' => date('c'),
        'source' => [],
        'converted' => [],
        'current' => [],
        'files' => [],
        'conflicts' => [],
        'issues' => [],
        'warnings' => [],
        'status' => 'unknown',
    ];

    $host = $env['USERDB_HOST'] ?? $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['USERDB_PORT'] ?? $env['DB_PORT'] ?? '3306';
    $sourceDb = $env['USERDB_NAME'] ?? 'userdb';
    $currentDb = $env['DB_DATABASE'] ?? '';

    $results['source'] = [
        'host' => $host,
        'port' => $port,
        'database' => $sourceDb,
    ];

    $stmt = $pdo->query("SHOW DATABASES LIKE '{$sourceDb}'");
    $results['source']['db_exists'] = $stmt->fetch() !== false;
    if (!$results['source']['db_exists']) {
        $results['issues'][] = "Source database '{$sourceDb}' does not exist";
    }

    $results['source']['tables'] = [];
    $requiredTables = ['users', 'userlogs'];
    foreach ($requiredTables as $table) {
        $exists = tableExists($pdo, $sourceDb, $table);
        $results['source']['tables'][$table] = $exists;
        if (!$exists) {
            $results['issues'][] = "Table '{$sourceDb}.{$table}' missing - migration requires this table";
        }
    }

    $convertedTables = detectConvertedTables($pdo, $projectRoot, $sourceDb, $projectName);
    $results['converted']['detected'] = $convertedTables;
    foreach ($convertedTables as $tbl) {
        $exists = tableExists($pdo, $sourceDb, $tbl);
        $results['converted']['tables'][$tbl] = $exists;
        if (!$exists) {
            $results['warnings'][] = "Referenced table '{$sourceDb}.{$tbl}' not found";
        }
    }

    if ($currentDb !== '') {
        $results['current']['database'] = $currentDb;
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$currentDb}'");
        $results['current']['db_exists'] = $stmt->fetch() !== false;
        if (!$results['current']['db_exists']) {
            $results['warnings'][] = "Project's configured DB '{$currentDb}' does not exist";
        }
    }

    $fileCheck = validateProjectFiles($projectRoot);
    $results['files'] = [
        'issues' => $fileCheck['file_issues'],
        'warnings' => $fileCheck['file_warnings'],
    ];
    $results['issues'] = array_merge($results['issues'], $fileCheck['file_issues']);
    $results['warnings'] = array_merge($results['warnings'], $fileCheck['file_warnings']);

    if (count($results['issues']) > 0) {
        $results['status'] = 'not_ready';
    } elseif (count($results['warnings']) > 0) {
        $results['status'] = 'ready_with_warnings';
    } else {
        $results['status'] = 'ready';
    }

    return $results;
}

function outputResult(array $results, string $format): void
{
    if ($format === 'json') {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    out('=== ML Migrate Check ===');
    out('Project: ' . $results['project']);
    out('Time: ' . $results['timestamp']);
    out('');

    out('--- Source Database ---');
    $src = $results['source'];
    out("Host: {$src['host']}:{$src['port']}");
    out("Database: {$src['database']}");
    out($src['db_exists'] ? '[OK] Database exists' : '[FAIL] Database missing');

    out('Required tables:');
    foreach ($src['tables'] ?? [] as $table => $exists) {
        out($exists ? "  [OK] {$table}" : "  [FAIL] {$table} missing");
    }

    out('');
    out('--- Converted Tables ---');
    if (count($results['converted']['detected'] ?? []) === 0) {
        out('[WARN] No converted RBAC/PBAC tables detected');
    } else {
        out('Detected: ' . implode(', ', $results['converted']['detected']));
        foreach ($results['converted']['tables'] ?? [] as $table => $exists) {
            out($exists ? "  [OK] {$table}" : "  [WARN] {$table} not found in source");
        }
    }

    out('');
    out('--- Current Configuration ---');
    if (isset($results['current']['database'])) {
        $curDb = $results['current']['database'];
        $status = $results['current']['db_exists'] ? 'exists' : 'missing';
        out("Database: {$curDb} ({$status})");
        if (strtolower($curDb) === strtolower($src['database'])) {
            out('[INFO] Using centralized userdb');
        }
    }

    out('');
    out('--- File Validation ---');
    foreach ($results['files']['issues'] ?? [] as $issue) {
        err($issue);
    }
    foreach ($results['files']['warnings'] ?? [] as $warn) {
        out($warn);
    }
    if (empty($results['files']['issues']) && empty($results['files']['warnings'])) {
        out('[OK] All critical files present');
    }

    out('');
    out('--- Summary ---');
    foreach ($results['warnings'] as $warn) {
        if (strpos($warn, '[WARN]') !== 0) {
            out('[WARN] ' . $warn);
        } else {
            out($warn);
        }
    }

    out('');
    $status = $results['status'];
    if ($status === 'not_ready') {
        out('Status: NOT READY FOR MIGRATION');
        out('Fix the issues above before running: ml migrate -db <target>');
    } elseif ($status === 'ready_with_warnings') {
        out('Status: READY WITH WARNINGS');
        out('You may proceed: ml migrate -db <target>');
    } else {
        out('Status: READY');
        out('All checks passed: ml migrate -db <target>');
    }
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

// ── Handle: ml migrate global (centralize back to userdb) ──────────────────
if ($targetDb === '__global__') {
    $currentDb = $env['DB_DATABASE'] ?? '';

    if ($currentDb === '' || strtolower($currentDb) === strtolower($sourceDb)) {
        err('Project is already using the centralized userdb. Nothing to centralize.');
        exit(2);
    }

    $confirmMsg = 'WARNING: This will rewrite all references from \'' . $currentDb . '\' back to \'' . $sourceDb . '\' in your project files, and update .env.' . PHP_EOL
        . 'Project: ' . $projectName . PHP_EOL
        . 'Do you want to proceed? (Y/N): ';

    if (!askConfirmation($confirmMsg)) {
        out('Centralization cancelled.');
        exit(0);
    }

    // Rewrite project references back to userdb
    $rewrittenFiles = rewriteDbReferences($projectRoot, $currentDb, $sourceDb);

    // Restore .env back to userdb
    $updatedLines = [];
    $rawEnv = is_file($projectRoot . DIRECTORY_SEPARATOR . '.env')
        ? (string) file_get_contents($projectRoot . DIRECTORY_SEPARATOR . '.env')
        : '';
    $envChanged = false;

    foreach (preg_split('/\r\n|\n|\r/', $rawEnv) as $line) {
        $trimmed = trim((string) $line);
        if (preg_match('/^DB_DATABASE\s*=/i', $trimmed)) {
            $updatedLines[] = 'DB_DATABASE=' . $sourceDb;
            $envChanged = true;
        } else {
            $updatedLines[] = $line;
        }
    }

    if ($envChanged && file_put_contents($projectRoot . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, $updatedLines) . PHP_EOL) === false) {
        err('Failed to update .env file.');
        exit(3);
    }

    $logFiles = $rewrittenFiles;
    if ($envChanged) {
        $logFiles[] = '.env';
        sort($logFiles);
    }

    writeMigrationLog($projectRoot, $currentDb, $sourceDb, [], $logFiles, $envChanged);

    out('Project: ' . $projectName . ' userdb has been centralized.');
    exit(0);
}
// ── End global/centralize ───────────────────────────────────────────────────

// ── Handle: ml migrate check ────────────────────────────────────────────────
if ($targetDb === '__check__') {
    $projectRoot = resolveProjectRoot();
    if ($projectRoot === null) {
        err('Error: run this command inside a scaffolded project directory.');
        exit(2);
    }

    $projectName = basename($projectRoot);
    $env = parseEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env');

    $outputFormat = 'text';
    foreach (array_slice($argv, 1) as $arg) {
        if (strtolower($arg) === '--json') {
            $outputFormat = 'json';
            break;
        }
    }

    $host = $env['USERDB_HOST'] ?? $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['USERDB_PORT'] ?? $env['DB_PORT'] ?? '3306';
    $user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';

    try {
        $dsnServer = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdo = new PDO($dsnServer, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]);

        $results = checkCompatibility($pdo, $projectRoot, $projectName, $env, $outputFormat);
        outputResult($results, $outputFormat);

        exit($results['status'] === 'not_ready' ? 2 : 0);
    } catch (Throwable $e) {
        if ($outputFormat === 'json') {
            echo json_encode([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT) . PHP_EOL;
        } else {
            err('Connection failed: ' . $e->getMessage());
        }
        exit(2);
    }
}
// ── End check ──────────────────────────────────────────────────────────────

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
