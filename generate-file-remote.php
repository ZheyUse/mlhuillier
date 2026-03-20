<?php
/**
 * generate-file-remote.php
 *
 * Loader stub that fetches the real `generate-file-structure.php` from a remote
 * location and executes it. Configure the URL below to point to your hosted
 * generator (raw PHP file). If fetching fails, the stub prints a helpful error.
 */

declare(strict_types=1);

// TODO: set this to the raw URL where your generator lives (HTTPS required)
$remoteUrl = getenv('ML_GENERATOR_URL') ?: 'https://raw.githubusercontent.com/ZheyUse/mlgen/main/generate-file-structure.php';

function fetchRemote(string $url): ?string
{
    // Try cURL first
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $body !== false;
        curl_close($ch);
        return $ok ? $body : null;
    }

    // Fallback to file_get_contents if allowed
    $opts = stream_context_create(['http' => ['timeout' => 15]]);
    $body = @file_get_contents($url, false, $opts);
    if ($body === false) {
        return null;
    }

    return $body;
}

$code = fetchRemote($remoteUrl);
if ($code === null) {
    fwrite(STDERR, "[ERROR] Failed to fetch remote generator: {$remoteUrl}\n");
    fwrite(STDERR, "Set the environment variable ML_GENERATOR_URL to point to your generator URL, or install the full generator locally.\n");
    exit(2);
}

// Save to a temp file and require it to run in current process.
// Create an isolated temp work directory for the fetched generator and its assets.
$workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mlgen_work_' . bin2hex(random_bytes(8));
if (!mkdir($workDir, 0700, true) && !is_dir($workDir)) {
    fwrite(STDERR, "[ERROR] Failed to create temporary work directory.\n");
    exit(3);
}

$temp = $workDir . DIRECTORY_SEPARATOR . 'generate-file-structure.php';
if (file_put_contents($temp, $code) === false) {
    fwrite(STDERR, "[ERROR] Failed to write temporary generator file.\n");
    // Attempt to remove work dir
    @rmdir($workDir);
    exit(3);
}

// Ensure an assets/images folder exists next to the generator so the scaffold
// can copy local images when it runs. Try to download the known logo files
// from the repository raw URLs if available.
$assetsImagesDir = $workDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
@mkdir($assetsImagesDir, 0700, true);
$repoRawBase = 'https://raw.githubusercontent.com/ZheyUse/mlgen/main/assets/images';
foreach (['logo1.png', 'logo2.png'] as $logoFile) {
    $imgUrl = $repoRawBase . '/' . $logoFile;
    $imgBody = fetchRemote($imgUrl);
    if ($imgBody !== null) {
        @file_put_contents($assetsImagesDir . DIRECTORY_SEPARATOR . $logoFile, $imgBody);
    }
}

// Execute the fetched generator from the isolated workdir
require $temp;

// Cleanup the temporary workdir and files we created.
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

rrmdir($workDir);

