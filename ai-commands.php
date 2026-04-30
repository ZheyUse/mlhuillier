<?php
// ai-commands.php
// Usage: php ai-commands.php [claude|bg|stop|restart]

const AI_INSTALL_DIR = 'C:\\free-claude-code\\free-claude-code';
const AI_STATE_FILE = 'C:\\free-claude-code\\ml-ai-pids.json';

function isWindows(): bool
{
    return stripos(PHP_OS, 'WIN') === 0;
}

function ensureInstalled(): void
{
    if (!is_dir(AI_INSTALL_DIR)) {
        fwrite(STDERR, 'Free-Claude-Code is not installed.' . PHP_EOL);
        fwrite(STDERR, 'Run: ml install ai' . PHP_EOL);
        exit(2);
    }
}

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

function loadState(): array
{
    if (!is_file(AI_STATE_FILE)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents(AI_STATE_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function saveState(array $state): void
{
    $dir = dirname(AI_STATE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(AI_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT) . PHP_EOL);
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
        'Set-Location ' . psSingleQuote(AI_INSTALL_DIR) . PHP_EOL .
        'uv run uvicorn server:app --host 0.0.0.0 --port 8082' . PHP_EOL;
}

function claudeScript(): string
{
    return '$Host.UI.RawUI.WindowTitle = "ml --ai claude"' . PHP_EOL .
        '$env:ANTHROPIC_AUTH_TOKEN = "freecc"' . PHP_EOL .
        '$env:ANTHROPIC_BASE_URL = "http://localhost:8082"' . PHP_EOL .
        'Set-Location ' . psSingleQuote(AI_INSTALL_DIR) . PHP_EOL .
        'claude' . PHP_EOL;
}

function startAi(bool $uvicornVisible, bool $claudeVisible): void
{
    ensureInstalled();

    $uvicornPs = writeScript('ml-ai-uvicorn', uvicornScript());
    $claudePs = writeScript('ml-ai-claude', claudeScript());

    $state = [
        'started_at' => date(DATE_ATOM),
        'scripts' => [$uvicornPs, $claudePs],
        'pids' => [],
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

function stopAi(): void
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
    if (is_file(AI_STATE_FILE)) {
        @unlink(AI_STATE_FILE);
    }

    echo 'CLI: Free Claude Code processes stopped.' . PHP_EOL;
}

if (!isWindows()) {
    fwrite(STDERR, 'ml --ai is currently configured for Windows.' . PHP_EOL);
    exit(2);
}

$subcommand = strtolower(trim((string)($argv[1] ?? '')));

switch ($subcommand) {
    case '':
        startAi(true, true);
        exit(0);

    case 'claude':
        startAi(false, true);
        exit(0);

    case 'bg':
        startAi(false, false);
        exit(0);

    case 'stop':
        stopAi();
        exit(0);

    case 'restart':
        stopAi();
        startAi(false, false);
        exit(0);

    default:
        fwrite(STDERR, 'Unknown ml --ai subcommand: ' . $subcommand . PHP_EOL);
        fwrite(STDERR, 'Use: ml --ai [claude|bg|stop|restart]' . PHP_EOL);
        exit(2);
}
