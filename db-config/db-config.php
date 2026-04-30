<?php
/**
 * db-config.php
 *
 * Interactive helper to create the config used by `backup-db.php`.
 * It writes a JSON config to the ML CLI tools directory.
 *
 * Usage:
 *  php db-config.php
 */
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

$toolsDir = ml_tools_dir();
$defaultBackup = ml_backup_dir();

// Try to detect common mysqldump locations (XAMPP / system MySQL)
function detect_mysqldump(): ?string {
    if (stripos(PHP_OS, 'WIN') === 0) {
        // Prefer official MySQL 8 client locations (Program Files), then Workbench, then XAMPP/MariaDB
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
            if (!empty($m)) return $m[0];
            if (file_exists($pat)) return $pat;
        }
    } else {
        $paths = ['/opt/lampp/bin/mysqldump', '/usr/local/mysql/bin/mysqldump', '/usr/bin/mysqldump'];
        foreach ($paths as $p) { if (file_exists($p)) return $p; }
    }
    return null;
}

$autoDump = detect_mysqldump();
if ($autoDump) echo "Auto-detected mysqldump: {$autoDump}\n";

function prompt($label, $default = '') {
    if ($default !== '') {
        echo "{$label} [{$default}]: ";
    } else {
        echo "{$label}: ";
    }
    $line = trim(fgets(STDIN));
    return $line === '' ? $default : $line;
}

echo "This will create a config file for ML CLI backup.\n";
$host = prompt('MySQL host', '127.0.0.1');
$port = prompt('MySQL port', '3306');
$user = prompt('MySQL user', 'root');
echo "MySQL password (will be stored in plaintext): ";
$password = trim(fgets(STDIN));
$mysqldumpPath = prompt('Full path to mysqldump (leave blank to use PATH)', $autoDump ?? '');
$backupPath = prompt('Backup path', $defaultBackup);

$cfg = [
    'host' => $host,
    'port' => intval($port),
    'user' => $user,
    'password' => $password,
    'mysqldumpPath' => $mysqldumpPath,
    'backupPath' => $backupPath,
];

if (!is_dir($toolsDir)) {
    if (!mkdir($toolsDir, 0777, true)) {
        fwrite(STDERR, "Error: could not create tools directory: {$toolsDir}\n");
        exit(1);
    }
}

$configPath = $toolsDir . DIRECTORY_SEPARATOR . 'mlcli-config.json';
file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT));

echo "Saved config to: {$configPath}\n";
echo "Warning: password is stored in plaintext. Consider securing it with OS credential store.\n";
