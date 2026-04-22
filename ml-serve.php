<?php
// ml-serve.php
// Usage:
//   php ml-serve.php [project]
//   php ml-serve.php [project] -o
//   php ml-serve.php --projectname -o

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

function normalizeProject(?string $project): string
{
    if (!$project) {
        return '';
    }

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
    return $parts[0] ?? '';
}

function openInBrowser(string $url): void
{
    if (isWindows()) {
        pclose(popen('start "" "' . $url . '"', 'r'));
        return;
    }
    if (stripos(PHP_OS, 'DAR') === 0) {
        @exec('open "' . $url . '"');
        return;
    }
    @exec('xdg-open "' . $url . '" >/dev/null 2>&1 &');
}

function cmdExists(string $name): bool
{
    $out = [];
    $rc = 1;
    if (isWindows()) {
        @exec('where ' . $name . ' >NUL 2>&1', $out, $rc);
    } else {
        @exec('command -v ' . escapeshellarg($name) . ' >/dev/null 2>&1', $out, $rc);
    }
    return $rc === 0;
}

function shellQuote(string $value): string
{
    if (isWindows()) {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }
    return escapeshellarg($value);
}

function askInput(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return trim((string)$line);
}

function ngrokConfigHasToken(): bool
{
    $paths = [];
    if (isWindows()) {
        $localAppData = getenv('LOCALAPPDATA') ?: '';
        if ($localAppData !== '') {
            $paths[] = $localAppData . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
        }
    }
    $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
    if ($home !== '') {
        $paths[] = $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
    }

    foreach ($paths as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        if (preg_match('/^\s*authtoken\s*:\s*\S+/mi', $raw)) {
            return true;
        }
    }
    return false;
}

function ensureNgrokInstalled(): void
{
    if (cmdExists('ngrok')) {
        return;
    }

    fwrite(STDOUT, "Missing Package: NGROK" . PHP_EOL);
    $ans = askInput('Do you want to install this package? (Y/N): ');
    if (strcasecmp($ans, 'Y') !== 0) {
        fwrite(STDERR, 'Failed: NGROK Package is missing' . PHP_EOL);
        exit(2);
    }

    if (!isWindows()) {
        fwrite(STDERR, 'Failed: automatic NGROK install is only configured for Windows winget.' . PHP_EOL);
        exit(2);
    }

    $cmd = 'winget install ngrok -s msstore --accept-source-agreements --accept-package-agreements --disable-interactivity';
    passthru($cmd, $rc);
    if ($rc !== 0 || !cmdExists('ngrok')) {
        fwrite(STDERR, 'Failed: NGROK Package is missing' . PHP_EOL);
        exit(2);
    }
}

function ensureNgrokAuthToken(): void
{
    if (ngrokConfigHasToken()) {
        return;
    }

    fwrite(STDOUT, 'Get your token on (https://dashboard.ngrok.com/get-started/your-authtoken)' . PHP_EOL);
    $token = askInput('NGROK Auth-Token: ');
    if ($token === '') {
        fwrite(STDERR, 'Failed: missing NGROK Auth-Token' . PHP_EOL);
        exit(2);
    }

    $cmd = 'ngrok config add-authtoken ' . shellQuote($token);
    passthru($cmd, $rc);
    if ($rc !== 0 || !ngrokConfigHasToken()) {
        fwrite(STDERR, 'Failed: unable to save NGROK Auth-Token' . PHP_EOL);
        exit(2);
    }
}

function startNgrokTunnel(int $port): void
{
    if (isWindows()) {
        pclose(popen('start "" /B cmd /c "ngrok http ' . $port . ' >NUL 2>&1"', 'r'));
        return;
    }
    @exec('ngrok http ' . $port . ' >/dev/null 2>&1 &');
}

function fetchNgrokTunnels(): array
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 1,
        ],
    ]);
    $json = @file_get_contents('http://127.0.0.1:4040/api/tunnels', false, $ctx);
    if ($json === false || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['tunnels']) || !is_array($data['tunnels'])) {
        return [];
    }
    return $data['tunnels'];
}

function tunnelMatchesPort(array $tunnel, int $port): bool
{
    $addr = (string)($tunnel['config']['addr'] ?? '');
    if ($addr === (string)$port) {
        return true;
    }
    if (stripos($addr, 'localhost:' . $port) !== false) {
        return true;
    }
    if (preg_match('/:' . preg_quote((string)$port, '/') . '$/', $addr)) {
        return true;
    }
    return false;
}

function getNgrokPublicUrlForPort(int $port): string
{
    $fallback = '';
    foreach (fetchNgrokTunnels() as $tunnel) {
        $url = (string)($tunnel['public_url'] ?? '');
        if ($url === '') {
            continue;
        }
        if ($fallback === '' && stripos($url, 'https://') === 0) {
            $fallback = $url;
        }
        if (tunnelMatchesPort($tunnel, $port)) {
            return $url;
        }
    }
    return $fallback;
}

function waitNgrokPublicUrl(int $port, int $attempts = 18, int $sleepMs = 500): string
{
    for ($i = 0; $i < $attempts; $i++) {
        $url = getNgrokPublicUrlForPort($port);
        if ($url !== '') {
            return $url;
        }
        usleep($sleepMs * 1000);
    }
    return '';
}

function parseArgs(array $argv): array
{
    $args = array_slice($argv, 1);
    $project = null;
    $online = false;

    for ($i = 0; $i < count($args); $i++) {
        $arg = trim((string)$args[$i]);
        if ($arg === '') {
            continue;
        }
        if ($arg === '-o' || $arg === '--online') {
            $online = true;
            continue;
        }
        if (stripos($arg, '--project=') === 0) {
            $project = substr($arg, strlen('--project='));
            continue;
        }
        if ($arg === '--project') {
            $next = $args[$i + 1] ?? '';
            if ($next !== '' && strpos($next, '-') !== 0) {
                $project = $next;
                $i++;
            }
            continue;
        }
        if (strpos($arg, '--') === 0) {
            $candidate = substr($arg, 2);
            if ($candidate !== '' && $candidate !== 'online' && $candidate !== 'project') {
                $project = $candidate;
            }
            continue;
        }
        if ($project === null) {
            $project = $arg;
        }
    }

    return [$project, $online];
}

[$project, $online] = parseArgs($argv);

if (!$project) {
    $cwd = getcwd();
    $project = $cwd === false ? '' : basename($cwd);
}

$project = normalizeProject($project);
if (!$project) {
    fwrite(STDERR, 'Error: cannot determine project name. Provide it as ml serve <project> or run inside a project folder.' . PHP_EOL);
    exit(2);
}

if (!$online) {
    $link = 'http://localhost/' . $project;
    echo 'Open project at: ' . $link . PHP_EOL;
    openInBrowser($link);
    exit(0);
}

ensureNgrokInstalled();
ensureNgrokAuthToken();

echo 'Creating a Shareable link to share...' . PHP_EOL;

$publicUrl = getNgrokPublicUrlForPort(80);
if ($publicUrl === '') {
    startNgrokTunnel(80);
    $publicUrl = waitNgrokPublicUrl(80);
}

if ($publicUrl === '') {
    $publicUrl = getNgrokPublicUrlForPort(8080);
    if ($publicUrl === '') {
        startNgrokTunnel(8080);
        $publicUrl = waitNgrokPublicUrl(8080);
    }
}

if ($publicUrl === '') {
    fwrite(STDERR, 'Failed: unable to create NGROK tunnel on ports 80 or 8080.' . PHP_EOL);
    exit(2);
}

$shareLink = rtrim($publicUrl, '/') . '/' . ltrim($project, '/');
echo 'Open project online at: ' . $shareLink . PHP_EOL;
openInBrowser($shareLink);
exit(0);
