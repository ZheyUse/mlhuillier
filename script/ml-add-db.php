<?php
/**
 * ml-add-db.php - Create databases and add tables to existing databases
 * Usage:
 *   php ml-add-db.php <database_name>           - Create new database (optional tables)
 *   php ml-add-db.php <database_name> -tb         - Add tables to existing database
 *   php ml-add-db.php --tb                       - Select database then add tables
 */

function getConnection(string $host, int $port, string $user, string $password): PDO {
    try {
        $dsn = "mysql:host=$host;port=$port";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        fwrite(STDERR, "Connection failed: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function getConfig(): array {
    $configPaths = [
        __DIR__ . '/../mlcli-config.json',
        'C:/ML CLI/Tools/mlcli-config.json',
        $_SERVER['USERPROFILE'] . '/mlcli-config.json'
    ];

    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $config = json_decode($content, true);
            if ($config && isset($config['host'], $config['user'], $config['password'])) {
                return [
                    'host' => $config['host'] ?? 'localhost',
                    'port' => $config['port'] ?? 3306,
                    'user' => $config['user'],
                    'password' => $config['password']
                ];
            }
        }
    }

    echo "Database configuration not found." . PHP_EOL;
    echo "Please run 'ml create --config' first to set up your database credentials." . PHP_EOL;
    exit(1);
}

function validateName(string $name): bool {
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
}

function promptTables(PDO $pdo, string $databaseName): array {
    echo PHP_EOL;
    echo "Input table names separated by comma:" . PHP_EOL;
    echo "Example: users, products, orders" . PHP_EOL;
    echo PHP_EOL;
    echo "Tables: ";

    $tablesInput = trim(fgets(STDIN));
    $tables = [];

    if (!empty($tablesInput)) {
        $tableNames = array_map('trim', explode(',', $tablesInput));
        foreach ($tableNames as $name) {
            if (validateName($name)) {
                $tables[] = $name;
            }
        }
    }

    if (count($tables) === 0) {
        echo "No valid tables provided." . PHP_EOL;
        return [];
    }

    $pdo->exec("USE `$databaseName`");

    foreach ($tables as $tableName) {
        $createSQL = "CREATE TABLE `$tableName` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($createSQL);
        echo "$tableName has been added to $databaseName" . PHP_EOL;
    }

    return $tables;
}

function createNewDatabase(PDO $pdo, string $databaseName): void {
    $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([$databaseName]);
    if ($stmt->fetch()) {
        fwrite(STDERR, "Error: Database '$databaseName' already exists." . PHP_EOL);
        exit(1);
    }

    $pdo->exec("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$databaseName' has been created." . PHP_EOL;

    echo PHP_EOL;
    echo "Do you want to add Tables? (Y/N): ";
    $addTables = strtoupper(trim(fgets(STDIN)));

    if ($addTables === 'Y' || $addTables === 'YES') {
        $tables = promptTables($pdo, $databaseName);
        if (count($tables) > 0) {
            echo PHP_EOL;
            echo "$databaseName has been created with tables:" . PHP_EOL;
            $num = 1;
            foreach ($tables as $tbl) {
                echo "  $num. $tbl" . PHP_EOL;
                $num++;
            }
        }
    }
}

function addTablesToDatabase(PDO $pdo, string $databaseName): void {
    $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([$databaseName]);
    if (!$stmt->fetch()) {
        fwrite(STDERR, "Error: Database '$databaseName' does not exist." . PHP_EOL);
        exit(1);
    }

    promptTables($pdo, $databaseName);
}

function selectDatabaseAndAddTables(PDO $pdo): void {
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($databases) === 0) {
        echo "No databases found." . PHP_EOL;
        exit(1);
    }

    echo "Select Database to be inserted with the new table:" . PHP_EOL;
    $num = 1;
    foreach ($databases as $db) {
        if ($db !== 'information_schema' && $db !== 'mysql' && $db !== 'performance_schema' && $db !== 'sys') {
            echo "$num. $db" . PHP_EOL;
            $num++;
        }
    }

    echo PHP_EOL;
    echo "Database (name or number): ";
    $selection = trim(fgets(STDIN));

    if (is_numeric($selection)) {
        $index = (int)$selection - 1;
        $filtered = array_values(array_filter($databases, function($db) {
            return $db !== 'information_schema' && $db !== 'mysql' && $db !== 'performance_schema' && $db !== 'sys';
        }));
        if ($index >= 0 && $index < count($filtered)) {
            $databaseName = $filtered[$index];
        } else {
            fwrite(STDERR, "Error: Invalid selection." . PHP_EOL);
            exit(1);
        }
    } else {
        if (!in_array($selection, $databases)) {
            fwrite(STDERR, "Error: Database '$selection' does not exist." . PHP_EOL);
            exit(1);
        }
        $databaseName = $selection;
    }

    echo PHP_EOL;
    echo "Selected database: $databaseName" . PHP_EOL;
    promptTables($pdo, $databaseName);
}

// Main execution
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line." . PHP_EOL);
    exit(1);
}

$args = $argv;
array_shift($args);

$mode = 'create_new';
$databaseName = null;

foreach ($args as $arg) {
    $arg = trim($arg);
    if ($arg === '-tb' || $arg === '--tb') {
        if ($arg === '--tb') {
            $mode = 'select_and_add';
        } else {
            $mode = 'add_tables';
        }
    } else {
        $databaseName = $arg;
    }
}

$config = getConfig();
$pdo = getConnection($config['host'], $config['port'], $config['user'], $config['password']);

switch ($mode) {
    case 'select_and_add':
        selectDatabaseAndAddTables($pdo);
        break;

    case 'add_tables':
        if (empty($databaseName)) {
            echo "Error: Database name is required for -tb option." . PHP_EOL;
            echo "Usage: ml add -db <database> -tb" . PHP_EOL;
            exit(1);
        }
        if (!validateName($databaseName)) {
            fwrite(STDERR, "Error: Invalid database name '$databaseName'." . PHP_EOL);
            exit(1);
        }
        addTablesToDatabase($pdo, $databaseName);
        break;

    case 'create_new':
    default:
        if (empty($databaseName)) {
            echo "Enter database name: ";
            $input = trim(fgets(STDIN));
            if (empty($input)) {
                echo "Error: Database name cannot be empty." . PHP_EOL;
                exit(1);
            }
            $databaseName = $input;
        }
        if (!validateName($databaseName)) {
            fwrite(STDERR, "Error: Invalid database name '$databaseName'." . PHP_EOL);
            exit(1);
        }
        createNewDatabase($pdo, $databaseName);
        break;
}

exit(0);