<?php
// v-update.php
// Run: php v-update.php [new-version]
// If no argument is provided, the script will prompt for the new version interactively.

$root = __DIR__ . DIRECTORY_SEPARATOR;
$files = [
    'ml' => $root . 'ml.bat',
    'install' => $root . 'install-ml.bat',
    'uninstall' => $root . 'uninstall-ml.bat',
    'version' => $root . 'VERSION',
];

function getCurrentVersion($files) {
    // Prefer VERSION file
    if (is_readable($files['version'])) {
        $v = trim(file_get_contents($files['version']));
        if ($v !== '') return $v;
    }
    // Fallback: check ml.bat
    if (is_readable($files['ml'])) {
        $s = file_get_contents($files['ml']);
        if (preg_match('/set\s+"ML_VERSION=([^\"]+)"/i', $s, $m)) return $m[1];
    }
    return 'unknown';
}

$current = getCurrentVersion($files);

// Accept argument as new version
if (isset($argv[1]) && trim($argv[1]) !== '') {
    $new = trim($argv[1]);
} else {
    echo "Current Version: $current\n\n";
    echo "Enter new version: ";
    $fp = fopen('php://stdin', 'r');
    $new = trim(fgets($fp));
    fclose($fp);
}

if ($new === '') {
    echo "No version provided. Aborting.\n";
    exit(2);
}

// Basic validation: must not contain quotes or newlines
if (preg_match('/[\"\r\n]/', $new)) {
    echo "Invalid version string.\n";
    exit(2);
}

echo "Updating files to version: $new\n";

$ops = [];

// helper to write file (no backups)
function safeWrite($path, $content) {
    return (bool)file_put_contents($path, $content);
}

// Update ml.bat: set "ML_VERSION=..."
if (is_readable($files['ml'])) {
    $s = file_get_contents($files['ml']);
    if (preg_match('/set\s+"ML_VERSION=[^\"]*"/i', $s)) {
        $s2 = preg_replace('/set\s+"ML_VERSION=[^\"]*"/i', 'set "ML_VERSION='.$new.'"', $s, 1);
        if (safeWrite($files['ml'], $s2)) {
            $ops[] = "Updated ml.bat";
        }
    } else {
        $ops[] = "ml.bat: ML_VERSION not found";
    }
} else {
    $ops[] = "ml.bat not found";
}

// Update install-ml.bat: set "CLI_VERSION=..."
if (is_readable($files['install'])) {
    $s = file_get_contents($files['install']);
    if (preg_match('/set\s+"CLI_VERSION=[^\"]*"/i', $s)) {
        $s2 = preg_replace('/set\s+"CLI_VERSION=[^\"]*"/i', 'set "CLI_VERSION='.$new.'"', $s, 1);
        if (safeWrite($files['install'], $s2)) {
            $ops[] = "Updated install-ml.bat";
        }
    } else {
        $ops[] = "install-ml.bat: CLI_VERSION not found";
    }
} else {
    $ops[] = "install-ml.bat not found";
}

// Update uninstall-ml.bat: set "UNINSTALL_VERSION=..."
if (is_readable($files['uninstall'])) {
    $s = file_get_contents($files['uninstall']);
    if (preg_match('/set\s+"UNINSTALL_VERSION=[^\"]*"/i', $s)) {
        $s2 = preg_replace('/set\s+"UNINSTALL_VERSION=[^\"]*"/i', 'set "UNINSTALL_VERSION='.$new.'"', $s, 1);
        if (safeWrite($files['uninstall'], $s2)) {
            $ops[] = "Updated uninstall-ml.bat";
        }
    } else {
        $ops[] = "uninstall-ml.bat: UNINSTALL_VERSION not found";
    }
} else {
    $ops[] = "uninstall-ml.bat not found";
}

// Update VERSION file
if (is_writable(dirname($files['version'])) || is_writable($files['version'])) {
    if (file_put_contents($files['version'], $new) !== false) {
        $ops[] = "Updated VERSION file";
    } else {
        $ops[] = "Failed to write VERSION file";
    }
} else {
    $ops[] = "VERSION file not writable";
}

echo "\nResults:\n";
foreach ($ops as $o) echo " - $o\n";

echo "\nDone.\n";

exit(0);
