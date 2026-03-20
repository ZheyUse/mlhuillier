<?php
// account-insert.php
// Downloads/uses .env in current working directory to connect to DB
// Run via: php account-insert.php

function parseEnvFile($path)
{
    $result = [];
    if (!file_exists($path)) return $result;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, " \t\"'");
        $result[$k] = $v;
    }
    return $result;
}

function findEnvFile($startDir)
{
    $dir = realpath($startDir);
    if ($dir === false) return null;
    while (true) {
        $candidate = $dir . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($candidate)) return $candidate;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    return null;
}

function safeEcho($msg)
{
    echo $msg . PHP_EOL;
}

function prompt($label)
{
    echo $label;
    $fp = fopen('php://stdin', 'r');
    $val = trim(fgets($fp));
    fclose($fp);
    return $val;
}

// Find nearest .env in current directory or parents (project .env)
$envPath = findEnvFile(getcwd());
if ($envPath) {
    safeEcho('Using .env: ' . $envPath);
    $env = parseEnvFile($envPath);
} else {
    safeEcho('Error: cannot find .env in the current project.');
    safeEcho('Hint: cd <projectname>');
    exit(2);
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '';
$dbName = $env['DB_DATABASE'] ?? ($env['DB_NAME'] ?? 'userdb');
$dbUser = $env['DB_USERNAME'] ?? ($env['DB_USER'] ?? 'root');
$dbPass = $env['DB_PASSWORD'] ?? ($env['DB_PASS'] ?? '');

// If .env contains obvious placeholder values, prompt for DB info interactively
if ($dbName === '' || strpos($dbName, '#') === 0 || preg_match('/put\s+system/i', $dbName)) {
    safeEcho('No valid DB name found in .env; please enter DB settings:');
    $dbHost = prompt('DB Host (default 127.0.0.1): ') ?: $dbHost;
    $dbPort = prompt('DB Port (default 3306): ') ?: $dbPort;
    $dbName = prompt('DB Database (e.g. userdb): ') ?: $dbName;
    $dbUser = prompt('DB Username (default root): ') ?: $dbUser;
    $dbPass = prompt('DB Password: ') ?: $dbPass;
}

// Not used directly but kept for debugging
$dsn = 'mysqli://' . $dbUser . ':' . rawurlencode($dbPass) . '@' . $dbHost . ($dbPort ? ':' . $dbPort : '') . '/' . $dbName;

safeEcho('Creating Account...');

// Read interactively for account fields
$stdin = fopen('php://stdin', 'r');
echo 'ID Number: ';
$idNumber = trim(fgets($stdin));
echo 'First Name: ';
$firstName = trim(fgets($stdin));
echo 'Last Name: ';
$lastName = trim(fgets($stdin));

// Role loop
while (true) {
    echo 'Role (Public/Admin): ';
    $role = trim(fgets($stdin));
    if (strcasecmp($role, 'Public') === 0) { $role = 'Public'; break; }
    if (strcasecmp($role, 'Admin') === 0) { $role = 'Admin'; break; }
    echo "Only enter either Public or Admin" . PHP_EOL;
}

if ($idNumber === '' || $firstName === '' || $lastName === '') {
    safeEcho('Aborting: missing required fields.');
    exit(2);
}

$username = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', $lastName), 0, 4)) . $idNumber;

// Generate random password (8 chars)
function genPassword($len = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

$password = $username;
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

safeEcho("Account Name: $firstName $lastName");
safeEcho("Role: $role");
safeEcho('Inserting created account to userdb...');

// Connect to DB using mysqli, allow one interactive retry if connection fails
$portInt = $dbPort ? intval($dbPort) : 3306;
$mysqli = null;
try {
    $mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $portInt);
    if ($mysqli->connect_errno) throw new Exception('connect');
} catch (Throwable $e) {
    safeEcho('Error: Cannot connect to the userdb.');
    safeEcho('Hint: run ml test userdb');
    safeEcho('Please enter DB connection details to retry (leave blank to cancel).');
    $dbHost = prompt('DB Host (current ' . $dbHost . '): ') ?: $dbHost;
    $dbPort = prompt('DB Port (current ' . $dbPort . '): ') ?: $dbPort;
    $dbName = prompt('DB Database (current ' . $dbName . '): ') ?: $dbName;
    $dbUser = prompt('DB Username (current ' . $dbUser . '): ') ?: $dbUser;
    $dbPass = prompt('DB Password: ') ?: $dbPass;
    $portInt = $dbPort ? intval($dbPort) : 3306;
    try {
        $mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $portInt);
        if ($mysqli->connect_errno) throw new Exception('connect');
    } catch (Throwable $e2) {
        safeEcho('Error: Cannot connect to the userdb.');
        safeEcho('Hint: run ml test userdb');
        exit(2);
    }
}

$mysqli->set_charset('utf8mb4');

// Insert into users
$now = date('Y-m-d H:i:s');
$insertUserSql = "INSERT INTO users (id_number, firstname, lastname, username, password, role, dateCreated) VALUES (?,?,?,?,?,?,?)";
$stmt = $mysqli->prepare($insertUserSql);
if ($stmt) {
    $stmt->bind_param('sssssss', $idNumber, $firstName, $lastName, $username, $passwordHash, $role, $now);
    if (!$stmt->execute()) {
        safeEcho('Failed to insert user: ' . $stmt->error);
        $stmt->close();
        $mysqli->close();
        exit(2);
    }
    $stmt->close();
} else {
    safeEcho('Failed to prepare user insert: ' . $mysqli->error);
    $mysqli->close();
    exit(2);
}

// Insert into userlogs — only id_number and status (as requested)
$status = 'active';
$stmt2 = $mysqli->prepare('INSERT INTO userlogs (id_number, status) VALUES (?,?)');
if ($stmt2) {
    $stmt2->bind_param('ss', $idNumber, $status);
    if (!$stmt2->execute()) safeEcho('Warning: Failed to insert userlog: ' . $stmt2->error);
    $stmt2->close();
} else {
    safeEcho('Warning: Could not prepare userlog insert: ' . $mysqli->error);
}

$mysqli->close();

safeEcho('Account created successfully');
safeEcho('Username: ' . $username);
safeEcho('Password: ' . $password);

exit(0);
