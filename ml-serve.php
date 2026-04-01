<?php
// ml-serve.php
// Usage: php ml-serve.php [project]
// When invoked without an argument, uses the current directory name as the project.

$project = $argv[1] ?? null;
if (!$project) {
    $cwd = getcwd();
    $project = $cwd === false ? '' : basename($cwd);
}

// Normalize any absolute/local path into a web project slug.
if ($project) {
    $project = str_replace('\\', '/', trim($project));
    $project = preg_replace('#^[A-Za-z]:/#', '', $project);
    if (stripos($project, 'xampp/htdocs/') !== false) {
        $project = preg_replace('#^.*xampp/htdocs/#i', '', $project);
    } elseif (stripos($project, 'htdocs/') !== false) {
        $project = preg_replace('#^.*htdocs/#i', '', $project);
    }
    $project = rtrim($project, '/');
    $project = preg_replace('#/public$#i', '', $project);
    $parts = array_values(array_filter(explode('/', $project), 'strlen'));
    $project = $parts[0] ?? '';
}

if (!$project) {
    fwrite(STDERR, "Error: cannot determine project name. Provide it as `ml serve <project>` or run inside a project folder.\n");
    exit(2);
}

$link = 'http://localhost/' . $project;
echo "Open project at: $link" . PHP_EOL;

// Try to open in default browser (cross-platform)
if (stripos(PHP_OS, 'WIN') === 0) {
    // Windows
    pclose(popen('start "" "' . $link . '"', 'r'));
} elseif (stripos(PHP_OS, 'DAR') === 0) {
    // macOS
    @exec('open "' . $link . '"');
} else {
    // Linux/other
    @exec('xdg-open "' . $link . '" >/dev/null 2>&1 &');
}

exit(0);
