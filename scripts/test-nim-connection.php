<?php
/**
 * scripts/test-nim-connection.php
 *
 * Step-by-step diagnostic for NVIDIA NIM API connectivity.
 * Run: php scripts/test-nim-connection.php
 *
 * NOTE: Run step-by-step checks first so you can stop early
 *       if a lower-level issue is found (e.g. no internet, port blocked).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from terminal.\n");
    exit(1);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function out(string $msg): void  { echo "  $msg\n"; }
function section(string $msg): void {
    echo "\n════ $msg ════\n";
}
function ok(string $msg): void   { echo "\033[32m  [OK]   \033[0m$msg\n"; }
function fail(string $msg): void { echo "\033[31m  [FAIL] \033[0m$msg\n"; }
function warn(string $msg): void { echo "\033[33m  [WARN] \033[0m$msg\n"; }
function step(string $msg): void { echo "\n  -- $msg --\n"; }
function ask(string $prompt): string {
    echo "$prompt: ";
    $line = fgets(STDIN);
    return trim((string) $line);
}

echo "\n";
echo "==========================================\n";
echo "  NVIDIA NIM API — Connection Diagnostic\n";
echo "==========================================\n";

// ── Config / API key ──────────────────────────────────────────────────────────
section("CONFIG — API Key");
$configPath = 'C:\ML CLI\Tools\mlcli-config.json';
$apiKey = '';
$keySource = '';

if (is_file($configPath)) {
    $cfg = json_decode((string) file_get_contents($configPath), true);
    if (!empty($cfg['nvidia_api_key'])) {
        $apiKey   = $cfg['nvidia_api_key'];
        $keySource = "config file ($configPath)";
        ok("API key found: " . substr($apiKey, 0, 8) . "......");
    }
}
if ($apiKey === '') {
    warn("No 'nvidia_api_key' in config.");
    $apiKey = ask('Enter NVIDIA API key to test the full endpoint');
    if ($apiKey === '') { exit(0); }
    $keySource = 'manual entry';
}

// ── PHP cURL extension ────────────────────────────────────────────────────────
section("STEP 1 — PHP cURL Extension");
if (!function_exists('curl_init')) {
    fail("curl_init() not available — enable php_curl in php.ini");
    exit(1);
}
ok("cURL extension loaded.");
$v = curl_version();
ok("cURL {$v['version']}  |  SSL: {$v['ssl_version']}");

// ── DNS resolution ────────────────────────────────────────────────────────────
section("STEP 2 — DNS Resolution");
$host = 'integrate.api.nvidia.com';
$ip = gethostbyname($host);
if ($ip === $host) {
    fail("Could not resolve: $host");
    warn("  -> Check your internet connection / DNS settings");
} else {
    ok("Resolved: $host -> $ip");
}

// ── TCP socket (port 443) ────────────────────────────────────────────────────
section("STEP 3 — TCP Connection :443");
$port = 443;
$errno = 0; $errstr = '';
$conn = @fsockopen($host, $port, $errno, $errstr, 8);
if ($conn) {
    ok("TCP handshake OK on port 443.");
    fclose($conn);
} else {
    fail("Cannot reach $host:$port  [errno=$errno] $errstr");
    warn("  -> Likely a firewall / antivirus blocking outbound :443");
    warn("  -> Check Windows Firewall, Windows Defender, or corporate proxy");
}

// ── Basic HTTPS GET (root path, no body) ─────────────────────────────────────
section("STEP 4 — HTTPS GET https://$host/");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$host/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$start = microtime(true);
$r = curl_exec($ch);
$elapsed = round((microtime(true) - $start) * 1000);
$code = curl_errno($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($code !== 0) {
    fail("HTTPS request failed — curl [$code]: $err");

    // SSL/cert diagnostics
    if (stripos($err, 'SSL') !== false || stripos($err, 'certificate') !== false) {
        warn("SSL certificate error — trying with verification OFF...");
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, "https://$host/");
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_NOBODY, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
        $r2 = curl_exec($ch2);
        $code2 = curl_errno($ch2);
        curl_close($ch2);

        if ($code2 === 0) {
            ok("HTTPS works with SSL verification disabled — root CA store issue on this machine.");
            warn("  Fix: Download cacert.pem and set curl.cainfo in php.ini");
            warn("  Download: https://curl.se/ca/cacert.pem");
            warn("  Add to php.ini: curl.cainfo=C:\\path\\to\\cacert.pem");
        } else {
            fail("Still fails even with SSL off — network-level block.");
        }
    }
} else {
    ok("HTTPS root request OK in {$elapsed}ms.");
}

// ── NIM /chat/completions endpoint ────────────────────────────────────────────
section("STEP 5 — NIM /v1/chat/completions (full test)");
$url = "https://$host/v1/chat/completions";
out("URL  : $url");
out("Auth : Bearer " . substr($apiKey, 0, 8) . "...");

$payload = [
    'model'      => 'nvidia/llama-3.1-nemotron-nano-8b-v1',
    'messages'   => [
        ['role' => 'system', 'content' => 'Reply with exactly {"ok":true}'],
        ['role' => 'user',   'content' => 'Ping'],
    ],
    'max_tokens' => 10,
    'stream'     => false,
    'temperature'=> 0,
];

$ch3 = curl_init();
curl_setopt($ch3, CURLOPT_URL, $url);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch3, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
]);
curl_setopt($ch3, CURLOPT_TIMEOUT, 60);
curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);

// Capture verbose details
$vb = fopen('php://temp', 'w+');
curl_setopt($ch3, CURLOPT_STDERR, $vb);

$t0 = microtime(true);
$response = curl_exec($ch3);
$t1 = microtime(true);
$elapsedMs  = round(($t1 - $t0) * 1000);
$httpCode   = (int) curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$curlCode   = curl_errno($ch3);
$curlErr    = curl_error($ch3);
$totalTime  = curl_getinfo($ch3, CURLINFO_TOTAL_TIME);

rewind($vb);
$verbose = stream_get_contents($vb);
fclose($vb);
curl_close($ch3);

echo "\n  HTTP status : $httpCode\n";
echo "  cURL code   : $curlCode\n";
echo "  cURL err    : $curlErr\n";
echo "  Time        : {$elapsedMs}ms (connect + response)\n\n";

if ($curlCode !== 0) {
    fail("cURL error [$curlCode]: $curlErr");
    echo "  --- cURL verbose ---\n";
    echo implode('', array_map(fn($l) => "    $l", explode("\n", rtrim($verbose))));
    echo "\n";
} elseif ($httpCode >= 400) {
    $body = json_decode($response, true);
    $errMsg = $body['error']['message'] ?? $body['error']['type'] ?? $response;
    fail("HTTP $httpCode from NIM API");
    out("  Error: $errMsg");

    $hints = [
        401 => "Invalid API key — check https://build.nvidia.com/",
        403 => "Forbidden — key may lack permissions for this model",
        429 => "Rate limited — wait and retry",
        500 => "NVIDIA server error — check status.nvidia.com",
    ];
    if (isset($hints[$httpCode])) {
        warn("  Hint: " . $hints[$httpCode]);
    }
} else {
    ok("NIM API responded HTTP $httpCode in {$elapsedMs}ms.");
    $body = json_decode($response, true);
    $reply = $body['choices'][0]['message']['content'] ?? '(no content field)';
    out("  AI reply: " . trim($reply));
}

// ── Summary ────────────────────────────────────────────────────────────────────
section("SUMMARY");
echo "  Config used  : $keySource\n";
echo "  API key      : " . substr($apiKey, 0, 8) . "...\n";
echo "  Host resolved: " . (($ip !== $host) ? "YES -> $ip" : "NO") . "\n";
echo "  Port 443     : " . (($conn !== false) ? "OPEN" : "BLOCKED/FILTERED") . "\n";
echo "  HTTPS GET    : " . (($code === 0) ? "OK" : "FAILED") . "\n";
echo "  NIM endpoint : " . (($curlCode === 0 && $httpCode < 400) ? "OK" : "FAILED") . "\n";

echo "\n";
if ($curlCode !== 0 && $code === 0) {
    echo "  LIKELY CAUSE: cURL timeout or DNS at step 5.\n";
    echo "  -> NIM servers may be slow; increase CURLOPT_TIMEOUT.\n";
    echo "  -> Try the same API from curl.exe to confirm NIM itself works.\n";
} elseif ($code !== 0) {
    echo "  LIKELY CAUSE: SSL/TLS handshake failed at step 4.\n";
    echo "  -> Windows root CA store may be outdated/missing.\n";
    echo "  -> Fix: Download cacert.pem, set curl.cainfo in php.ini.\n";
    echo "  -> Download: https://curl.se/ca/cacert.pem\n";
    echo "  -> php.ini entry: curl.cainfo=C:\\path\\to\\cacert.pem\n";
} elseif ($curlCode === 0 && $httpCode >= 400) {
    echo "  LIKELY CAUSE: Bad API key or NIM account issue.\n";
    echo "  -> Visit https://build.nvidia.com/ and verify your key is active.\n";
} else {
    ok("All checks passed — NIM API should work from sidebar-add-menu.php");
}
echo "\n";