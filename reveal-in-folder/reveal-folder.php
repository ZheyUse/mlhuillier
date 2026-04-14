<?php
// reveal-folder.php
// Opens a folder in Windows File Explorer.
// Usage: php reveal-folder.php [project_name_or_path]

$arg = isset($argv[1]) ? trim($argv[1]) : '';

function openFolder($path) {
    $path = rtrim($path, "\\/");
    if (!is_dir($path)) {
        echo "Folder not found: $path\n";
        return 2;
    }
    $escaped = str_replace('"', '\\"', $path);
    $cmd = 'explorer "' . $escaped . '"';
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

// If looks absolute (C:\ or \server\), use directly
if (preg_match('/^[A-Za-z]:\\\\|^\\\\/', $input)) {
    exit(openFolder($input));
}

// Try under XAMPP htdocs
$candidate = 'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . ltrim($input, DIRECTORY_SEPARATOR);
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
