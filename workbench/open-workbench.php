<?php
/**
 * open-workbench.php
 *
 * Behaviors:
 * - `ml wb` -> launch MySQL Workbench
 * - `ml wb --export` -> interactive export flow
 * - `ml wb --export : db, table1, table2 = option` -> direct mode + filename prompt
 */

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
    fwrite(STDOUT, "This helper currently supports Windows only.\n");
    exit(2);
}

$args = $argv;
array_shift($args); // script path
if (!empty($args) && strtolower((string) $args[0]) === 'wb') {
    array_shift($args);
}

$isExport = false;
$exportExpr = '';
for ($i = 0; $i < count($args); $i++) {
    $a = (string) $args[$i];
    if (strtolower($a) === '--export') {
        $isExport = true;
        $tail = array_slice($args, $i + 1);
        $exportExpr = trim(implode(' ', $tail));
        break;
    }
}

if (!$isExport) {
    launchWorkbench();
    exit(0);
}

$database = '';
$tables = [];
$method = '';

if ($exportExpr !== '') {
    [$ok, $database, $tables, $method] = parseDirectExport($exportExpr);
    if (!$ok) {
        fwrite(STDOUT, "Error: Invalid option, please try again\n");
        exit(2);
    }

    if (!in_array($method, ['1', '2', '3', '4'], true)) {
        fwrite(STDOUT, "Error: Invalid option, please try again\n");
        exit(2);
    }
}

$connCfg = getConnectionConfig();
$mysqli = @new mysqli(
    $connCfg['host'],
    $connCfg['user'],
    $connCfg['pass'],
    '',
    (int) $connCfg['port']
);
if ($mysqli->connect_errno) {
    fwrite(STDOUT, "Database connection failed.\n");
    fwrite(STDOUT, "Host: {$connCfg['host']}:{$connCfg['port']} User: {$connCfg['user']}\n");
    fwrite(STDOUT, "MySQL error: {$mysqli->connect_error}\n");
    fwrite(STDOUT, "Tip: configure credentials via ml create --config (writes C:\\ML CLI\\Tools\\mlcli-config.json)\n");
    exit(2);
}

if ($exportExpr !== '') {

    if (!databaseExists($mysqli, $database)) {
        fwrite(STDOUT, "{$database} cannot be found, please try again\n");
        exit(2);
    }

    $missing = firstMissingTable($mysqli, $database, $tables);
    if ($missing !== null) {
        fwrite(STDOUT, "{$missing} cannot be found, please select again\n");
        exit(2);
    }
} else {
    $database = promptUntilValidDatabase($mysqli);
    $tables = promptUntilValidTables($mysqli, $database);
    $method = promptUntilValidMethod();
}

$fileName = promptUntilValidFileName();

$dateFolder = date('m-d-Y');
$exportDir = 'C:\\ML CLI\\Exports\\' . $dateFolder;
if (!is_dir($exportDir) && !@mkdir($exportDir, 0777, true) && !is_dir($exportDir)) {
    fwrite(STDOUT, "Failed to create export folder: {$exportDir}\n");
    exit(2);
}

$outputPath = $exportDir . '\\' . $fileName . '.sql';
$mysqldump = findMysqldump($connCfg);
if ($mysqldump === null) {
    fwrite(STDOUT, "Could not find mysqldump.exe.\n");
    exit(2);
}

$dumpResult = runExport(
    $mysqldump,
    $connCfg,
    $database,
    $tables,
    $method,
    $outputPath
);

if (!$dumpResult['ok']) {
    fwrite(STDOUT, "Export failed.\n");
    if ($dumpResult['stderr'] !== '') {
        fwrite(STDOUT, $dumpResult['stderr'] . "\n");
    }
    exit(2);
}

fwrite(STDOUT, "{$fileName} has been exported successfully\n");
fwrite(STDOUT, "Location: {$outputPath}\n");

@pclose(@popen('cmd /c start "" explorer "' . str_replace('"', '', $exportDir) . '"', 'r'));
exit(0);

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return trim($line === false ? '' : $line);
}

function promptUntilValidDatabase(mysqli $mysqli): string
{
    while (true) {
        $db = prompt("Database: ");
        if ($db === '') continue;
        if (databaseExists($mysqli, $db)) {
            return $db;
        }
        fwrite(STDOUT, "{$db} cannot be found, please try again\n");
    }
}

function promptUntilValidTables(mysqli $mysqli, string $database): array
{
    $allTables = listTables($mysqli, $database);
    fwrite(STDOUT, "{$database} List of Tables:\n");
    $i = 1;
    foreach ($allTables as $t) {
        fwrite(STDOUT, $i . '. ' . $t . "\n");
        $i++;
    }

    while (true) {
        $input = prompt("Tables (comma-separated): ");
        $tables = array_values(array_filter(array_map('trim', explode(',', $input)), 'strlen'));
        if (count($tables) === 0) {
            fwrite(STDOUT, " cannot be found, please select again\n");
            continue;
        }
        $missing = firstMissingTable($mysqli, $database, $tables);
        if ($missing === null) {
            return $tables;
        }
        fwrite(STDOUT, "{$missing} cannot be found, please select again\n");
    }
}

function promptUntilValidMethod(): string
{
    fwrite(STDOUT, "Select export method:\n");
    fwrite(STDOUT, "1. Dump Structure Only\n");
    fwrite(STDOUT, "2. Dump Data Only\n");
    fwrite(STDOUT, "3. Dump Data and Structure\n");
    fwrite(STDOUT, "4. Full Export (Data + Structure + Schema)\n\n");

    while (true) {
        $m = prompt("Method: ");
        if (in_array($m, ['1', '2', '3', '4'], true)) {
            return $m;
        }
        fwrite(STDOUT, "Error: Invalid option, please try again\n");
    }
}

function promptUntilValidFileName(): string
{
    while (true) {
        $f = prompt("File Name: ");
        if (isValidFileName($f)) {
            return $f;
        }
        fwrite(STDOUT, "Error: Invalid filename, please try again\n");
    }
}

function isValidFileName(string $name): bool
{
    if ($name === '') return false;
    if (preg_match('/[<>:"\\\/|?*]/', $name)) return false;
    if (preg_match('/[\.\s]$/', $name)) return false;
    return true;
}

function parseDirectExport(string $expr): array
{
    $expr = trim($expr);
    if ($expr === '') return [false, '', [], ''];

    if ($expr[0] === ':') {
        $expr = trim(substr($expr, 1));
    }

    if (!preg_match('/^(.+?)\s*=\s*([1-4])\s*$/', $expr, $m)) {
        return [false, '', [], ''];
    }

    $left = trim($m[1]);
    $method = trim($m[2]);
    $parts = array_values(array_filter(array_map('trim', explode(',', $left)), 'strlen'));
    if (count($parts) < 2) {
        return [false, '', [], ''];
    }

    $db = array_shift($parts);
    return [true, $db, $parts, $method];
}

function databaseExists(mysqli $mysqli, string $database): bool
{
    $safe = $mysqli->real_escape_string($database);
    $sql = "SHOW DATABASES LIKE '{$safe}'";
    $res = $mysqli->query($sql);
    if (!$res) return false;
    $ok = $res->num_rows > 0;
    $res->free();
    return $ok;
}

function listTables(mysqli $mysqli, string $database): array
{
    $safeDb = str_replace('`', '``', $database);
    $res = $mysqli->query("SHOW TABLES FROM `{$safeDb}`");
    if (!$res) return [];
    $out = [];
    while ($row = $res->fetch_row()) {
        $out[] = (string) $row[0];
    }
    $res->free();
    return $out;
}

function firstMissingTable(mysqli $mysqli, string $database, array $tables): ?string
{
    if (count($tables) === 0) return '';

    $existing = listTables($mysqli, $database);
    $set = array_fill_keys($existing, true);
    foreach ($tables as $t) {
        if (!isset($set[$t])) return $t;
    }
    return null;
}

function getConnectionConfig(): array
{
    $cfgPath = 'C:\\ML CLI\\Tools\\mlcli-config.json';
    if (!is_file($cfgPath)) {
        fwrite(STDOUT, "Error: missing config file\n");
        fwrite(STDOUT, "Setup your config by running:\n");
        fwrite(STDOUT, "ml create --config\n");
        exit(2);
    }

    $raw = @file_get_contents($cfgPath);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json)) {
        fwrite(STDOUT, "Error: invalid config file format: {$cfgPath}\n");
        exit(2);
    }

    return [
        'host' => (string) ($json['host'] ?? '127.0.0.1'),
        'port' => (int) ($json['port'] ?? 3306),
        'user' => (string) ($json['user'] ?? 'root'),
        'pass' => (string) ($json['password'] ?? ''),
        'mysqldumpPath' => (string) ($json['mysqldumpPath'] ?? ''),
    ];
}

function findMysqldump(array $cfg = []): ?string
{
    $cfgPath = (string) ($cfg['mysqldumpPath'] ?? '');
    if ($cfgPath !== '' && file_exists($cfgPath)) {
        return $cfgPath;
    }

    $out = [];
    exec('where mysqldump.exe 2>NUL', $out, $rc);
    if ($rc === 0 && !empty($out) && file_exists($out[0])) {
        return $out[0];
    }

    $patterns = [
        'C:\\Program Files\\MySQL\\*\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL Workbench *\\mysqldump.exe',
        'C:\\Program Files (x86)\\MySQL\\*\\bin\\mysqldump.exe',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\xampp\\php\\mysqldump.exe',
        'C:\\Program Files\\MariaDB*\\*\\bin\\mysqldump.exe',
    ];
    foreach ($patterns as $p) {
        foreach (glob($p) as $m) {
            if (file_exists($m)) return $m;
        }
    }
    return null;
}

function runExport(string $mysqldump, array $cfg, string $db, array $tables, string $method, string $outputPath): array
{
    $tmpCnf = tempnam(sys_get_temp_dir(), 'mlwb_');
    if ($tmpCnf === false) {
        return ['ok' => false, 'stderr' => 'Unable to create temporary credentials file.'];
    }
    $tmpCnf .= '.cnf';
    $cnf = "[client]\n";
    $cnf .= "host={$cfg['host']}\n";
    $cnf .= "port=" . (int) $cfg['port'] . "\n";
    $cnf .= "user={$cfg['user']}\n";
    $cnf .= "password={$cfg['pass']}\n";
    file_put_contents($tmpCnf, $cnf);

    $parts = [];
    $parts[] = quoteCmd($mysqldump);
    $parts[] = '--defaults-extra-file=' . quoteCmd($tmpCnf);

    if ($method === '1') {
        $parts[] = '--no-data';
    } elseif ($method === '2') {
        $parts[] = '--no-create-info';
    } elseif ($method === '4') {
        $parts[] = '--routines';
        $parts[] = '--triggers';
        $parts[] = '--events';
    }

    $parts[] = quoteCmd($db);
    foreach ($tables as $t) {
        $parts[] = quoteCmd($t);
    }

    $cmd = implode(' ', $parts) . ' > ' . quoteCmd($outputPath) . ' 2>&1';
    $output = [];
    exec($cmd, $output, $rc);
    @unlink($tmpCnf);

    return [
        'ok' => $rc === 0,
        'stderr' => trim(implode("\n", $output)),
    ];
}

function quoteCmd(string $value): string
{
    return '"' . str_replace('"', '\\"', $value) . '"';
}

function launchWorkbench(): void
{
    $candidates = [];
    $env = getenv('MYSQL_WORKBENCH');
    if ($env) {
        $candidates[] = $env;
    }

    $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0\\MySQLWorkbench.exe';
    $candidates[] = 'C:\\Program Files (x86)\\MySQL\\MySQL Workbench 8.0\\MySQLWorkbench.exe';
    $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 6.3\\MySQLWorkbench.exe';
    $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0\\wb.exe';
    $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0 CE\\MySQLWorkbench.exe';
    $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0 CE\\wb.exe';

    $found = null;
    foreach ($candidates as $p) {
        if ($p && file_exists($p)) {
            $found = $p;
            break;
        }
    }

    if (!$found) {
        $patterns = [
            'C:\\Program Files\\MySQL Workbench*\\MySQLWorkbench.exe',
            'C:\\Program Files\\MySQL Workbench*\\wb.exe',
            'C:\\Program Files\\MySQL\\MySQL Workbench*\\MySQLWorkbench.exe',
            'C:\\Program Files\\MySQL\\MySQL Workbench*\\wb.exe',
            'C:\\Program Files (x86)\\MySQL Workbench*\\MySQLWorkbench.exe',
            'C:\\Program Files (x86)\\MySQL\\MySQL Workbench*\\MySQLWorkbench.exe',
            'C:\\Program Files\\MySQL\\MySQLWorkbench.exe',
            'C:\\Program Files (x86)\\MySQL\\MySQLWorkbench.exe',
            'C:\\ProgramData\\chocolatey\\bin\\MySQLWorkbench.exe',
        ];
        foreach ($patterns as $pat) {
            foreach (glob($pat) as $match) {
                if (file_exists($match)) {
                    $found = $match;
                    break 2;
                }
            }
        }

        if (!$found) {
            $out = [];
            exec('where MySQLWorkbench.exe 2>NUL', $out, $rc);
            if ($rc === 0 && !empty($out)) {
                $found = $out[0];
            }
        }
    }

    if (!$found) {
        fwrite(STDOUT, "Could not find MySQL Workbench executable.\n");
        fwrite(STDOUT, "Set environment variable MYSQL_WORKBENCH to the full path of MySQLWorkbench.exe\n");
        exit(2);
    }

    @pclose(@popen('cmd /c start "" "' . str_replace('"', '', $found) . '"', 'r'));
    fwrite(STDOUT, "Opening MySQL Workbench: {$found}\n");
}
