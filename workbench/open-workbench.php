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

// Support `--help`, `--h`, `-h` for `ml wb --help`
foreach ($args as $arg) {
    $la = strtolower((string) $arg);
    if ($la === '--help' || $la === '--h' || $la === '-h' || $la === 'help') {
        showWorkbenchHelp();
        exit(0);
    }
}

$isExport = false;
$exportTail = [];
for ($i = 0; $i < count($args); $i++) {
    $a = (string) $args[$i];
    if (strtolower($a) === '--export') {
        $isExport = true;
        $exportTail = array_slice($args, $i + 1);
        break;
    }
}

if (!$isExport) {
    launchWorkbench();
    exit(0);
}

$databaseSelections = [];
$tableSelections = [];
$method = '';
$fileName = '';
$isDirectMode = count($exportTail) > 0;

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

if ($isDirectMode) {
    [$ok, $databaseSelections, $tableSelections, $method, $fileName, $error] = parseDirectExportArgs($exportTail);
    if (!$ok) {
        fwrite(STDOUT, $error . "\n");
        exit(2);
    }

    $allDatabases = listUserDatabases($mysqli);
    [$ok, $databaseSelections, $tableSelections, $error] = normalizeSelections(
        $databaseSelections,
        $tableSelections,
        $allDatabases
    );
    if (!$ok) {
        fwrite(STDOUT, $error . "\n");
        exit(2);
    }

    if (!in_array($method, ['1', '2', '3', '4', '5', '6'], true)) {
        fwrite(STDOUT, "Error: Invalid option, please try again\n");
        exit(2);
    }

    $missingDb = firstMissingDatabase($mysqli, $databaseSelections);
    if ($missingDb !== null) {
        fwrite(STDOUT, "{$missingDb} cannot be found, please try again\n");
        exit(2);
    }

    for ($i = 0; $i < count($databaseSelections); $i++) {
        $db = $databaseSelections[$i];
        $tables = $tableSelections[$i];
        if (isAllSelector($tables)) {
            continue;
        }
        $allTables = listTables($mysqli, $db);
        [$ok, $tables, $error] = resolveNumberedSelections($tables, $allTables);
        if (!$ok) {
            fwrite(STDOUT, "{$error} cannot be found, please select again\n");
            exit(2);
        }
        $tableSelections[$i] = $tables;

        $missing = firstMissingTable($mysqli, $db, $tables);
        if ($missing !== null) {
            fwrite(STDOUT, "{$missing} cannot be found, please select again\n");
            exit(2);
        }
    }

    if ($fileName === '') {
        $fileName = promptUntilValidFileName();
    } elseif (!isValidFileName($fileName)) {
        fwrite(STDOUT, "Error: Invalid filename, please try again\n");
        exit(2);
    }
} else {
    $databaseSelections = promptUntilValidDatabases($mysqli);
    for ($i = 0; $i < count($databaseSelections); $i++) {
        $db = $databaseSelections[$i];
        $tableSelections[] = promptUntilValidTables($mysqli, $db);
    }
    $method = promptUntilValidMethod();
    $fileName = promptUntilValidFileName();
}

$dateFolder = date('m-d-Y');
$exportDir = 'C:\\ML CLI\\Exports\\' . $dateFolder;
if (!is_dir($exportDir) && !@mkdir($exportDir, 0777, true) && !is_dir($exportDir)) {
    fwrite(STDOUT, "Failed to create export folder: {$exportDir}\n");
    exit(2);
}

$namedExportDir = $exportDir . '\\' . $fileName;
$origNamedExportDir = $namedExportDir;
$counter = 1;
while (file_exists($namedExportDir)) {
    $counter++;
    $namedExportDir = $origNamedExportDir . '(' . $counter . ')';
}
if (!is_dir($namedExportDir) && !@mkdir($namedExportDir, 0777, true) && !is_dir($namedExportDir)) {
    fwrite(STDOUT, "Failed to create export folder: {$namedExportDir}\n");
    exit(2);
}

$mysqldump = findMysqldump($connCfg);
if ($mysqldump === null) {
    fwrite(STDOUT, "Could not find mysqldump.exe.\n");
    exit(2);
}

$outputPaths = [];
for ($i = 0; $i < count($databaseSelections); $i++) {
    $db = $databaseSelections[$i];
    $tables = $tableSelections[$i];
    $tablesForExport = isAllSelector($tables) ? [] : $tables;
    $outputPath = $namedExportDir . '\\' . $db . '.sql';

    $dumpResult = runExport(
        $mysqldump,
        $connCfg,
        $db,
        $tablesForExport,
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

    $outputPaths[] = $outputPath;
}

fwrite(STDOUT, "{$fileName} has been exported successfully\n");
foreach ($outputPaths as $path) {
    fwrite(STDOUT, "Location: {$path}\n");
}

@pclose(@popen('cmd /c start "" explorer "' . str_replace('"', '', $namedExportDir) . '"', 'r'));
exit(0);

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return trim($line === false ? '' : $line);
}

function promptUntilValidDatabases(mysqli $mysqli): array
{
    while (true) {
        $allDatabases = listUserDatabases($mysqli);
        fwrite(STDOUT, "Database List:\n");
        for ($i = 0; $i < count($allDatabases); $i++) {
            fwrite(STDOUT, ($i + 1) . '. ' . $allDatabases[$i] . "\n");
        }
        fwrite(STDOUT, "all\n");
        fwrite(STDOUT, "*\n\n");

        $input = prompt("Select database(s) by name, number, or range: ");
        $tokens = splitCsv($input);
        if (count($tokens) === 0) {
            fwrite(STDOUT, "Please select at least one database\n");
            continue;
        }

        if (count($tokens) === 1 && isUniversalToken($tokens[0])) {
            if (count($allDatabases) === 0) {
                fwrite(STDOUT, "No databases found\n");
                continue;
            }
            return $allDatabases;
        }

        [$ok, $selected, $error] = resolveNumberedSelections($tokens, $allDatabases);
        if (!$ok) {
            fwrite(STDOUT, "{$error} cannot be found, please try again\n");
            continue;
        }

        $missing = firstMissingFromSet($selected, $allDatabases);
        if ($missing !== null) {
            fwrite(STDOUT, "{$missing} cannot be found, please try again\n");
            continue;
        }

        return dedupePreserveOrder($selected);
    }
}

function promptUntilValidTables(mysqli $mysqli, string $database): array
{
    fwrite(STDOUT, "Select Tables to be exported from {$database}\n\n");
    $allTables = listTables($mysqli, $database);
    fwrite(STDOUT, "{$database} List of Tables:\n");
    $i = 1;
    foreach ($allTables as $t) {
        fwrite(STDOUT, $i . '. ' . $t . "\n");
        $i++;
    }
    fwrite(STDOUT, "all\n");
    fwrite(STDOUT, "*\n\n");

    while (true) {
        $input = prompt("Tables (names, numbers, or ranges): ");
        $tables = splitCsv($input);
        if (count($tables) === 0) {
            fwrite(STDOUT, "Please select at least one table\n");
            continue;
        }
        if (count($tables) === 1 && isUniversalToken($tables[0])) {
            return ['*'];
        }
        [$ok, $selected, $error] = resolveNumberedSelections($tables, $allTables);
        if (!$ok) {
            fwrite(STDOUT, "{$error} cannot be found, please select again\n");
            continue;
        }

        $missing = firstMissingTable($mysqli, $database, $selected);
        if ($missing === null) {
            return dedupePreserveOrder($selected);
        }
        fwrite(STDOUT, "{$missing} cannot be found, please select again\n");
    }
}

function promptUntilValidMethod(): string
{
    fwrite(STDOUT, "Select export method:\n");
    fwrite(STDOUT, "1. Structure Only\n");
    fwrite(STDOUT, "2. Data Only\n");
    fwrite(STDOUT, "3. Data + Structure\n");
    fwrite(STDOUT, "4. Structure + Schema\n");
    fwrite(STDOUT, "5. Data + Schema\n");
    fwrite(STDOUT, "6. Full Export (Data + Structure + Schema)\n\n");

    while (true) {
        $m = prompt("Method: ");
        if (in_array($m, ['1', '2', '3', '4', '5', '6'], true)) {
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
    if (preg_match('~[<>:"/\\|?*]~', $name)) return false;
    if (preg_match('/[\.\s]$/', $name)) return false;
    return true;
}

function parseDirectExportArgs(array $args): array
{
    $dbArg = '';
    $tbArgs = [];
    $method = '';
    $fileName = '';

    if (count($args) === 0) {
        return [false, [], [], '', '', "Error: Invalid option, please try again"];
    }

    $hasFlag = false;
    foreach ($args as $token) {
        if (strlen((string) $token) > 0 && (string) $token[0] === '-') {
            $hasFlag = true;
            break;
        }
    }

    if (!$hasFlag) {
        $expr = trim(implode(' ', $args));
        [$ok, $db, $tables, $legacyMethod] = parseLegacyExportExpression($expr);
        if (!$ok) {
            return [false, [], [], '', '', "Error: Invalid option, please try again"];
        }
        return [true, [$db], [implode(',', $tables)], $legacyMethod, '', ''];
    }

    for ($i = 0; $i < count($args); $i++) {
        $token = strtolower((string) $args[$i]);
        if ($token === '-db') {
            if (!isset($args[$i + 1])) {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            $dbArg = consumeFlagValue($args, $i);
            if ($dbArg === '') {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            continue;
        }
        if ($token === '-tb') {
            if (!isset($args[$i + 1])) {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            $tbVal = consumeFlagValue($args, $i);
            if ($tbVal === '') {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            $tbArgs[] = $tbVal;
            continue;
        }
        if ($token === '-m') {
            if (!isset($args[$i + 1])) {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            $method = consumeFlagValue($args, $i);
            continue;
        }
        if ($token === '-fn') {
            if (!isset($args[$i + 1])) {
                return [false, [], [], '', '', "Error: Invalid option, please try again"];
            }
            $fileName = consumeFlagValue($args, $i);
            continue;
        }

        return [false, [], [], '', '', "Error: Invalid option, please try again"];
    }

    if ($dbArg === '' || count($tbArgs) === 0 || $method === '') {
        return [false, [], [], '', '', "Error: Invalid option, please try again"];
    }

    return [true, splitCsv($dbArg), $tbArgs, $method, $fileName, ''];
}

function consumeFlagValue(array $args, int &$i): string
{
    $parts = [];
    for ($j = $i + 1; $j < count($args); $j++) {
        $next = (string) $args[$j];
        if (strlen($next) > 0 && $next[0] === '-') {
            break;
        }
        $parts[] = trim($next);
    }

    if (count($parts) === 0) {
        return '';
    }

    $i += count($parts);
    return trim(implode(' ', $parts));
}

function normalizeSelections(array $databases, array $tableArgs, array $allDatabases): array
{
    if (count($databases) === 0) {
        return [false, [], [], "Error: Invalid option, please try again"];
    }

    // PowerShell can expand unquoted '*' into a space-separated file list.
    // If detected on -db value, treat it as universal selector.
    if (count($databases) === 1 && looksLikeExpandedWildcard((string) $databases[0])) {
        $databases = ['*'];
    }

    if (count($databases) === 1 && isUniversalToken($databases[0])) {
        $databases = $allDatabases;
    } else {
        [$ok, $databases, $error] = resolveNumberedSelections($databases, $allDatabases);
        if (!$ok) {
            return [false, [], [], "{$error} cannot be found, please try again"];
        }
        $databases = dedupePreserveOrder($databases);
    }

    if (count($databases) === 0) {
        return [false, [], [], "No databases found"];
    }

    if (count($tableArgs) === 1 && isUniversalToken((string) $tableArgs[0]) && count($databases) > 1) {
        $expanded = [];
        for ($i = 0; $i < count($databases); $i++) {
            $expanded[] = '*';
        }
        $tableArgs = $expanded;
    }

    if (count($tableArgs) !== count($databases)) {
        return [false, [], [], "Error: Number of -tb arguments must match number of databases"];
    }

    $normalizedTables = [];
    foreach ($tableArgs as $tbArg) {
        $tbArg = trim((string) $tbArg);
        // Same wildcard-expansion guard for -tb values in PowerShell.
        if (looksLikeExpandedWildcard($tbArg)) {
            $tbArg = '*';
        }
        if (isUniversalToken($tbArg)) {
            $normalizedTables[] = ['*'];
            continue;
        }

        $tables = splitCsv($tbArg);
        if (count($tables) === 0) {
            return [false, [], [], "Error: Invalid option, please try again"];
        }
        $normalizedTables[] = dedupePreserveOrder($tables);
    }

    return [true, $databases, $normalizedTables, ''];
}

function splitCsv(string $value): array
{
    return array_values(array_filter(
        array_map('trim', preg_split('/[\s,]+/', $value) ?: []),
        'strlen'
    ));
}

function resolveNumberedSelections(array $tokens, array $available): array
{
    $selected = [];

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') {
            continue;
        }

        if (preg_match('/^\d+$/', $token) === 1) {
            $index = (int) $token;
            if ($index < 1 || $index > count($available)) {
                return [false, [], $token];
            }
            $selected[] = (string) $available[$index - 1];
            continue;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $token, $m) === 1) {
            $start = (int) $m[1];
            $end = (int) $m[2];
            if ($start < 1 || $end < 1 || $start > $end || $end > count($available)) {
                return [false, [], $token];
            }

            for ($i = $start; $i <= $end; $i++) {
                $selected[] = (string) $available[$i - 1];
            }
            continue;
        }

        $selected[] = $token;
    }

    return [true, dedupePreserveOrder($selected), ''];
}

function looksLikeExpandedWildcard(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    // Already explicit wildcard selector.
    if ($value === '*') {
        return true;
    }

    // Heuristic: wildcard expansion usually becomes many space-separated names
    // that exist as files/folders in current working directory.
    $parts = preg_split('/\s+/', $value) ?: [];
    if (count($parts) < 2) {
        return false;
    }

    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') {
        return false;
    }

    $matches = 0;
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $candidate = $cwd . DIRECTORY_SEPARATOR . $part;
        if (file_exists($candidate)) {
            $matches++;
        }
    }

    return $matches >= 2;
}

function isUniversalToken(string $value): bool
{
    $value = strtolower(trim($value));
    return $value === 'all' || $value === '*';
}

function isAllSelector(array $tables): bool
{
    return count($tables) === 1 && isUniversalToken((string) $tables[0]);
}

function dedupePreserveOrder(array $values): array
{
    $seen = [];
    $result = [];
    foreach ($values as $value) {
        $key = strtolower((string) $value);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = (string) $value;
    }
    return $result;
}

function firstMissingFromSet(array $selected, array $available): ?string
{
    $set = [];
    foreach ($available as $item) {
        $set[strtolower((string) $item)] = true;
    }

    foreach ($selected as $item) {
        if (!isset($set[strtolower((string) $item)])) {
            return (string) $item;
        }
    }
    return null;
}

function parseLegacyExportExpression(string $expr): array
{
    $expr = trim($expr);
    if ($expr === '') return [false, '', [], ''];

    if ($expr[0] === ':') {
        $expr = trim(substr($expr, 1));
    }

    if (!preg_match('/^(.+?)\s*=\s*([1-6])\s*$/', $expr, $m)) {
        return [false, '', [], ''];
    }

    $left = trim($m[1]);
    $method = trim($m[2]);
    if ($method === '4') {
        // legacy '4' used to mean full export; map to new '6'
        $method = '6';
    }
    $parts = splitCsv($left);
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

function listUserDatabases(mysqli $mysqli): array
{
    $res = $mysqli->query('SHOW DATABASES');
    if (!$res) {
        return [];
    }

    $system = [
        'information_schema' => true,
        'mysql' => true,
        'performance_schema' => true,
        'sys' => true,
    ];

    $out = [];
    while ($row = $res->fetch_row()) {
        $db = (string) $row[0];
        if (isset($system[strtolower($db)])) {
            continue;
        }
        $out[] = $db;
    }
    $res->free();

    return $out;
}

function firstMissingDatabase(mysqli $mysqli, array $databases): ?string
{
    foreach ($databases as $db) {
        if (!databaseExists($mysqli, $db)) {
            return $db;
        }
    }
    return null;
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
    if (count($tables) === 0) return null;

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
        // Structure only
        $parts[] = '--no-data';
    } elseif ($method === '2') {
        // Data only
        $parts[] = '--no-create-info';
    } elseif ($method === '4') {
        // Structure + Schema (structure plus routines/triggers/events)
        $parts[] = '--no-data';
        $parts[] = '--routines';
        $parts[] = '--triggers';
        $parts[] = '--events';
    } elseif ($method === '5') {
        // Data + Schema (data plus routines/triggers/events)
        $parts[] = '--no-create-info';
        $parts[] = '--routines';
        $parts[] = '--triggers';
        $parts[] = '--events';
    } elseif ($method === '6') {
        // Full export (data + structure + schema)
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

function showWorkbenchHelp(): void
{
    fwrite(STDOUT, "Usage: ml wb [--export] [options]\n");
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Flags:\n");
    fwrite(STDOUT, "  --export    Run export mode (direct or interactive)\n");
    fwrite(STDOUT, "  --help, --h, -h   Show this help\n");
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Direct export example:\n");
    fwrite(STDOUT, "  ml wb --export -db userdb,gledb -tb * -tb users -m 6 -fn backup1\n");
    fwrite(STDOUT, "  ml wb --export -db 1 -tb 1-2,3,6-9 -m 6 -fn backup1\n");
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Notes:\n");
    fwrite(STDOUT, "  -db    Comma-separated database names, numbers, ranges, or 'all' / '*'\n");
    fwrite(STDOUT, "  -tb    Repeatable; table names, numbers, ranges, or 'all' / '*'; maps by position to -db\n");
    fwrite(STDOUT, "  -m     Method 1..6 (see below)\n");
    fwrite(STDOUT, "  -fn    Optional export folder name (created under C:\\ML CLI\\Exports)\n");
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Methods:\n");
    fwrite(STDOUT, "  1 Structure Only\n");
    fwrite(STDOUT, "  2 Data Only\n");
    fwrite(STDOUT, "  3 Data + Structure\n");
    fwrite(STDOUT, "  4 Structure + Schema\n");
    fwrite(STDOUT, "  5 Data + Schema\n");
    fwrite(STDOUT, "  6 Full Export (Data + Structure + Schema)\n");
    fwrite(STDOUT, "\n");
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
