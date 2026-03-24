<?php
// ml-local.php
// Local helper to install ML CLI files into a local tools folder for testing.
// This mirrors what install-ml.bat installs remotely, but uses local files.

declare(strict_types=1);

$sourceDir = __DIR__;
$dest = 'C:\\ML CLI\\Tools';
if (isset($argv[1]) && !empty($argv[1])) {
    $dest = $argv[1];
}

echo "Installing ML CLI (local) to: $dest\n";

if (!is_dir($dest)) {
    if (!@mkdir($dest, 0777, true)) {
        fwrite(STDERR, "[ERROR] Failed to create destination folder: $dest\n");
        exit(2);
    }
    echo "Created: $dest\n";
}

$errors = 0;

// Files that the official installer places in the target
$want = [
    // generator stub (installer downloads generate-file-remote.php and saves as generate-file-structure.php)
    'generate-file-structure.php',
    'ml.bat',
    'uninstall-ml.bat',
    // CLI wrappers for different shells
    'ml.cmd',
    'ml.ps1'
];

// If we only have the remote loader locally, copy it as the installed generator name
foreach ($want as $f) {
    $src = $sourceDir . DIRECTORY_SEPARATOR . $f;
    $dst = rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . $f;

    // special-case: if generate-file-structure.php is missing but generate-file-remote.php exists,
    // copy generate-file-remote.php into the target as generate-file-structure.php so behavior matches remote installer.
    if ($f === 'generate-file-structure.php' && !file_exists($src)) {
        $alt = $sourceDir . DIRECTORY_SEPARATOR . 'generate-file-remote.php';
        if (file_exists($alt)) {
            $src = $alt;
        }
    }

    if (!file_exists($src)) {
        echo "Skipping missing source: " . basename($src) . "\n";
        $errors++;
        continue;
    }

    if ($f === 'generate-file-structure.php' && basename($src) === 'generate-file-remote.php') {
        // copy remote loader but name it generate-file-structure.php in destination
        $dst = rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . 'generate-file-structure.php';
    }

    if (@copy($src, $dst)) {
        echo "Copied: " . basename($src) . " -> $dst\n";
    } else {
        fwrite(STDERR, "[ERROR] Failed to copy: " . basename($src) . "\n");
        $errors++;
    }
}

// Ensure VERSION in installed ml.bat matches local VERSION if present
$localVersion = null;
$localVersionFile = $sourceDir . DIRECTORY_SEPARATOR . 'VERSION';
if (file_exists($localVersionFile)) {
    $localVersion = trim(file_get_contents($localVersionFile));
}

if ($localVersion !== null && $localVersion !== '') {
    $installedBat = rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . 'ml.bat';
    if (file_exists($installedBat)) {
        $bat = file_get_contents($installedBat);
        if ($bat !== false) {
            $new = preg_replace('/set \\\"ML_VERSION=.*\\\"/i', 'set "ML_VERSION=' . addcslashes($localVersion, '\\\\"') . '"', $bat, 1);
            if ($new !== null) {
                file_put_contents($installedBat, $new);
                echo "Set ML_VERSION in installed ml.bat to: $localVersion\n";
            }
        }
    }
    // write VERSION file in destination
    @file_put_contents(rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . 'VERSION', $localVersion);
    echo "Wrote VERSION ($localVersion)\n";
} else {
    echo "No local VERSION file found; leaving installed ml.bat unchanged for ML_VERSION.\n";
}

// Write a simple version.txt with source info and timestamp (mirror installer behavior)
$versionTxt = rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . 'version.txt';
$content = [];
$content[] = 'ML CLI Installer (local)';
$content[] = 'Source: ' . $sourceDir;
$content[] = 'InstalledAt: ' . date('Y-m-d H:i:s');
@file_put_contents($versionTxt, implode(PHP_EOL, $content));
echo "Wrote version.txt\n";

// Write a small made-by.txt (mirrors installer art)
$madeBy = rtrim($dest, "\\\\\/") . DIRECTORY_SEPARATOR . 'made-by.txt';
$made = <<<'TXT'
██████╗ ██████╗ ██████╗ ███████╗███████╗
██╔════╝██╔═══██╗██╔══██╗██╔════╝╚══███╔╝
██║     ██║   ██║██║  ██║█████╗    ███╔╝ 
██║     ██║   ██║██║  ██║██╔══╝   ███╔╝  
╚██████╗╚██████╔╝██████╔╝███████╗███████╗
 ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝╚══════╝
Follow: https://github.com/ZheyUse
TXT;
@file_put_contents($madeBy, $made);
echo "Wrote made-by.txt\n";

if ($errors === 0) {
    echo "Local installation complete.\n";
    exit(0);
}

echo "Completed with $errors issues (some sources missing).\n";
exit(1);
