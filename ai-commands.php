<?php
// ai-commands.php
// Usage: php ai-commands.php [claude|bg|stop|restart|cm|key|codex|admin|update-info]
// Works on Windows (PowerShell), macOS, and Linux (bash/sh).

const FCC_INSTALL_DIR = 'C:\\free-claude-code\\free-claude-code';
const FCC_PROJECT_FLAG = '--project ' . FCC_INSTALL_DIR;

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

    echo 'NVIDIA_NIM_API_KEY has been set successfully' . PHP_EOL;
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

function fccServerScript(): string
{
    return '$Host.UI.RawUI.WindowTitle = "ml --ai fcc-server"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(FCC_INSTALL_DIR) . PHP_EOL .
        'uv run ' . FCC_PROJECT_FLAG . ' fcc-server' . PHP_EOL;
}

function fccClaudeScript(bool $runInCurrentDir = false): string
{
    if ($runInCurrentDir) {
        // When called via 'ml --ai claude': run fcc-claude in CURRENT directory
        // Server runs in background from FCC_INSTALL_DIR
        return '$Host.UI.RawUI.WindowTitle = "ml --ai fcc-claude"' . PHP_EOL .
            '$serverScript = [System.IO.Path]::GetTempFileName() + ".ps1"' . PHP_EOL .
            '$serverBody = @' . PHP_EOL .
            '\"$Host.UI.RawUI.WindowTitle = \'ml --ai fcc-server (bg)\'"\' + "`n" + ' . PHP_EOL .
            '"Set-Location ' . psSingleQuote(FCC_INSTALL_DIR) . '`n' . PHP_EOL .
            '"uv run ' . FCC_PROJECT_FLAG . ' fcc-server`"' . PHP_EOL .
            '@' . PHP_EOL .
            'Set-Content -Path $serverScript -Value $serverBody' . PHP_EOL .
            'Start-Process powershell.exe -ArgumentList "-NoExit","-ExecutionPolicy","Bypass","-File",$serverScript -WindowStyle Hidden' . PHP_EOL .
            'Start-Sleep -Seconds 2' . PHP_EOL .
            'uv run ' . FCC_PROJECT_FLAG . ' fcc-claude' . PHP_EOL;
    }

    // Default: both server and claude in FCC_INSTALL_DIR
    return '$Host.UI.RawUI.WindowTitle = "ml --ai fcc-claude"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(FCC_INSTALL_DIR) . PHP_EOL .
        'uv run ' . FCC_PROJECT_FLAG . ' fcc-claude' . PHP_EOL;
}

function fccCodexScript(bool $runInCurrentDir = false): string
{
    if ($runInCurrentDir) {
        // When called via 'ml --ai codex': run fcc-codex in CURRENT directory
        // Server runs in background from FCC_INSTALL_DIR
        return '$Host.UI.RawUI.WindowTitle = "ml --ai fcc-codex"' . PHP_EOL .
            '$serverScript = [System.IO.Path]::GetTempFileName() + ".ps1"' . PHP_EOL .
            '$serverBody = @' . PHP_EOL .
            '\"$Host.UI.RawUI.WindowTitle = \'ml --ai fcc-server (bg)\'"\' + "`n" + ' . PHP_EOL .
            '"Set-Location ' . psSingleQuote(FCC_INSTALL_DIR) . '`n' . PHP_EOL .
            '"uv run ' . FCC_PROJECT_FLAG . ' fcc-server`"' . PHP_EOL .
            '@' . PHP_EOL .
            'Set-Content -Path $serverScript -Value $serverBody' . PHP_EOL .
            'Start-Process powershell.exe -ArgumentList "-NoExit","-ExecutionPolicy","Bypass","-File",$serverScript -WindowStyle Hidden' . PHP_EOL .
            'Start-Sleep -Seconds 2' . PHP_EOL .
            'uv run ' . FCC_PROJECT_FLAG . ' fcc-codex' . PHP_EOL;
    }

    // Default: both server and codex in FCC_INSTALL_DIR
    return '$Host.UI.RawUI.WindowTitle = "ml --ai fcc-codex"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(FCC_INSTALL_DIR) . PHP_EOL .
        'uv run ' . FCC_PROJECT_FLAG . ' fcc-codex' . PHP_EOL;
}

function startAiWindows(bool $serverVisible, bool $claudeVisible): void
{
    ensureInstalled();

    $serverPs = writeScript('ml-ai-fcc-server', fccServerScript());
    // When server is NOT visible: run claude in current directory (claude-only mode)
    $runInCurrentDir = !$serverVisible;
    $claudePs  = writeScript('ml-ai-fcc-claude', fccClaudeScript($runInCurrentDir));

    $state = [
        'started_at' => date(DATE_ATOM),
        'scripts'   => [$serverPs, $claudePs],
        'pids'      => [],
    ];

    $serverPid = startPowerShellScript($serverPs, $serverVisible);
    if ($serverPid > 0) {
        $state['pids'][] = $serverPid;
    }

    sleep(2);

    $claudePid = startPowerShellScript($claudePs, $claudeVisible);
    if ($claudePid > 0) {
        $state['pids'][] = $claudePid;
    }

    saveState($state);
    echo 'CLI: Free Claude Code processes started.' . PHP_EOL;
}

function startCodexWindows(bool $visible): void
{
    ensureInstalled();

    // Always run codex in current directory, server in background
    $codexPs = writeScript('ml-ai-fcc-codex', fccCodexScript(true));

    $state = [
        'started_at' => date(DATE_ATOM),
        'scripts'   => [$codexPs],
        'pids'      => [],
    ];

    $codexPid = startPowerShellScript($codexPs, $visible);
    if ($codexPid > 0) {
        $state['pids'][] = $codexPid;
    }

    saveState($state);
    echo 'CLI: Free Claude Code (Codex) started.' . PHP_EOL;
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
    ($_.CommandLine -like '*fcc-server*' -and $_.CommandLine -like '*free-claude-code*') -or
    ($_.CommandLine -like '*fcc-claude*' -and $_.CommandLine -like '*free-claude-code*') -or
    ($_.CommandLine -like '*fcc-codex*' -and $_.CommandLine -like '*free-claude-code*') -or
    ($_.CommandLine -like '*ml-ai-fcc-*')
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

function fccServerCommand(): string
{
    return 'cd ' . escapeshellarg(FCC_INSTALL_DIR) . ' && uv run ' . FCC_PROJECT_FLAG . ' fcc-server';
}

function fccClaudeCommand(): string
{
    return 'cd ' . escapeshellarg(FCC_INSTALL_DIR) . ' && uv run ' . FCC_PROJECT_FLAG . ' fcc-claude';
}

function fccCodexCommand(): string
{
    return 'cd ' . escapeshellarg(FCC_INSTALL_DIR) . ' && uv run ' . FCC_PROJECT_FLAG . ' fcc-codex';
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

function startAiUnix(bool $serverVisible, bool $claudeVisible): void
{
    ensureInstalled();

    $installDir = aiInstallDir();

    $logDir = mlHome() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $state = [
        'started_at' => date(DATE_ATOM),
        'pids'       => [],
    ];

    $serverCmd = fccServerCommand();
    $serverLogFile = $logDir . '/fcc-server.log';

    if (isMac()) {
        if ($serverVisible) {
            exec("osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($installDir) . " && uv run " . FCC_PROJECT_FLAG . " fcc-server\"' 2>/dev/null");
            $serverPid = 0;
        } else {
            exec("cd " . escapeshellarg($installDir) . " && nohup uv run " . FCC_PROJECT_FLAG . " fcc-server > " . escapeshellarg($serverLogFile) . " 2>&1 &");
            $serverPid = (int)@exec("pgrep -f 'fcc-server.*free-claude-code' | head -1");
        }
    } else {
        exec("cd " . escapeshellarg($installDir) . " && nohup uv run " . FCC_PROJECT_FLAG . " fcc-server > " . escapeshellarg($serverLogFile) . " 2>&1 &");
        $serverPid = (int)@exec("pgrep -f 'fcc-server.*free-claude-code' | tail -1");
    }

    if ($serverPid > 0) {
        $state['pids'][] = $serverPid;
        @file_put_contents(sys_get_temp_dir() . '/ml-ai-fcc-server.pid', (string)$serverPid);
    }

    echo "CLI: fcc-server started (PID $serverPid)." . PHP_EOL;
    sleep(2);

    $claudeCmd = fccClaudeCommand();

    if ($claudeVisible) {
        if (isMac()) {
            exec("osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($installDir) . " && uv run " . FCC_PROJECT_FLAG . " fcc-claude\"' 2>/dev/null");
        } else {
            $terminals = ['konsole', 'gnome-terminal', 'xfce4-terminal'];
            $found = false;
            foreach ($terminals as $term) {
                if (commandExists($term)) {
                    if ($term === 'konsole') {
                        exec("$term -e 'bash -c " . escapeshellarg($claudeCmd) . "' 2>/dev/null &");
                    } else {
                        exec("$term -- bash -c " . escapeshellarg($claudeCmd) . " 2>/dev/null &");
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                exec("nohup bash -c " . escapeshellarg($claudeCmd) . " >> " . escapeshellarg($logDir . '/fcc-claude.log') . " 2>&1 &");
            }
        }
        $claudePid = 0;
    } else {
        $claudeLogFile = $logDir . '/fcc-claude.log';
        exec("nohup bash -c " . escapeshellarg($claudeCmd) . " >> " . escapeshellarg($claudeLogFile) . " 2>&1 &");
        $claudePid = (int)@exec("pgrep -f 'fcc-claude.*free-claude-code' | tail -1");
        if ($claudePid > 0) {
            $state['pids'][] = $claudePid;
        }
    }

    saveState($state);
    echo "CLI: Free Claude Code processes started." . PHP_EOL;
}

function startCodexUnix(bool $visible): void
{
    ensureInstalled();

    $installDir = aiInstallDir();
    $logDir = mlHome() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $state = [
        'started_at' => date(DATE_ATOM),
        'pids'       => [],
    ];

    $codexCmd = fccCodexCommand();

    if ($visible) {
        if (isMac()) {
            exec("osascript -e 'tell app \"Terminal\" to do script \"cd " . escapeshellarg($installDir) . " && uv run " . FCC_PROJECT_FLAG . " fcc-codex\"' 2>/dev/null");
        } else {
            $terminals = ['konsole', 'gnome-terminal', 'xfce4-terminal'];
            $found = false;
            foreach ($terminals as $term) {
                if (commandExists($term)) {
                    if ($term === 'konsole') {
                        exec("$term -e 'bash -c " . escapeshellarg($codexCmd) . "' 2>/dev/null &");
                    } else {
                        exec("$term -- bash -c " . escapeshellarg($codexCmd) . " 2>/dev/null &");
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                exec("nohup bash -c " . escapeshellarg($codexCmd) . " >> " . escapeshellarg($logDir . '/fcc-codex.log') . " 2>&1 &");
            }
        }
        $codexPid = 0;
    } else {
        $codexLogFile = $logDir . '/fcc-codex.log';
        exec("nohup bash -c " . escapeshellarg($codexCmd) . " >> " . escapeshellarg($codexLogFile) . " 2>&1 &");
        $codexPid = (int)@exec("pgrep -f 'fcc-codex.*free-claude-code' | tail -1");
        if ($codexPid > 0) {
            $state['pids'][] = $codexPid;
        }
    }

    saveState($state);
    echo "CLI: fcc-codex started." . PHP_EOL;
}

function stopAiUnix(): void
{
    $state = loadState();
    $pids = $state['pids'] ?? [];

    foreach ($pids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, 15);
            } else {
                exec('kill ' . (int)$pid . ' 2>/dev/null');
            }
            echo "Sent SIGTERM to PID $pid." . PHP_EOL;
        }
    }

    $patterns = [
        'fcc-server.*free-claude-code',
        'fcc-claude.*free-claude-code',
        'fcc-codex.*free-claude-code',
        'uv run.*fcc-',
    ];

    foreach ($patterns as $pattern) {
        exec("pkill -f " . escapeshellarg($pattern) . " 2>/dev/null");
    }

    @unlink(sys_get_temp_dir() . '/ml-ai-fcc-server.pid');

    if (is_file(aiStateFile())) {
        @unlink(aiStateFile());
    }

    echo "CLI: Free Claude Code processes stopped." . PHP_EOL;
}

// ── Admin ─────────────────────────────────────────────────────────────────────

function openAdminBrowser(): void
{
    ensureInstalled();

    $url = 'http://127.0.0.1:8082/admin';

    if (isWindows()) {
        exec('start "" ' . escapeshellarg($url) . ' 2>nul');
    } elseif (isMac()) {
        exec('open ' . escapeshellarg($url) . ' 2>/dev/null');
    } else {
        exec('xdg-open ' . escapeshellarg($url) . ' 2>/dev/null');
    }

    echo 'CLI: Opening ' . $url . ' in browser.' . PHP_EOL;
}

// ── Update Info ───────────────────────────────────────────────────────────────

function getStderrRedirect(): string
{
    return isWindows() ? '2>nul' : '2>/dev/null';
}

function checkGitUpdates(): void
{
    ensureInstalled();

    $installDir = aiInstallDir();
    $stderr = getStderrRedirect();

    echo 'Checking for updates...' . PHP_EOL;
    echo PHP_EOL;

    $out = [];
    exec('git -C ' . escapeshellarg($installDir) . ' fetch origin 2>&1', $out, $rc);

    $branch = trim((string)@exec('git -C ' . escapeshellarg($installDir) . ' branch --show-current ' . $stderr));
    if (empty($branch)) {
        $branch = 'main';
    }

    $revList = trim((string)@exec('git -C ' . escapeshellarg($installDir) . ' rev-list --left-right --count origin/' . escapeshellarg($branch) . '...HEAD ' . $stderr));

    echo 'Branch: ' . $branch . PHP_EOL;
    echo 'Remote: origin' . PHP_EOL;
    echo PHP_EOL;

    if ($revList !== '' && $revList !== false) {
        $parts = explode("\t", $revList);
        $behind = (int)($parts[0] ?? 0);
        $ahead = (int)($parts[1] ?? 0);

        echo 'Commits behind: ' . $behind . PHP_EOL;
        echo 'Commits ahead: ' . $ahead . PHP_EOL;
        echo PHP_EOL;

        if ($behind > 0) {
            echo 'Updates available:' . PHP_EOL;
            $logOut = [];
            exec('git -C ' . escapeshellarg($installDir) . ' log HEAD..origin/' . escapeshellarg($branch) . ' --oneline 2>&1', $logOut);
            foreach ($logOut as $line) {
                echo '  ' . trim($line) . PHP_EOL;
            }
            echo PHP_EOL;
            echo 'Run: ml --ai update' . PHP_EOL;
        } else {
            echo 'You are up to date. No changes to pull.' . PHP_EOL;
        }
    } else {
        echo 'Unable to determine update status.' . PHP_EOL;
        echo 'Tracking: origin/' . $branch . PHP_EOL;
    }

    echo PHP_EOL;
}

// ── Main dispatch ───────────────────────────────────────────────────────────────

if (is_dir(mlHome()) && !is_dir(aiInstallDir())) {
    fwrite(STDERR, 'Free-Claude-Code is not installed.' . PHP_EOL);
    fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
    exit(2);
}

$subcommand = strtolower(trim((string)($argv[1] ?? '')));
if ($subcommand !== '') {
    $subcommand = ltrim($subcommand, '-');
}

switch ($subcommand) {
    case '':
        if (isWindows()) {
            startAiWindows(true, true);
        } else {
            startAiUnix(true, true);
        }
        exit(0);

    case 'bg':
        if (isWindows()) {
            startAiWindows(false, false);
        } else {
            startAiUnix(false, false);
        }
        exit(0);

    case 'claude':
        if (isWindows()) {
            startAiWindows(false, true);
        } else {
            startAiUnix(false, true);
        }
        exit(0);

    case 'codex':
        if (isWindows()) {
            startCodexWindows(true);
        } else {
            startCodexUnix(true);
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

    case 'admin':
        openAdminBrowser();
        exit(0);

    case 'update-info':
        checkGitUpdates();
        exit(0);

    default:
        fwrite(STDERR, 'Unknown ml --ai subcommand: ' . $subcommand . PHP_EOL);
        fwrite(STDERR, 'Use: ml --ai [claude|bg|codex|stop|restart|cm|key|admin|update-info]' . PHP_EOL);
        exit(2);
}