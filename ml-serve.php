<?php
// ml-serve.php
// Usage:
//   php ml-serve.php [project]
//   php ml-serve.php [project] -o
//   php ml-serve.php --projectname -o
//   php ml-serve.php -stop

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

function mlCliConfigPath(): string
{
    $override = getenv('ML_CLI_TOOLS');
    if (is_string($override) && trim($override) !== '') {
        return rtrim($override, "\\/") . DIRECTORY_SEPARATOR . 'mlcli-config.json';
    }
    if (isWindows()) {
        return 'C:\\ML CLI\\Tools\\mlcli-config.json';
    }
    $home = getenv('HOME') ?: sys_get_temp_dir();
    return $home . DIRECTORY_SEPARATOR . '.ml-cli' . DIRECTORY_SEPARATOR . 'mlcli-config.json';
}

function loadMlCliConfig(): array
{
    $path = mlCliConfigPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveMlCliConfig(array $config): bool
{
    $path = mlCliConfigPath();
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return @file_put_contents($path, $json . PHP_EOL) !== false;
}

function ensureNgrokAuthConfigKey(): array
{
    $config = loadMlCliConfig();
    if (!array_key_exists('ngrok-auth', $config)) {
        $config['ngrok-auth'] = '';
        saveMlCliConfig($config);
    }
    return $config;
}

function getConfigNgrokAuthToken(array $config): string
{
    return trim((string)($config['ngrok-auth'] ?? ''));
}

function saveConfigNgrokAuthToken(string $token): void
{
    $config = loadMlCliConfig();
    $config['ngrok-auth'] = $token;
    saveMlCliConfig($config);
}

function ngrokConfigPaths(): array
{
    $paths = [];
    $localAppData = getenv('LOCALAPPDATA') ?: '';
    $userProfile = getenv('USERPROFILE') ?: '';
    $home = getenv('HOME') ?: '';
    $appData = getenv('APPDATA') ?: '';

    if ($localAppData !== '') {
        $paths[] = $localAppData . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
    }
    if ($userProfile !== '') {
        $paths[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
        $paths[] = $userProfile . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
        $paths[] = $userProfile . DIRECTORY_SEPARATOR . '.ngrok2' . DIRECTORY_SEPARATOR . 'ngrok.yml';
    }
    if ($home !== '') {
        $paths[] = $home . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
        $paths[] = $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
        $paths[] = $home . DIRECTORY_SEPARATOR . '.ngrok2' . DIRECTORY_SEPARATOR . 'ngrok.yml';
    }
    if ($appData !== '') {
        $paths[] = dirname($appData) . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'ngrok' . DIRECTORY_SEPARATOR . 'ngrok.yml';
    }

    return array_values(array_unique(array_filter($paths, 'strlen')));
}

function hasAnyNgrokConfigFile(): bool
{
    foreach (ngrokConfigPaths() as $file) {
        if (is_file($file)) {
            return true;
        }
    }
    return false;
}

function ngrokConfigCheckPasses(): bool
{
    if (!cmdExists('ngrok')) {
        return false;
    }

    $out = [];
    $rc = 1;
    if (isWindows()) {
        @exec('ngrok config check >NUL 2>&1', $out, $rc);
    } else {
        @exec('ngrok config check >/dev/null 2>&1', $out, $rc);
    }
    return $rc === 0;
}

function ngrokConfigHasToken(): bool
{
    $envToken = trim((string)(getenv('NGROK_AUTHTOKEN') ?: ''));
    if ($envToken !== '') {
        return true;
    }

    foreach (ngrokConfigPaths() as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        if (preg_match('/^\s*["\']?authtoken["\']?\s*:\s*.+$/mi', $raw)) {
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
        if (stripos(PHP_OS, 'DAR') === 0) {
            fwrite(STDERR, 'Failed: NGROK is missing. Install it with: brew install ngrok/ngrok/ngrok' . PHP_EOL);
        } else {
            fwrite(STDERR, 'Failed: NGROK is missing. Install ngrok with your package manager.' . PHP_EOL);
        }
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

    if (ngrokConfigCheckPasses()) {
        return;
    }

    if (!is_file(mlCliConfigPath())) {
        fwrite(STDERR, 'mlcli-config has not been setup' . PHP_EOL);
        fwrite(STDERR, 'setup your config by running: ml create --config' . PHP_EOL);
        exit(2);
    }

    $config = ensureNgrokAuthConfigKey();

    $configToken = getConfigNgrokAuthToken($config);
    if ($configToken !== '') {
        $cmdFromConfig = 'ngrok config add-authtoken ' . shellQuote($configToken);
        passthru($cmdFromConfig, $rcFromConfig);
        if ($rcFromConfig === 0) {
            return;
        }
    }

    fwrite(STDOUT, 'Get your token on (https://dashboard.ngrok.com/get-started/your-authtoken)' . PHP_EOL);
    $token = askInput('NGROK Auth-Token: ');
    if ($token === '') {
        fwrite(STDERR, 'Failed: missing NGROK Auth-Token' . PHP_EOL);
        exit(2);
    }

    $cmd = 'ngrok config add-authtoken ' . shellQuote($token);
    passthru($cmd, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, 'Failed: unable to save NGROK Auth-Token' . PHP_EOL);
        exit(2);
    }

    saveConfigNgrokAuthToken($token);
}

function startNgrokTunnel(int $port): void
{
    if (isWindows()) {
        pclose(popen('start "" /B cmd /c "ngrok http ' . $port . ' >NUL 2>&1"', 'r'));
        return;
    }
    @exec('ngrok http ' . $port . ' >/dev/null 2>&1 &');
}

function stopNgrokTunnel(): void
{
    $out = [];
    $rc = 1;

    if (isWindows()) {
        @exec('taskkill /F /IM ngrok.exe >NUL 2>&1', $out, $rc);
    } else {
        @exec('pkill -f "ngrok http" >/dev/null 2>&1', $out, $rc);
    }

    if ($rc === 0) {
        echo 'Stopped: NGROK tunnel ended.' . PHP_EOL;
        return;
    }

    echo 'No active NGROK tunnel found.' . PHP_EOL;
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
    $stop = false;

    for ($i = 0; $i < count($args); $i++) {
        $arg = trim((string)$args[$i]);
        if ($arg === '') {
            continue;
        }
        if ($arg === '-o' || $arg === '--online') {
            $online = true;
            continue;
        }
        if ($arg === '-stop' || $arg === '--stop') {
            $stop = true;
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

    return [$project, $online, $stop];
}

[$project, $online, $stop] = parseArgs($argv);

if ($stop) {
    stopNgrokTunnel();
    exit(0);
}

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
