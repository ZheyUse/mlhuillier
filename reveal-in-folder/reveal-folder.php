<?php
// reveal-folder.php
// Opens a folder in File Explorer, Finder, or the Linux desktop file manager.
// Usage: php reveal-folder.php [project_name_or_path]

$arg = isset($argv[1]) ? trim($argv[1]) : '';

function isWindows() {
    return stripos(PHP_OS, 'WIN') === 0;
}

function isMac() {
    return stripos(PHP_OS, 'DAR') === 0;
}

function htdocsPath() {
    $override = getenv('ML_HTDOCS');
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, "\\/");
    }
    if (isWindows()) {
        return 'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs';
    }
    $home = getenv('HOME') ?: '';
    if ($home !== '') {
        $xampp = $home . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs';
        if (is_dir($xampp)) {
            return $xampp;
        }
    }
    if (is_dir('/Applications/XAMPP/htdocs')) {
        return '/Applications/XAMPP/htdocs';
    }
    if (is_dir('/opt/lampp/htdocs')) {
        return '/opt/lampp/htdocs';
    }
    return '/var/www/html';
}

function openFolder($path) {
    $path = rtrim($path, "\\/");
    if (!is_dir($path)) {
        echo "Folder not found: $path\n";
        return 2;
    }
    if (isWindows()) {
        $cmd = 'explorer "' . str_replace('"', '\\"', $path) . '"';
    } elseif (isMac()) {
        $cmd = 'open ' . escapeshellarg($path);
    } else {
        $cmd = 'xdg-open ' . escapeshellarg($path) . ' >/dev/null 2>&1';
    }
    exec($cmd, $out, $rc);
    if ($rc === 0) {
        echo "Opened: $path\n";
        return 0;
    }
    echo "Opening: $path\n";
    return $rc ?: 1;
}

if ($arg === '') {
    // No argument — open current working directory
    $cwd = getcwd();
    echo "Opening current directory: $cwd\n";
    exit(openFolder($cwd));
}

// Normalize slashes
$input = str_replace('/', DIRECTORY_SEPARATOR, $arg);

// If looks absolute, use directly.
if (preg_match('/^[A-Za-z]:\\\\|^\\\\|^\//', $input)) {
    exit(openFolder($input));
}

// Try under XAMPP htdocs
$candidate = htdocsPath() . DIRECTORY_SEPARATOR . ltrim($input, DIRECTORY_SEPARATOR);
if (is_dir($candidate)) {
    exit(openFolder($candidate));
}

// Try relative to current working directory
$candidate2 = getcwd() . DIRECTORY_SEPARATOR . $input;
if (is_dir($candidate2)) {
    exit(openFolder($candidate2));
}

// As a last resort, try the raw input path (may succeed if passing full path)
if (is_dir($input)) {
    exit(openFolder($input));
}

echo "Folder not found using heuristics: '$arg'\n";
exit(2);
