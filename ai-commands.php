<?php
// ai-commands.php
// Usage: php ai-commands.php [claude|bg|stop|restart|cm|key]
// Works on Windows (PowerShell), macOS, and Linux (bash/sh).

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
    return $home . DIRECTORY_SEPARATOR . '.free-claude-code';
}

function aiInstallDir(): string
{
    return mlHome() . DIRECTORY_SEPARATOR . 'free-claude-code';
}

function aiStateFile(): string
{
    return mlHome() . DIRECTORY_SEPARATOR . 'ml-ai-pids.json';
}

function ensureInstalled(): void
{
    if (!is_dir(aiInstallDir())) {
        fwrite(STDERR, 'Free-Claude-Code is not installed.' . PHP_EOL);
        fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
        exit(2);
    }
}

function loadState(): array
{
    $file = aiStateFile();
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function saveState(array $state): void
{
    $dir = dirname(aiStateFile());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(aiStateFile(), json_encode($state, JSON_PRETTY_PRINT) . PHP_EOL);
}

function runCommand(string $cmd): int
{
    passthru($cmd, $rc);
    return $rc;
}

function promptInput(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return trim((string)$line);
}

function aiEnvPath(): string
{
    return aiInstallDir() . DIRECTORY_SEPARATOR . '.env';
}

function setEnvValue(string $envPath, string $key, string $value): void
{
    $lines = is_file($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }

    $newLine = $key . '="' . str_replace('"', '\\"', $value) . '"';
    $found = false;
    foreach ($lines as $idx => $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', (string)$line) === 1) {
            $lines[$idx] = $newLine;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $lines[] = $newLine;
    }

    file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
}

function normalizeNvidiaModel(string $input): string
{
    $model = trim($input);
    $model = trim($model, "\"'");
    $model = preg_replace('#^nvidia_nim/+#i', '', $model) ?? $model;
    $model = trim($model, '/');
    return 'nvidia_nim/' . $model;
}

function modelDisplayName(string $fullModel): string
{
    $model = preg_replace('#^nvidia_nim/+#i', '', $fullModel) ?? $fullModel;
    $parts = array_values(array_filter(explode('/', $model), 'strlen'));
    return end($parts) ?: $model;
}

function changeModel(): void
{
    ensureInstalled();

    $envPath = aiEnvPath();
    if (!is_file($envPath)) {
        fwrite(STDERR, 'Free Claude Code .env file was not found: ' . $envPath . PHP_EOL);
        fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
        exit(2);
    }

    echo 'Select Model to change:' . PHP_EOL;
    echo '1. Opus' . PHP_EOL;
    echo '2. Sonnet' . PHP_EOL;
    echo '3. Haiku' . PHP_EOL;
    echo '4. Default' . PHP_EOL;
    echo PHP_EOL;

    $choice = promptInput('Model: ');
    $map = [
        '1' => ['Opus', 'MODEL_OPUS'],
        '2' => ['Sonnet', 'MODEL_SONNET'],
        '3' => ['Haiku', 'MODEL_HAIKU'],
        '4' => ['Default', 'MODEL'],
    ];

    if (!isset($map[$choice])) {
        fwrite(STDERR, 'Invalid model selection.' . PHP_EOL);
        exit(2);
    }

    [$label, $key] = $map[$choice];
    $input = promptInput($label . ' nvidia_nim/: ');
    if ($input === '') {
        fwrite(STDERR, 'Model value cannot be empty.' . PHP_EOL);
        exit(2);
    }

    $fullModel = normalizeNvidiaModel($input);
    setEnvValue($envPath, $key, $fullModel);

    echo $label . ' is now using ' . modelDisplayName($fullModel) . PHP_EOL;
}

function changeApiKey(): void
{
    ensureInstalled();

    $envPath = aiEnvPath();
    if (!is_file($envPath)) {
        fwrite(STDERR, 'Free Claude Code .env file was not found: ' . $envPath . PHP_EOL);
        fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
        exit(2);
    }

    $key = promptInput('Enter NVIDIA_NIM_API_KEY: ');
    if ($key === '') {
        fwrite(STDERR, 'NVIDIA_NIM_API_KEY cannot be empty.' . PHP_EOL);
        exit(2);
    }

    setEnvValue($envPath, 'NVIDIA_NIM_API_KEY', $key);

    echo 'NVIDIA_NIM_API_KEY has been set sucessfully' . PHP_EOL;
}

// ── Windows implementation ─────────────────────────────────────────────────────

function psSingleQuote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function powershellQuoteArg(string $value): string
{
    return '"' . str_replace('"', '\"', $value) . '"';
}

function writeScript(string $name, string $body): string
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '-' . uniqid('', true) . '.ps1';
    file_put_contents($path, $body);
    return $path;
}

function startPowerShellScript(string $scriptPath, bool $visible): int
{
    $windowStyle = $visible ? 'Normal' : 'Hidden';
    $cmd = '$p = Start-Process -FilePath powershell.exe -ArgumentList ' .
        '@("-NoExit","-ExecutionPolicy","Bypass","-File",' . psSingleQuote($scriptPath) . ') ' .
        '-WindowStyle ' . $windowStyle . ' -PassThru; Write-Output $p.Id';

    $out = [];
    $rc = 1;
    exec('powershell -NoProfile -ExecutionPolicy Bypass -Command ' . powershellQuoteArg($cmd), $out, $rc);
    if ($rc !== 0 || empty($out)) {
        return 0;
    }
    return (int)trim((string)end($out));
}

function uvicornScript(): string
{
    return '$Host.UI.RawUI.WindowTitle = "ml --ai uvicorn"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(aiInstallDir()) . PHP_EOL .
        'uv run uvicorn server:app --host 0.0.0.0 --port 8082' . PHP_EOL;
}

function claudeScript(): string
{
    return '$Host.UI.RawUI.WindowTitle = "ml --ai claude"' . PHP_EOL .
        '$env:ANTHROPIC_AUTH_TOKEN = "freecc"' . PHP_EOL .
        '$env:ANTHROPIC_BASE_URL = "http://localhost:8082"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(aiInstallDir()) . PHP_EOL .
        'claude' . PHP_EOL;
}

function startAiWindows(bool $uvicornVisible, bool $claudeVisible): void
{
    ensureInstalled();

    $uvicornPs = writeScript('ml-ai-uvicorn', uvicornScript());
    $claudePs  = writeScript('ml-ai-claude', claudeScript());

    $state = [
        'started_at' => date(DATE_ATOM),
        'scripts'   => [$uvicornPs, $claudePs],
        'pids'      => [],
    ];

    $uvicornPid = startPowerShellScript($uvicornPs, $uvicornVisible);
    if ($uvicornPid > 0) {
        $state['pids'][] = $uvicornPid;
    }

    sleep(1);

    $claudePid = startPowerShellScript($claudePs, $claudeVisible);
    if ($claudePid > 0) {
        $state['pids'][] = $claudePid;
    }

    saveState($state);
    echo 'CLI: Free Claude Code processes started.' . PHP_EOL;
}

function stopAiWindows(): void
{
    $state = loadState();
    $pids = array_values(array_filter(array_map('intval', $state['pids'] ?? [])));

    foreach ($pids as $pid) {
        exec('taskkill /F /T /PID ' . $pid . ' >NUL 2>&1');
    }

    $ps = <<<'PS'
$matches = Get-CimInstance Win32_Process | Where-Object {
    ($_.CommandLine -like '*uvicorn server:app*' -and $_.CommandLine -like '*free-claude-code*') -or
    ($_.CommandLine -like '*ml-ai-uvicorn*') -or
    ($_.CommandLine -like '*ml-ai-claude*')
}
foreach ($p in $matches) {
    try { taskkill /F /T /PID $p.ProcessId | Out-Null } catch {}
}
PS;
    $script = writeScript('ml-ai-stop', $ps);
    exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . powershellQuoteArg($script) . ' >NUL 2>&1');
    @unlink($script);

    foreach (($state['scripts'] ?? []) as $scriptPath) {
        if (is_string($scriptPath) && is_file($scriptPath)) {
            @unlink($scriptPath);
        }
    }
    if (is_file(aiStateFile())) {
        @unlink(aiStateFile());
    }

    echo 'CLI: Free Claude Code processes stopped.' . PHP_EOL;
}

// ── Unix / macOS implementation ─────────────────────────────────────────────────

// Spawn a background process tied to the session (won't die when terminal closes).
// Uses nohup on macOS/Linux.
function nohupSpawn(string $cmd, string $wd, bool $keepOpen): int
{
    $nohup = 'nohup';
    $redirect = '> /dev/null 2>&1';
    if ($keepOpen) {
        // Keep a terminal window open (macOS Terminal / xterm)
        if (isMac()) {
            $escapedCmd = str_replace('"', '\\"', $cmd);
            $full = "osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($wd) . " && " . $escapedCmd . " && sleep 86400\"' 2>/dev/null";
            exec($full, $out, $rc);
            return 0; // Terminal doesn't give us a PID
        }
        // Linux: try x-terminal-emulator or konsole/gnome-terminal
        $terminals = ['x-terminal-emulator', 'konsole', 'gnome-terminal', 'xfce4-terminal', 'alacritty'];
        foreach ($terminals as $term) {
            if (commandExists($term)) {
                $escapedCmd = str_replace('"', '\\"', $cmd);
                $full = "$term -e 'bash -c " . escapeshellarg("cd " . escapeshellarg($wd) . " && " . $escapedCmd) . "' 2>/dev/null &";
                exec($full);
                return 0;
            }
        }
        // Fallback: background without visible terminal
        $redirect = '>> ' . escapeshellarg(mlHome() . '/logs') . ' 2>&1 &';
    }

    $spawnCmd = "$nohup $cmd $redirect";
    // Drop to background so PHP can exit
    if (isMac()) {
        $final = "bash -c '$spawnCmd' &";
    } else {
        $final = "nohup $cmd $redirect & echo \\$!";
    }

    $out = [];
    exec($final, $out, $rc);

    // Extract PID from & echo $! output if available
    foreach ($out as $line) {
        $line = trim($line);
        if (is_numeric($line)) {
            return (int)$line;
        }
    }
    return 0;
}

function commandExists(string $cmd): bool
{
    $out = [];
    $rc = 1;
    if (isWindows()) {
        exec('where ' . escapeshellarg($cmd) . ' >NUL 2>&1', $out, $rc);
    } else {
        exec('command -v ' . escapeshellarg($cmd) . ' >/dev/null 2>&1', $out, $rc);
    }
    return $rc === 0;
}

function startAiUnix(bool $uvicornVisible, bool $claudeVisible): void
{
    ensureInstalled();

    $installDir = aiInstallDir();
    $envSetup = 'cd ' . escapeshellarg($installDir);
    $stateFile = mlHome() . DIRECTORY_SEPARATOR . 'ml-ai-pids.txt';

    // Ensure logs dir exists
    $logDir = mlHome() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $state = [
        'started_at' => date(DATE_ATOM),
        'pids'       => [],
    ];

    // Start uvicorn
    $uvicornCmd = 'cd ' . escapeshellarg($installDir) . ' && uv run uvicorn server:app --host 0.0.0.0 --port 8082';
    $pidFile = sys_get_temp_dir() . '/ml-ai-uvicorn.pid';

    if (isMac()) {
        // macOS: use launchd or just background with nohup + Terminal
        if ($uvicornVisible) {
            exec("osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($installDir) . " && uv run uvicorn server:app --host 0.0.0.0 --port 8082\"' 2>/dev/null");
            $uvicornPid = 0;
        } else {
            exec("cd " . escapeshellarg($installDir) . " && nohup uv run uvicorn server:app --host 0.0.0.0 --port 8082 > " . escapeshellarg($logDir . '/uvicorn.log') . " 2>&1 &");
            $uvicornPid = (int)@exec("pgrep -f 'uvicorn server:app.*8082' | head -1");
        }
    } else {
        $uvicornCmd = "cd " . escapeshellarg($installDir) . " && uv run uvicorn server:app --host 0.0.0.0 --port 8082 > " . escapeshellarg($logDir . '/uvicorn.log') . " 2>&1 &";
        exec($uvicornCmd);
        $uvicornPid = (int)@exec("pgrep -f 'uvicorn server:app.*8082' | tail -1");
    }

    if ($uvicornPid > 0) {
        $state['pids'][] = $uvicornPid;
        @file_put_contents($pidFile, (string)$uvicornPid);
    }

    echo "CLI: uvicorn started (PID $uvicornPid)." . PHP_EOL;
    sleep(2);

    // Start Claude Code
    $claudeCmdFull = 'cd ' . escapeshellarg($installDir) . ' && ANTHROPIC_AUTH_TOKEN=freecc ANTHROPIC_BASE_URL=http://localhost:8082 claude';

    if ($claudeVisible) {
        if (isMac()) {
            exec("osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($installDir) . " && ANTHROPIC_AUTH_TOKEN=freecc ANTHROPIC_BASE_URL=http://localhost:8082 claude\"' 2>/dev/null");
        } else {
            // Try to find a terminal emulator on Linux
            $terminals = ['konsole', 'gnome-terminal', 'xfce4-terminal'];
            $found = false;
            foreach ($terminals as $term) {
                if (commandExists($term)) {
                    $escaped = str_replace('"', '\\"', $claudeCmdFull);
                    if ($term === 'konsole') {
                        exec("$term -e 'bash -c " . escapeshellarg($claudeCmdFull) . "' 2>/dev/null &");
                    } else {
                        exec("$term -- bash -c " . escapeshellarg($claudeCmdFull) . " 2>/dev/null &");
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Fallback: run in background
                exec("nohup bash -c " . escapeshellarg($claudeCmdFull) . " >> " . escapeshellarg($logDir . '/claude.log') . " 2>&1 &");
            }
        }
        $claudePid = 0;
    } else {
        $claudeLogFile = $logDir . '/claude.log';
        exec("nohup bash -c " . escapeshellarg($claudeCmdFull) . " >> " . escapeshellarg($claudeLogFile) . " 2>&1 &");
        $claudePid = (int)@exec("pgrep -f 'ANTHROPIC_BASE_URL=http://localhost:8082 claude|claude' | tail -1");
        if ($claudePid > 0) {
            $state['pids'][] = $claudePid;
        }
    }

    saveState($state);
    echo "CLI: Free Claude Code processes started." . PHP_EOL;
}

function stopAiUnix(): void
{
    $state = loadState();
    $pids = $state['pids'] ?? [];

    // Kill tracked PIDs
    foreach ($pids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            } else {
                exec('kill ' . (int)$pid . ' 2>/dev/null');
            }
            echo "Sent SIGTERM to PID $pid." . PHP_EOL;
        }
    }

    // Also kill by process name patterns
    $patterns = [
        'uvicorn server:app',
        'ANTHROPIC_BASE_URL=http://localhost:8082 claude',
        'ml-ai-uvicorn',
    ];

    foreach ($patterns as $pattern) {
        exec("pkill -f " . escapeshellarg($pattern) . " 2>/dev/null");
    }

    // Clean up pid files
    $pidFile = sys_get_temp_dir() . '/ml-ai-uvicorn.pid';
    @unlink($pidFile);

    if (is_file(aiStateFile())) {
        @unlink(aiStateFile());
    }

    echo "CLI: Free Claude Code processes stopped." . PHP_EOL;
}

// ── Main dispatch ───────────────────────────────────────────────────────────────

if (is_dir(mlHome()) && !is_dir(aiInstallDir())) {
    fwrite(STDERR, 'Free-Claude-Code is not installed.' . PHP_EOL);
    fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
    exit(2);
}

$subcommand = strtolower(trim((string)($argv[1] ?? '')));

switch ($subcommand) {
    case '':
        if (isWindows()) {
            startAiWindows(true, true);
        } else {
            startAiUnix(true, true);
        }
        exit(0);

    case 'claude':
        if (isWindows()) {
            startAiWindows(false, true);
        } else {
            startAiUnix(false, true);
        }
        exit(0);

    case 'bg':
        if (isWindows()) {
            startAiWindows(false, false);
        } else {
            startAiUnix(false, false);
        }
        exit(0);

    case 'stop':
        if (isWindows()) {
            stopAiWindows();
        } else {
            stopAiUnix();
        }
        exit(0);

    case 'restart':
        if (isWindows()) {
            stopAiWindows();
            startAiWindows(false, false);
        } else {
            stopAiUnix();
            startAiUnix(false, false);
        }
        exit(0);

    case 'cm':
        changeModel();
        exit(0);

    case 'key':
        changeApiKey();
        exit(0);

    default:
        fwrite(STDERR, 'Unknown ml --ai subcommand: ' . $subcommand . PHP_EOL);
        fwrite(STDERR, 'Use: ml --ai [claude|bg|stop|restart|cm|key]' . PHP_EOL);
        exit(2);
}
