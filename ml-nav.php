<?php
// ml-nav.php
// CLI helper for `ml nav` commands.
// Works on Windows, macOS, and Linux.

function get_htdocs_path(): string
{
    $override = getenv('ML_HTDOCS') ?: '';
    if ($override !== '' && is_dir($override)) {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $override), DIRECTORY_SEPARATOR);
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        return 'C:\\xampp\\htdocs';
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
    // Fallback
    return '/var/www/html';
}

$HTDOCS_PATH = get_htdocs_path();

$args = $argv;
array_shift($args); // remove script name

$opts = [];
foreach ($args as $a) {
    $opts[] = $a;
}

$remoteOnly = in_array('--remote', $opts, true) || getenv('ML_REMOTE') === '1';

function list_projects($base) {
    $items = @scandir($base);
    if ($items === false) return [];
    $out = [];
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $full = $base . DIRECTORY_SEPARATOR . $it;
        if (is_dir($full)) $out[] = $it;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function prompt($label) {
    echo $label . ' ';
    $line = trim(fgets(STDIN));
    return $line;
}

// Determine command mode
$selectedPath = null;
if (count($opts) === 0) {
    // Interactive prompt
    $projects = list_projects($HTDOCS_PATH);
    echo "Where do you want to go?\n";
    echo "0) New (" . $HTDOCS_PATH . ")\n";
    $i = 1;
    foreach ($projects as $p) {
        echo "$i) $p\n";
        $i++;
    }
    echo "Enter number or project name: ";
    $choice = trim(fgets(STDIN));
    if ($choice === '0' || strcasecmp($choice, 'new') === 0) {
        $selectedPath = $HTDOCS_PATH;
    } elseif (is_numeric($choice)) {
        $idx = intval($choice) - 1;
        if (isset($projects[$idx])) {
            $selectedPath = $HTDOCS_PATH . DIRECTORY_SEPARATOR . $projects[$idx];
        }
    } else {
        // treat as name
        $candidate = $HTDOCS_PATH . DIRECTORY_SEPARATOR . $choice;
        if (is_dir($candidate)) $selectedPath = $candidate;
    }
} else {
    // Handle flags
    foreach ($opts as $o) {
        if (strcasecmp($o, 'ml') === 0 || strcasecmp($o, 'nav') === 0) {
            continue;
        }
        if ($o === '--remote') {
            continue;
        }
        if ($o === '--new') {
            $selectedPath = $HTDOCS_PATH;
            break;
        }
        if (substr($o,0,2) === '--') {
            $proj = substr($o,2);
            $candidate = $HTDOCS_PATH . DIRECTORY_SEPARATOR . $proj;
            if (is_dir($candidate)) {
                $selectedPath = $candidate;
                break;
            }
            // try case-insensitive search
            $projects = list_projects($HTDOCS_PATH);
            foreach ($projects as $p) {
                if (strcasecmp($p, $proj) === 0) {
                    $selectedPath = $HTDOCS_PATH . DIRECTORY_SEPARATOR . $p;
                    break 2;
                }
            }
            continue;
        }

        $candidate = $HTDOCS_PATH . DIRECTORY_SEPARATOR . $o;
        if (is_dir($candidate)) {
            $selectedPath = $candidate;
            break;
        }
    }
}

if ($selectedPath === null) {
    fwrite(STDERR, "Project or location not found.\n");
    exit(2);
}

// Print a machine-parseable line for wrappers (e.g. batch file) to perform the actual cd
echo "CD_TO: " . $selectedPath . "\n";

// After navigating, prompt to open in VSCode (skip if remote-only)
if (!$remoteOnly) {
    $open = prompt("Do you want to open " . basename($selectedPath) . " in VSCode? (Y/N)");
    if (strtoupper(substr($open,0,1)) === 'Y') {
        // Try to run `code` if available
        $cmd = 'code -n ' . escapeshellarg($selectedPath);
        // On Windows, using start may be needed but `code` usually registers on PATH if installed
        $rc = null;
        @exec($cmd . ' 2>&1', $out, $rc);
        if ($rc !== 0) {
            echo "Failed to open with `code`. You can open manually: \n" . $cmd . "\n";
            exit(0);
        }
        echo "Opened in VSCode.\n";
    }
} else {
    // Remote-only mode; do not attempt to open anything locally
    echo "(remote-only mode: not attempting to open VSCode)\n";
}

exit(0);
