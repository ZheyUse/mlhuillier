<?php
// ai-installer.php
// Usage: php ai-installer.php

const AI_PARENT_DIR = 'C:\\free-claude-code';
const AI_INSTALL_DIR = 'C:\\free-claude-code\\free-claude-code';
const AI_REPO_URL = 'https://github.com/Alishahryar1/free-claude-code.git';

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

function runCommand(string $command, ?string $cwd = null): int
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

function askInput(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $line = fgets(STDIN);
    return trim((string)$line);
}

function shellQuote(string $value): string
{
    if (isWindows()) {
        return '"' . str_replace('"', '\"', $value) . '"';
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
                $lines[$idx] = $key . '="' . str_replace('"', '\"', $value) . '"';
                $seen[$key] = true;
            }
        }
    }

    foreach ($values as $key => $value) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '="' . str_replace('"', '\"', $value) . '"';
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
    $appData = getenv('APPDATA') ?: '';
    if ($appData === '') {
        fwrite(STDERR, 'CLI: Unable to locate APPDATA for settings.json.' . PHP_EOL);
        exit(2);
    }

    $settingsDir = $appData . DIRECTORY_SEPARATOR . 'Code' . DIRECTORY_SEPARATOR . 'User';
    $settingsPath = $settingsDir . DIRECTORY_SEPARATOR . 'settings.json';

    if (!is_dir($settingsDir)) {
        mkdir($settingsDir, 0777, true);
    }

    $settings = [];
    if (is_file($settingsPath)) {
        $raw = file_get_contents($settingsPath);
        $decoded = json_decode(stripJsonComments((string)$raw), true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }

    $merge = [
        'liveServer.settings.CustomBrowser' => 'chrome',
        'dart.flutterSdkPath' => 'C:\\src\\flutter',
        'workbench.editor.empty.hint' => 'hidden',
        'github.copilot.nextEditSuggestions.enabled' => true,
        'files.autoSave' => 'afterDelay',
        'git.autofetch' => true,
        'chat.mcp.gallery.enabled' => true,
        'deepseek.lang' => 'en',
        'python.terminal.useEnvFile' => true,
        'terminal.integrated.initialHint' => false,
        'claudeCode.environmentVariables' => [
            ['name' => 'ANTHROPIC_BASE_URL', 'value' => 'http://localhost:8082'],
            ['name' => 'ANTHROPIC_AUTH_TOKEN', 'value' => 'freecc'],
        ],
        'claudeCode.disableLoginPrompt' => true,
    ];

    foreach ($merge as $key => $value) {
        $settings[$key] = $value;
    }

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($settingsPath, $json . PHP_EOL) === false) {
        fwrite(STDERR, 'CLI: Failed to configure settings.json.' . PHP_EOL);
        exit(2);
    }
}

if (is_dir(AI_INSTALL_DIR)) {
    echo 'Free-Claude-Code is already installed.' . PHP_EOL;
    echo 'Run: ml --ai' . PHP_EOL;
    exit(0);
}

echo 'CLI: Installing Pre-requisites...' . PHP_EOL;
if (runCommand('pip install uv') !== 0) {
    fwrite(STDERR, 'CLI: Failed installing pre-requisites.' . PHP_EOL);
    exit(2);
}
echo 'CLI: Pre-requisites ...ok' . PHP_EOL;

echo 'CLI: Cloning free-claude-code...' . PHP_EOL;
if (!is_dir(AI_PARENT_DIR)) {
    mkdir(AI_PARENT_DIR, 0777, true);
}
if (runCommand('git clone ' . shellQuote(AI_REPO_URL), AI_PARENT_DIR) !== 0) {
    fwrite(STDERR, 'CLI: Failed cloning free-claude-code.' . PHP_EOL);
    exit(2);
}
echo 'CLI: free-claude-code ...ok' . PHP_EOL;

echo 'CLI: Initializing .env file...' . PHP_EOL;
$envExample = AI_INSTALL_DIR . DIRECTORY_SEPARATOR . '.env.example';
$envPath = AI_INSTALL_DIR . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envExample) || !copy($envExample, $envPath)) {
    fwrite(STDERR, 'CLI: Failed initializing .env file.' . PHP_EOL);
    exit(2);
}
echo 'CLI: .env initialized ...ok' . PHP_EOL;

echo 'Get NVIDIA NIM KEY at https://build.nvidia.com/' . PHP_EOL;
$key = askInput('Enter NVIDIA NIM API KEY: ');
while (!validateNvidiaKey($key)) {
    echo 'CLI: Invalid API Key.' . PHP_EOL;
    echo 'Get NVIDIA NIM KEY at https://build.nvidia.com/' . PHP_EOL;
    $key = askInput('Enter NVIDIA NIM API KEY: ');
}
setEnvValues($envPath, ['NVIDIA_NIM_API_KEY' => $key]);
echo 'CLI: NVIDIA NIM KEY injected ...ok' . PHP_EOL;

echo 'CLI: Adding proper models...' . PHP_EOL;
setEnvValues($envPath, [
    'MODEL_OPUS' => 'nvidia_nim/deepseek-ai/deepseek-v4-pro',
    'MODEL_SONNET' => 'nvidia_nim/minimaxai/minimax-m2.7',
    'MODEL_HAIKU' => 'nvidia_nim/z-ai/glm4.7',
    'MODEL' => 'nvidia_nim/google/gemma-3n-e4b-it',
]);
echo 'CLI: Models has been added ...ok' . PHP_EOL;

echo 'CLI: Installing 2nd requirements: Claude-Code...' . PHP_EOL;
$npmRc = runCommand('npm install -g @anthropic-ai/claude-code');
if ($npmRc !== 0) {
    runCommand('npm cache clean --force');
    if (isWindows()) {
        $target = getenv('APPDATA') . '\\npm\\node_modules\\@anthropic-ai';
        runCommand('powershell -NoProfile -Command "Remove-Item -Recurse -Force ' . "'" . $target . "'" . ' -ErrorAction SilentlyContinue"');
    }
    runCommand('npm install -g npm@latest');
    $npmRc = runCommand('npm install -g @anthropic-ai/claude-code');
}
if ($npmRc !== 0) {
    fwrite(STDERR, 'CLI: Claude-Code Installation failed.' . PHP_EOL);
    exit(2);
}
echo 'CLI: Claude-Code Installation ...ok' . PHP_EOL;

echo 'CLI: Configuring settings.json...' . PHP_EOL;
configureVsCodeSettings();
echo 'CLI: Settings.json has been configured ...ok' . PHP_EOL;

echo 'CLI: All set, you can now run: ml --ai' . PHP_EOL;
exit(0);
