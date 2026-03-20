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

function safeEcho($msg)
{
    echo $msg . PHP_EOL;
}

$env = parseEnvFile('.env');
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '';
$dbName = $env['DB_DATABASE'] ?? ($env['DB_NAME'] ?? 'userdb');
$dbUser = $env['DB_USERNAME'] ?? ($env['DB_USER'] ?? 'root');
$dbPass = $env['DB_PASSWORD'] ?? ($env['DB_PASS'] ?? '');

$dsn = 'mysqli://' . $dbUser . ':' . rawurlencode($dbPass) . '@' . $dbHost . ($dbPort ? ':' . $dbPort : '') . '/' . $dbName;

safeEcho('Creating Account...');

$stdin = fopen('php://stdin', 'r');
// Prompt for fields
function prompt($label)
{
    echo $label;
    $fp = fopen('php://stdin', 'r');
    $val = trim(fgets($fp));
    fclose($fp);
    return $val;
}

// Read interactively
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

$password = genPassword(8);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

safeEcho("Account Name: $firstName $lastName");
safeEcho("Role: $role");
safeEcho('Inserting created account to userdb...');

// Connect to DB using mysqli
$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort ?: 3306);
if ($mysqli->connect_errno) {
    safeEcho('Database connection failed: ' . $mysqli->connect_error);
    exit(2);
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

// Insert into userlogs
$insertLogSql = "INSERT INTO userlogs (id_number, status, created_at) VALUES (?,?,?)";
$stmt2 = $mysqli->prepare($insertLogSql);
if ($stmt2) {
    $status = 'active';
    $stmt2->bind_param('sss', $idNumber, $status, $now);
    if (!$stmt2->execute()) {
        safeEcho('Warning: Failed to insert userlog: ' . $stmt2->error);
    }
    $stmt2->close();
} else {
    // try fallback: only id_number and status
    $fallback = $mysqli->prepare('INSERT INTO userlogs (id_number, status) VALUES (?,?)');
    if ($fallback) {
        $status = 'active';
        $fallback->bind_param('ss', $idNumber, $status);
        $fallback->execute();
        $fallback->close();
    }
}

$mysqli->close();

safeEcho('Account created successfully');
safeEcho('Username: ' . $username);
safeEcho('Password: ' . $password);

exit(0);
