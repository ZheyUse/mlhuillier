<?php
/**
 * ml-update.php
 *
 * Remote updater for ML CLI. When executed locally (php streamed), it will
 * download the latest CLI files from the repository and write them into the
 * installer target directory.
 *
 * This script is intended to be streamed from the repository via the `ml update`
 * command and executed by PHP on the user's machine.
 */

$baseRaw = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main';
$cacheToken = '?t=' . time() . rand(1000,9999);
$files = [
    'ml.bat' => $baseRaw . '/ml.bat' . $cacheToken,
    'ml' => $baseRaw . '/ml' . $cacheToken,
    'VERSION' => $baseRaw . '/VERSION' . $cacheToken,
];

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

function targetDir(): string
{
    $override = getenv('ML_CLI_TOOLS');
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, "\\/");
    }
    if (isWindows()) {
        return 'C:\\ML CLI\\Tools';
    }
    $home = getenv('HOME') ?: sys_get_temp_dir();
    return $home . DIRECTORY_SEPARATOR . '.ml-cli';
}

$targetDir = targetDir();

function fetchUrl($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ML Updater');
        $data = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data === false || $code >= 400) {
            throw new RuntimeException('Failed to download ' . $url . ' (' . $err . ')');
        }
        return $data;
    }
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ML Updater\r\n",
            'timeout' => 30,
        ],
    ];
    $context = stream_context_create($opts);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        throw new RuntimeException('Failed to download ' . $url);
    }
    return $data;
}

function safeEcho($line)
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function normalizeWindowsLineEndings($content)
{
    return preg_replace("/\r?\n/", "\r\n", $content);
}

safeEcho('ML Updater: Starting...');

if (!is_dir($targetDir)) {
    safeEcho('ML Updater: Target directory does not exist: ' . $targetDir);
    safeEcho('ML Updater: Attempting to create target directory...');
    if (!@mkdir($targetDir, 0777, true)) {
        safeEcho('ML Updater: Failed to create target directory. Run the updater as Administrator.');
        exit(2);
    }
}

foreach ($files as $name => $url) {
    safeEcho('ML Updater: Downloading ' . $name . ' ...');
    try {
        $data = fetchUrl($url);
    } catch (RuntimeException $e) {
        safeEcho('ML Updater: ' . $e->getMessage());
        exit(2);
    }

    $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
    if (preg_match('/\.(bat|cmd)$/i', $name)) {
        $data = normalizeWindowsLineEndings($data);
    }
    safeEcho('ML Updater: Writing to ' . $dest . ' ...');
    if (file_put_contents($dest, $data) === false) {
        safeEcho('ML Updater: Failed to write ' . $dest . '. Check permissions.');
        exit(2);
    }
    if (!isWindows() && $name === 'ml') {
        @chmod($dest, 0755);
    }
}

// Ensure installed ml.bat reports the downloaded VERSION value
$installedVersion = null;
$versionFile = $targetDir . DIRECTORY_SEPARATOR . 'VERSION';
if (is_file($versionFile)) {
    $installedVersion = trim(@file_get_contents($versionFile));
}
if ($installedVersion) {
    $mlBatPath = $targetDir . DIRECTORY_SEPARATOR . 'ml.bat';
    if (is_file($mlBatPath)) {
        safeEcho('ML Updater: Enforcing ML_VERSION=' . $installedVersion . ' in ' . $mlBatPath . ' ...');
        $mlBatData = @file_get_contents($mlBatPath);
        if ($mlBatData !== false) {
            $newData = preg_replace('/set \"ML_VERSION=.*\"/i', 'set "ML_VERSION=' . addslashes($installedVersion) . '"', $mlBatData, 1);
            if ($newData !== null) {
                $newData = normalizeWindowsLineEndings($newData);
            }
            if ($newData !== null && $newData !== $mlBatData) {
                if (file_put_contents($mlBatPath, $newData) === false) {
                    safeEcho('ML Updater: Failed to update ML_VERSION in ' . $mlBatPath);
                }
            }
        }
    }
    $mlUnixPath = $targetDir . DIRECTORY_SEPARATOR . 'ml';
    if (is_file($mlUnixPath)) {
        safeEcho('ML Updater: Enforcing ML_VERSION=' . $installedVersion . ' in ' . $mlUnixPath . ' ...');
        $mlUnixData = @file_get_contents($mlUnixPath);
        if ($mlUnixData !== false) {
            $newData = preg_replace('/ML_VERSION="[^"]*"/', 'ML_VERSION="' . addslashes($installedVersion) . '"', $mlUnixData, 1);
            if ($newData !== null && $newData !== $mlUnixData) {
                if (file_put_contents($mlUnixPath, $newData) === false) {
                    safeEcho('ML Updater: Failed to update ML_VERSION in ' . $mlUnixPath);
                } else {
                    @chmod($mlUnixPath, 0755);
                }
            }
        }
    }
}

// version.txt is optional in the repository; generate it if not downloadable.
$versionTxtUrl = $baseRaw . '/version.txt' . $cacheToken;
$versionTxtData = null;
try {
    safeEcho('ML Updater: Downloading version.txt ...');
    $versionTxtData = fetchUrl($versionTxtUrl);
} catch (RuntimeException $e) {
    $version = @file_get_contents($targetDir . DIRECTORY_SEPARATOR . 'VERSION');
    if ($version === false) {
        $version = 'unknown';
    }
    $version = trim($version);
    safeEcho('ML Updater: version.txt not found remotely; generating local version.txt');
    $versionTxtData = "ML CLI Updater\nVersion: {$version}\nSource: {$baseRaw}\nUpdatedAt: " . date('c') . "\n";
}

$versionTxtDest = $targetDir . DIRECTORY_SEPARATOR . 'version.txt';
safeEcho('ML Updater: Writing to ' . $versionTxtDest . ' ...');
if (file_put_contents($versionTxtDest, $versionTxtData) === false) {
    safeEcho('ML Updater: Failed to write ' . $versionTxtDest . '. Check permissions.');
    exit(2);
}

// Generate a simple changelog from recent commit messages (not docs)
safeEcho('ML Updater: Generating changelog from recent commits...');
$commitsApi = 'https://api.github.com/repos/ZheyUse/mlhuillier/commits?per_page=50';
try {
    $commitsJson = fetchUrl($commitsApi);
    $commits = json_decode($commitsJson, true);
    if (!is_array($commits)) {
        throw new RuntimeException('Unexpected commits response');
    }

    $lines = [];
    $lines[] = "ML CLI Changelog";
    $lines[] = "GeneratedAt: " . date('c');
    $lines[] = "Version: " . ($installedVersion ? $installedVersion : 'unknown');
    $lines[] = "";

    $max = 30;
    $count = 0;
    foreach ($commits as $c) {
        if ($count >= $max) break;
        $sha = isset($c['sha']) ? $c['sha'] : '';
        $short = $sha ? substr($sha, 0, 7) : '';
        $date = isset($c['commit']['author']['date']) ? $c['commit']['author']['date'] : '';
        $author = isset($c['commit']['author']['name']) ? $c['commit']['author']['name'] : '';
        $message = isset($c['commit']['message']) ? trim($c['commit']['message']) : '';
        $summary = preg_split("/\r?\n/", $message)[0];
        $commitUrl = isset($c['html_url']) ? $c['html_url'] : ($baseRaw . '/commit/' . $sha);

        $lines[] = "- [{$short}] {$summary} ({$author} @ {$date})";
        $lines[] = "  {$commitUrl}";
        $lines[] = "";

        $count++;
    }

    $changelog = implode("\r\n", $lines);
    $changelogDest = $targetDir . DIRECTORY_SEPARATOR . 'changelog.txt';
    safeEcho('ML Updater: Writing changelog to ' . $changelogDest . ' ...');
    if (file_put_contents($changelogDest, $changelog) === false) {
        safeEcho('ML Updater: Failed to write changelog to ' . $changelogDest);
    } else {
        safeEcho('ML Updater: Wrote changelog to ' . $changelogDest);
    }

} catch (RuntimeException $e) {
    safeEcho('ML Updater: Skipped changelog generation: ' . $e->getMessage());
}

safeEcho('ML Updater: Update completed successfully.');
exit(0);
