<?php
/**
 * backup-db.php
 *
 * CLI helper to backup MySQL/MariaDB schemas using mysqldump.
 * Usage:
 *  php backup-db.php               # interactive (type schema name or 'all')
 *  php backup-db.php <schema>      # non-interactive
 *
 * Reads config from the ML CLI tools directory.
 */
// Set timezone to avoid warnings when using date()
date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');

function is_windows(): bool {
    return stripos(PHP_OS, 'WIN') === 0;
}

function ml_tools_dir(): string {
    $override = getenv('ML_CLI_TOOLS');
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, "\\/");
    }
    if (is_windows()) {
        return 'C:\\ML CLI\\Tools';
    }
    $home = getenv('HOME') ?: sys_get_temp_dir();
    return $home . DIRECTORY_SEPARATOR . '.ml-cli';
}

function ml_backup_dir(): string {
    $override = getenv('ML_CLI_BACKUP');
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, "\\/");
    }
    if (is_windows()) {
        return 'C:\\ML CLI\\Backup';
    }
    $home = getenv('HOME') ?: sys_get_temp_dir();
    return $home . DIRECTORY_SEPARATOR . 'ML CLI' . DIRECTORY_SEPARATOR . 'Backup';
}

$CONFIG_PATH = ml_tools_dir() . DIRECTORY_SEPARATOR . 'mlcli-config.json';

if (!file_exists($CONFIG_PATH)) {
    fwrite(STDERR, "Error: missing config file\n");
    fwrite(STDERR, "Setup your config by running:\n");
    fwrite(STDERR, "ml create --config\n");
    exit(2);
}

$config = json_decode(file_get_contents($CONFIG_PATH), true);
if (!is_array($config)) {
    fwrite(STDERR, "Error: invalid config file format: {$CONFIG_PATH}\n");
    exit(3);
}

$host = $config['host'] ?? '127.0.0.1';
$port = isset($config['port']) ? intval($config['port']) : 3306;
$user = $config['user'] ?? 'root';
$password = $config['password'] ?? '';
$mysqldumpPath = $config['mysqldumpPath'] ?? '';
$backupRoot = rtrim($config['backupPath'] ?? ml_backup_dir(), "\\/");

// Connect to MySQL server to discover databases
$mysqli = @new mysqli($host, $user, $password, '', $port);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Error: could not connect to MySQL server: {$mysqli->connect_error}\n");
    exit(4);
}

$res = $mysqli->query('SHOW DATABASES');
if (!$res) {
    fwrite(STDERR, "Error: failed to list databases: " . $mysqli->error . "\n");
    exit(5);
}

$schemas = [];
while ($row = $res->fetch_assoc()) {
    $name = $row['Database'] ?? reset($row);
    if (in_array($name, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
        continue;
    }
    $schemas[] = $name;
}
$res->free();
$mysqli->close();

echo "What Schemas do you want to backup?\n";
echo "Available Schema:\n";
echo "All\n";
foreach ($schemas as $s) { echo $s . "\n"; }
echo "\n";

$argv0 = $argv[0] ?? 'backup-db.php';
$requested = $argv[1] ?? null;
if ($requested === null) {
    echo "Enter schema name to backup (or 'all'): ";
    $handle = fopen('php://stdin', 'r');
    $input = trim(fgets($handle));
    fclose($handle);
    $requested = $input;
}
$requested = trim((string)$requested);
if ($requested === '') {
    fwrite(STDERR, "No schema specified. Exiting.\n");
    exit(6);
}

$toBackup = [];
if (strtolower($requested) === 'all') {
    $toBackup = $schemas;
} else {
    if (!in_array($requested, $schemas)) {
        fwrite(STDERR, "Schema '{$requested}' not found on server.\n");
        exit(7);
    }
    $toBackup = [$requested];
}

// Ensure backup root exists
if (!is_dir($backupRoot) && !mkdir($backupRoot, 0777, true)) {
    fwrite(STDERR, "Error: could not create backup root: {$backupRoot}\n");
    exit(8);
}

$todayFolder = $backupRoot . DIRECTORY_SEPARATOR . 'BACKUP_' . date('m-d-y');
if (!is_dir($todayFolder) && !mkdir($todayFolder, 0777, true)) {
    fwrite(STDERR, "Error: could not create backup folder: {$todayFolder}\n");
    exit(9);
}

// locate mysqldump if not provided
function locateExecutable(string $exe): ?string {
    if (stripos(PHP_OS, 'WIN') === 0) {
        exec("where {$exe} 2>&1", $out, $rc);
        if ($rc === 0 && !empty($out)) return $out[0];
    } else {
        exec("which {$exe} 2>&1", $out, $rc);
        if ($rc === 0 && !empty($out)) return $out[0];
    }
    return null;
}

$mysqldump = '';
if (!empty($mysqldumpPath) && file_exists($mysqldumpPath)) {
    $mysqldump = $mysqldumpPath;
} else {
    $found = locateExecutable('mysqldump');
    if ($found) $mysqldump = $found;
}

// If not found in PATH or config, attempt common XAMPP/MySQL locations
if (!$mysqldump) {
    $detected = null;
    if (stripos(PHP_OS, 'WIN') === 0) {
        // Prefer MySQL Server 8 and Workbench locations first, then fallback to XAMPP/MariaDB
        $patterns = [
            'C:\\Program Files\\MySQL\\*\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL Workbench *\\mysqldump.exe',
            'C:\\Program Files (x86)\\MySQL\\*\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\php\\mysqldump.exe',
            'C:\\Program Files\\MariaDB*\\*\\bin\\mysqldump.exe',
        ];
        foreach ($patterns as $pat) {
            $m = glob($pat);
            if (!empty($m)) { $detected = $m[0]; break; }
            if (file_exists($pat)) { $detected = $pat; break; }
        }
    } else {
        $patterns = [
            '/opt/lampp/bin/mysqldump',
            '/opt/*/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/usr/bin/mysqldump',
        ];
        foreach ($patterns as $pat) {
            $m = glob($pat);
            if (!empty($m)) { $detected = $m[0]; break; }
            if (file_exists($pat)) { $detected = $pat; break; }
        }
    }
    if ($detected) {
        $mysqldump = $detected;
        fwrite(STDOUT, "Detected mysqldump at: {$mysqldump}\n");
    }
}

if (!$mysqldump) {
    fwrite(STDERR, "Error: mysqldump not found. Please install it or set 'mysqldumpPath' in config.\n");
    exit(10);
}

foreach ($toBackup as $schemaName) {
    echo "Backing up schema: {$schemaName}\n";
    $schemaDir = $todayFolder . DIRECTORY_SEPARATOR . $schemaName;
    if (!is_dir($schemaDir) && !mkdir($schemaDir, 0777, true)) {
        fwrite(STDERR, "Error: could not create schema folder: {$schemaDir}\n");
        continue;
    }
    $outfile = $schemaDir . DIRECTORY_SEPARATOR . $schemaName . '.sql';

    // create temporary defaults file for credentials
    $tmpCnf = tempnam(sys_get_temp_dir(), 'mlcli_') . '.cnf';
    $cnf = "[client]\n";
    $cnf .= "host={$host}\n";
    $cnf .= "port={$port}\n";
    $cnf .= "user={$user}\n";
    $cnf .= "password={$password}\n";
    file_put_contents($tmpCnf, $cnf);

    $cmd = escapeshellarg($mysqldump)
         . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
         . ' --databases ' . escapeshellarg($schemaName)
         . ' --routines --triggers --events --single-transaction --quick'
         . ' --result-file=' . escapeshellarg($outfile);

    exec($cmd . ' 2>&1', $out, $rc);
    unlink($tmpCnf);

    if ($rc !== 0) {
        fwrite(STDERR, "Error: mysqldump failed for {$schemaName} (exit {$rc}).\n");
        if (!empty($out)) { fwrite(STDERR, implode("\n", $out) . "\n"); }
    } else {
        echo "Saved: {$outfile}\n";
    }
}

echo "Backup complete. Files saved to: {$todayFolder}\n";
