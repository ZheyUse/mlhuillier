<?php
// ai-installer.php
// Usage: php ai-installer.php
// Works on Windows, macOS, and Linux.

const AI_REPO_URL = 'https://github.com/Alishahryar1/free-claude-code.git';

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

function isMac(): bool
{
    return stripos(PHP_OS, 'DAR') === 0;
}

function isUnix(): bool
{
    return !isWindows();
}

function mlHome(): string
{
    if (isWindows()) {
        return 'C:\\free-claude-code';
    }
    $home = getenv('HOME') ?: '/usr/local';
    return $home . '/.free-claude-code';
}

function aiInstallDir(): string
{
    return mlHome() . DIRECTORY_SEPARATOR . 'free-claude-code';
}

function aiParentDir(): string
{
    return mlHome();
}

function runCommand(string $command, ?string $cwd = null): int
{
    $previous = getcwd();
    if ($cwd !== null && is_dir($cwd)) {
        chdir($cwd);
    }
    $shell = isWindows() ? $command : 'bash -c ' . escapeshellarg($command);
    passthru($shell, $rc);
    if ($previous !== false) {
        chdir($previous);
    }
    return (int)$rc;
}

function runCommandRaw(string $command, ?string $cwd = null): int
{
    $previous = getcwd();
    if ($cwd !== null && is_dir($cwd)) {
        chdir($cwd);
    }
    passthru($command, $rc);
    if ($previous !== false) {
        chdir($previous);
    }
    return (int)$rc;
}

function commandPath(string $cmd): string
{
    $out = [];
    $rc = 1;
    if (isWindows()) {
        exec('where ' . $cmd . ' 2>NUL', $out, $rc);
    } else {
        exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $rc);
    }
    if ($rc === 0 && !empty($out)) {
        return trim((string)$out[0]);
    }
    return '';
}

function uvCommand(): string
{
    $found = commandPath('uv');
    if ($found !== '') {
        return $found;
    }
    if (!isWindows()) {
        $home = getenv('HOME') ?: '';
        $local = $home . '/.local/bin/uv';
        if ($home !== '' && is_executable($local)) {
            return $local;
        }
    }
    return 'uv';
}

function ensureUvAndPython(): bool
{
    if (!commandExists('uv')) {
        if (isWindows()) {
            $install = 'powershell -ExecutionPolicy ByPass -c "irm https://astral.sh/uv/install.ps1 | iex"';
        } else {
            if (!commandExists('curl')) {
                fwrite(STDERR, 'CLI: curl is required to install uv. Install curl and retry.' . PHP_EOL);
                return false;
            }
            $install = 'curl -LsSf https://astral.sh/uv/install.sh | sh';
        }

        if (runCommandRaw($install) !== 0) {
            fwrite(STDERR, 'CLI: Failed installing uv from Astral installer.' . PHP_EOL);
            return false;
        }
    }

    $uv = uvCommand();
    if (runCommandRaw(shellQuote($uv) . ' self update') !== 0) {
        fwrite(STDERR, 'CLI: uv self update failed.' . PHP_EOL);
        return false;
    }
    if (runCommandRaw(shellQuote($uv) . ' python install 3.14') !== 0) {
        fwrite(STDERR, 'CLI: uv Python 3.14 install failed.' . PHP_EOL);
        return false;
    }
    return true;
}

function askInput(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $line = fgets(STDIN);
    return trim((string)$line);
}

function shellQuote(string $value): string
{
    if (isWindows()) {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }
    return escapeshellarg($value);
}

function validateNvidiaKey(string $key): bool
{
    if ($key === '') {
        return false;
    }

    $url = 'https://integrate.api.nvidia.com/v1/models';
    $headers = [
        'Authorization: Bearer ' . $key,
        'Accept: application/json',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status >= 200 && $status < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);
    @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    return preg_match('/\s2\d\d\s/', $statusLine) === 1;
}

function setEnvValues(string $envPath, array $values): void
{
    $lines = is_file($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }

    $seen = [];
    foreach ($lines as $idx => $line) {
        foreach ($values as $key => $value) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                $lines[$idx] = $key . '="' . str_replace('"', '\\"', $value) . '"';
                $seen[$key] = true;
            }
        }
    }

    foreach ($values as $key => $value) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '="' . str_replace('"', '\\"', $value) . '"';
        }
    }

    file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
}

function stripJsonComments(string $json): string
{
    $json = preg_replace('#/\*.*?\*/#s', '', $json);
    $json = preg_replace('#^\s*//.*$#m', '', (string)$json);
    $json = preg_replace('/,\s*([}\]])/', '$1', (string)$json);
    return (string)$json;
}

function configureVsCodeSettings(): void
{
    $settingsPath = '';

    if (isWindows()) {
        $appData = getenv('APPDATA') ?: '';
        if ($appData !== '') {
            $settingsPath = $appData . DIRECTORY_SEPARATOR . 'Code' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . 'settings.json';
        }
    } elseif (isMac()) {
        $home = getenv('HOME') ?: '';
        if ($home !== '') {
            $settingsPath = $home . '/Library/Application Support/Code/User/settings.json';
        }
    } else {
        // Linux
        $configDirs = [
            getenv('XDG_CONFIG_HOME') ?: '',
            (getenv('HOME') ?: '') . '/.config',
        ];
        foreach ($configDirs as $dir) {
            if ($dir !== '') {
                $candidate = $dir . '/Code/User/settings.json';
                if (is_file($candidate) || is_dir(dirname($candidate))) {
                    $settingsPath = $candidate;
                    break;
                }
            }
        }
        // Fallback: VS Code setting file in standard Linux location
        if ($settingsPath === '') {
            $home = getenv('HOME') ?: '';
            if ($home !== '') {
                $settingsPath = $home . '/.config/Code/User/settings.json';
            }
        }
    }

    if ($settingsPath === '' || !is_dir(dirname($settingsPath))) {
        fwrite(STDERR, 'CLI: Unable to locate VS Code settings directory.' . PHP_EOL);
        fwrite(STDERR, '  Please manually configure your settings.json if needed.' . PHP_EOL);
        // Not fatal — skip VS Code config on Unix if we can't find it
        return;
    }

    $settingsDir = dirname($settingsPath);
    if (!is_dir($settingsDir)) {
        mkdir($settingsDir, 0755, true);
    }

    $settings = [];
    if (is_file($settingsPath)) {
        $raw = file_get_contents($settingsPath);
        $decoded = json_decode(stripJsonComments((string)$raw), true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }

    $flutterSdkPath = isWindows()
        ? 'C:\\src\\flutter'
        : ((getenv('HOME') ?: '') . '/development/flutter');

    $merge = [
        'liveServer.settings.CustomBrowser' => 'chrome',
        'dart.flutterSdkPath'               => $flutterSdkPath,
        'workbench.editor.empty.hint'       => 'hidden',
        'github.copilot.nextEditSuggestions.enabled' => true,
        'files.autoSave'                    => 'afterDelay',
        'git.autofetch'                    => true,
        'chat.mcp.gallery.enabled'          => true,
        'python.terminal.useEnvFile'        => true,
        'terminal.integrated.initialHint'   => false,
        'claudeCode.environmentVariables'  => [
            ['name' => 'ANTHROPIC_BASE_URL',  'value' => 'http://localhost:8082'],
            ['name' => 'ANTHROPIC_AUTH_TOKEN', 'value' => 'freecc'],
        ],
        'claudeCode.disableLoginPrompt'     => true,
    ];

    foreach ($merge as $key => $value) {
        $settings[$key] = $value;
    }

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($settingsPath, $json . PHP_EOL) === false) {
        fwrite(STDERR, 'CLI: Failed to write settings.json (may need sudo). Skipping.' . PHP_EOL);
    }
}

// ── Prerequisite checks ────────────────────────────────────────────────────────

function checkPrerequisites(): bool
{
    if (!commandExists('git')) {
        fwrite(STDERR, 'CLI: git is required. Install it with Git for Windows, Homebrew, or your package manager.' . PHP_EOL);
        return false;
    }
    if (!commandExists('npm')) {
        fwrite(STDERR, 'CLI: npm is required. Install Node.js from nodejs.org or via your package manager.' . PHP_EOL);
        return false;
    }
    return true;
}

function commandExists(string $cmd): bool
{
    return commandPath($cmd) !== '';
}

// ── Main ───────────────────────────────────────────────────────────────────────

$installDir = aiInstallDir();
$parentDir  = aiParentDir();

if (is_dir($installDir)) {
    echo 'Free-Claude-Code is already installed at: ' . $installDir . PHP_EOL;
    echo 'Run: ml --ai' . PHP_EOL;
    exit(0);
}

if (!checkPrerequisites()) {
    exit(2);
}

echo 'CLI: Installing pre-requisites...' . PHP_EOL;

if (!ensureUvAndPython()) {
    exit(2);
}
echo 'CLI: pre-requisites ...ok' . PHP_EOL;

echo 'CLI: Cloning free-claude-code...' . PHP_EOL;
if (!is_dir($parentDir)) {
    if (isWindows()) {
        mkdir($parentDir, 0777, true);
    } else {
        mkdir($parentDir, 0755, true);
    }
}
if (runCommand('git clone ' . shellQuote(AI_REPO_URL) . ' ' . shellQuote($installDir)) !== 0) {
    fwrite(STDERR, 'CLI: Failed cloning free-claude-code.' . PHP_EOL);
    exit(2);
}
echo 'CLI: free-claude-code ...ok' . PHP_EOL;

echo 'CLI: Initializing .env file...' . PHP_EOL;
$envExample = $installDir . DIRECTORY_SEPARATOR . '.env.example';
$envPath = $installDir . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envExample) || !copy($envExample, $envPath)) {
    fwrite(STDERR, 'CLI: Failed initializing .env file.' . PHP_EOL);
    exit(2);
}
echo 'CLI: .env initialized ...ok' . PHP_EOL;

echo 'Get NVIDIA NIM KEY at https://build.nvidia.com/' . PHP_EOL;
$key = askInput('Enter NVIDIA NIM API KEY: ');
while ($key !== '' && !validateNvidiaKey($key)) {
    echo 'CLI: Invalid API Key.' . PHP_EOL;
    echo 'Get NVIDIA NIM KEY at https://build.nvidia.com/' . PHP_EOL;
    $key = askInput('Enter NVIDIA NIM API KEY: ');
}
if ($key !== '') {
    setEnvValues($envPath, ['NVIDIA_NIM_API_KEY' => $key]);
    echo 'CLI: NVIDIA NIM KEY injected ...ok' . PHP_EOL;
} else {
    echo 'CLI: Skipping NVIDIA NIM KEY (empty input). Add it manually to .env later.' . PHP_EOL;
}

echo 'CLI: Adding proper models...' . PHP_EOL;
setEnvValues($envPath, [
    'ANTHROPIC_AUTH_TOKEN' => 'freecc',
    'MODEL_OPUS'           => 'nvidia_nim/deepseek-ai/deepseek-v4-pro',
    'MODEL_SONNET'        => 'nvidia_nim/minimaxai/minimax-m2.7',
    'MODEL_HAIKU'        => 'nvidia_nim/z-ai/glm4.7',
    'MODEL'               => 'nvidia_nim/z-ai/glm-5.1',
]);
echo 'CLI: Models has been added ...ok' . PHP_EOL;

echo 'CLI: Installing Claude-Code via npm...' . PHP_EOL;
$npmRc = runCommandRaw('npm install -g @anthropic-ai/claude-code');
if ($npmRc !== 0) {
    runCommandRaw('npm cache clean --force');
    $osSpecificClean = '';
    if (isMac()) {
        $home = getenv('HOME') ?: '';
        $target = $home . '/.npm/_cacache';
        $osSpecificClean = "rm -rf " . escapeshellarg($target);
    } elseif (!isWindows()) {
        $target = getenv('HOME') . '/.npm';
        $osSpecificClean = "rm -rf " . escapeshellarg($target);
    }
    if ($osSpecificClean !== '') {
        runCommandRaw($osSpecificClean);
    }
    runCommandRaw('npm install -g npm@latest');
    $npmRc = runCommandRaw('npm install -g @anthropic-ai/claude-code');
}
if ($npmRc !== 0) {
    fwrite(STDERR, 'CLI: Claude-Code Installation failed.' . PHP_EOL);
    exit(2);
}
echo 'CLI: Claude-Code Installation ...ok' . PHP_EOL;

echo 'CLI: Configuring settings.json...' . PHP_EOL;
configureVsCodeSettings();
echo 'CLI: Settings.json has been configured ...ok' . PHP_EOL;

echo PHP_EOL . 'CLI: All set! Run the following:' . PHP_EOL;
echo '  ml --ai          Start uvicorn + Claude Code' . PHP_EOL;
echo '  ml --ai key      Update NVIDIA_NIM_API_KEY' . PHP_EOL;
echo PHP_EOL;
exit(0);
