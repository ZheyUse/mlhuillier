<?php
// download-installer.php
// Downloads the remote installer/launcher into the user's Downloads folder.
// Run via: php download-installer.php

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

$targetName = isWindows() ? 'install-ml.bat' : 'ml';
$rawFile = isWindows() ? 'install-ml.bat' : 'ml';
$rawUrl = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/' . $rawFile;

// Determine Downloads folder on Windows (USERPROFILE) or fallback to HOME/Downloads
$home = getenv('USERPROFILE') ?: getenv('HOME');
$downloads = $home ? $home . DIRECTORY_SEPARATOR . 'Downloads' : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'downloads';

if (!is_dir($downloads)) {
    if (!@mkdir($downloads, 0777, true)) {
        fwrite(STDERR, "Error: could not create downloads directory: $downloads\n");
        exit(2);
    }
}

$targetPath = $downloads . DIRECTORY_SEPARATOR . $targetName;
if (file_exists($targetPath)) {
    $ts = date('Ymd-His');
    $suffix = isWindows() ? ".bat" : "";
    $targetPath = $downloads . DIRECTORY_SEPARATOR . "install-ml-{$ts}{$suffix}";
}

echo "Downloading installer from $rawUrl ...\n";

$opts = [
    'http' => [
        'timeout' => 30,
        'user_agent' => 'ml-cli-downloader/1.0'
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
];
$ctx = stream_context_create($opts);
$data = @file_get_contents($rawUrl, false, $ctx);
if ($data === false) {
    fwrite(STDERR, "Error: failed to download $rawUrl\n");
    exit(2);
}

if (@file_put_contents($targetPath, $data) === false) {
    fwrite(STDERR, "Error: failed to write installer to $targetPath\n");
    exit(2);
}

if (!isWindows()) {
    @chmod($targetPath, 0755);
}

echo "Saved installer to: $targetPath\n";
if (!isWindows()) {
    echo "Run it with: {$targetPath} --h\n";
}
exit(0);
