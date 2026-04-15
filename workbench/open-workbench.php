<?php
/**
 * open-workbench.php
 *
 * Helper to launch MySQL Workbench on Windows. Prefer setting the
 * MYSQL_WORKBENCH environment variable to the full path of the
 * MySQLWorkbench.exe if it is installed in a non-standard location.
 */

// Only helpful on Windows for now
if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
    fwrite(STDOUT, "This helper currently supports Windows only.\n");
    exit(2);
}

$candidates = [];
$env = getenv('MYSQL_WORKBENCH');
if ($env) {
    $candidates[] = $env;
}

$candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0\\MySQLWorkbench.exe';
$candidates[] = 'C:\\Program Files (x86)\\MySQL\\MySQL Workbench 8.0\\MySQLWorkbench.exe';
$candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 6.3\\MySQLWorkbench.exe';
$candidates[] = 'C:\\Program Files\\MySQL\\MySQL Workbench 8.0\\wb.exe';

$found = null;
foreach ($candidates as $p) {
    if (!$p) continue;
    if (file_exists($p)) {
        $found = $p;
        break;
    }
}

if (!$found) {
    // Try `where` to discover it on PATH
    $out = [];
    exec('where MySQLWorkbench.exe 2>NUL', $out, $rc);
    if ($rc === 0 && !empty($out)) {
        $found = $out[0];
    }
}

if (!$found) {
    fwrite(STDOUT, "Could not find MySQL Workbench executable.\n");
    fwrite(STDOUT, "Set environment variable MYSQL_WORKBENCH to the full path of MySQLWorkbench.exe\n");
    exit(2);
}

// Launch using cmd start so it opens non-blocking
$cmd = 'cmd /c start "" "' . $found . '"';
// Use popen/pclose to detach
@pclose(@popen($cmd, 'r'));
fwrite(STDOUT, "Opening MySQL Workbench: {$found}\n");
exit(0);
