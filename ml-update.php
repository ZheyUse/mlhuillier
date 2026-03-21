<?php
/**
 * ml-update.php
 *
 * Remote updater for ML CLI. When executed locally (php streamed), it will
 * download the latest `ml.bat`, `VERSION`, and `version.txt` from the
 * repository and write them into the installer target `C:\\ML CLI\\Tools`.
 *
 * This script is intended to be streamed from the repository via the `ml update`
 * command and executed by PHP on the user's machine.
 */

$baseRaw = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main';
$cacheToken = '?t=' . time() . rand(1000,9999);
$files = [
    'ml.bat' => $baseRaw . '/ml.bat' . $cacheToken,
    'VERSION' => $baseRaw . '/VERSION' . $cacheToken,
];

$targetDir = 'C:\\ML CLI\\Tools';

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
    safeEcho('ML Updater: Writing to ' . $dest . ' ...');
    if (file_put_contents($dest, $data) === false) {
        safeEcho('ML Updater: Failed to write ' . $dest . '. Check permissions.');
        exit(2);
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

safeEcho('ML Updater: Update completed successfully.');
exit(0);
