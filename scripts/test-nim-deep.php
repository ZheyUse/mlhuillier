<?php
/**
 * scripts/test-nim-deep.php
 *
 * Deep diagnostic on the NIM /v1/chat/completions endpoint.
 * Verbose cURL logging + longer timeout + connection diagnostics.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

$configPath = 'C:\ML CLI\Tools\mlcli-config.json';
$cfg = json_decode((string) file_get_contents($configPath), true);
$apiKey = $cfg['nvidia_api_key'] ?? '';

if (!$apiKey) {
    echo "No API key found.\n";
    exit(1);
}

$url = 'https://integrate.api.nvidia.com/v1/chat/completions';
$payload = [
    'model'      => 'nvidia/llama-3.1-nemotron-nano-8b-v1',
    'messages'   => [
        ['role' => 'system', 'content' => 'Reply ONLY with JSON.'],
        ['role' => 'user',   'content' => 'Say hello in JSON'],
    ],
    'max_tokens' => 20,
    'stream'     => false,
    'temperature'=> 0,
];

echo "\nTarget : $url\n";
echo "Key    : " . substr($apiKey, 0, 12) . "...\n\n";

// ── Test A: Minimal OPTIONS request (CORS/preflight check) ─────────────────────
echo "== A: OPTIONS request (preflight/CORS) ==\n";
$chA = curl_init();
curl_setopt($chA, CURLOPT_URL, $url);
curl_setopt($chA, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($chA, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chA, CURLOPT_NOBODY, true);
curl_setopt($chA, CURLOPT_TIMEOUT, 15);
curl_setopt($chA, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($chA, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Access-Control-Request-Method: POST',
]);
$vbA = fopen('php://temp', 'w+');
curl_setopt($chA, CURLOPT_STDERR, $vbA);
$t0 = microtime(true);
$rA = curl_exec($chA);
$elA = round((microtime(true) - $t0) * 1000);
$codeA = curl_errno($chA);
$httpA = curl_getinfo($chA, CURLINFO_HTTP_CODE);
$errA  = curl_error($chA);
rewind($vbA); $logA = stream_get_contents($vbA); fclose($vbA);
curl_close($chA);
echo "  Time: {$elA}ms | cURL: $codeA ($errA) | HTTP: $httpA\n";
if ($codeA !== 0) {
    echo "  FAIL: $errA\n";
    echo "  Log: " . trim($logA) . "\n";
} else {
    echo "  OK\n";
}

// ── Test B: POST with explicit HOST header ─────────────────────────────────────
echo "\n== B: POST with explicit Host header ==\n";
$chB = curl_init();
curl_setopt($chB, CURLOPT_URL, $url);
curl_setopt($chB, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chB, CURLOPT_POST, true);
curl_setopt($chB, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($chB, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'Host: integrate.api.nvidia.com',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) curl/8.7.0',
    'Accept: application/json',
]);
curl_setopt($chB, CURLOPT_TIMEOUT, 90);
curl_setopt($chB, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($chB, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($chB, CURLOPT_SSL_VERIFYHOST, 2);
$vbB = fopen('php://temp', 'w+');
curl_setopt($chB, CURLOPT_STDERR, $vbB);
$t0 = microtime(true);
$rB = curl_exec($chB);
$elB = round((microtime(true) - $t0) * 1000);
$codeB = curl_errno($chB);
$httpB = curl_getinfo($chB, CURLINFO_HTTP_CODE);
$errB  = curl_error($chB);
rewind($vbB); $logB = stream_get_contents($vbB); fclose($vbB);
curl_close($chB);

echo "  Time: {$elB}ms | cURL: $codeB | HTTP: $httpB\n";
if ($codeB !== 0) {
    echo "  FAIL: $errB\n";
    echo "  Log:\n" . implode("\n", array_slice(explode("\n", trim($logB)), 0, 30)) . "\n";
} elseif ($httpB >= 400) {
    $body = json_decode($rB, true);
    echo "  HTTP $httpB — Error: " . json_encode($body['error'] ?? $rB) . "\n";
} else {
    $body = json_decode($rB, true);
    echo "  OK: " . trim($body['choices'][0]['message']['content'] ?? '(no content)') . "\n";
}

// ── Test C: Same via curl.exe command ─────────────────────────────────────────
echo "\n== C: Same POST via curl.exe (native binary) ==\n";
$curlCmd = sprintf(
    'curl -s -X POST "%s" -H "Content-Type: application/json" -H "Authorization: Bearer %s" -d \'%s\' --max-time 90',
    $url,
    $apiKey,
    json_encode($payload)
);
echo "  Command: $curlCmd\n";
exec($curlCmd . ' 2>&1', $curlOutC, $curlRetC);
$curlResult = implode("\n", $curlOutC);
echo "  Exit code: $curlRetC\n";
if ($curlRetC !== 0) {
    echo "  FAIL:\n  " . substr($curlResult, 0, 300) . "\n";
} else {
    $decoded = json_decode($curlResult, true);
    echo "  OK: " . trim($decoded['choices'][0]['message']['content'] ?? $curlResult) . "\n";
}

// ── Test D: GET available models (no body) ────────────────────────────────────
echo "\n== D: GET /v1/models (read-only, no body) ==\n";
$chD = curl_init();
curl_setopt($chD, CURLOPT_URL, 'https://integrate.api.nvidia.com/v1/models');
curl_setopt($chD, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chD, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
]);
curl_setopt($chD, CURLOPT_TIMEOUT, 30);
curl_setopt($chD, CURLOPT_CONNECTTIMEOUT, 15);
$vbD = fopen('php://temp', 'w+');
curl_setopt($chD, CURLOPT_STDERR, $vbD);
$t0 = microtime(true);
$rD = curl_exec($chD);
$elD = round((microtime(true) - $t0) * 1000);
$codeD = curl_errno($chD);
$httpD = curl_getinfo($chD, CURLINFO_HTTP_CODE);
$errD  = curl_error($chD);
rewind($vbD); $logD = stream_get_contents($vbD); fclose($vbD);
curl_close($chD);
echo "  Time: {$elD}ms | cURL: $codeD | HTTP: $httpD\n";
if ($codeD !== 0) {
    echo "  FAIL: $errD\n";
} elseif ($httpD >= 400) {
    echo "  HTTP $httpD\n";
} else {
    $body = json_decode($rD, true);
    $models = $body['data'] ?? [];
    $names = array_slice(array_column($models, 'id'), 0, 10);
    echo "  OK — models: " . implode(', ', $names) . "\n";
}

// ── Test E: POST with very short timeout to see WHERE it blocks ───────────────
echo "\n== E: POST (1s timeout) — detect WHERE it stalls ==\n";
$chE = curl_init();
curl_setopt($chE, CURLOPT_URL, $url);
curl_setopt($chE, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chE, CURLOPT_POST, true);
curl_setopt($chE, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($chE, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
]);
curl_setopt($chE, CURLOPT_TIMEOUT, 1);
curl_setopt($chE, CURLOPT_CONNECTTIMEOUT, 1);
$vbE = fopen('php://temp', 'w+');
curl_setopt($chE, CURLOPT_STDERR, $vbE);
curl_exec($chE);
$codeE = curl_errno($chE);
$errE  = curl_error($chE);
$connTime = curl_getinfo($chE, CURLINFO_CONNECT_TIME);
$startTran = curl_getinfo($chE, CURLINFO_PRETRANSFER_TIME);
curl_close($chE);
rewind($vbE); $logE = stream_get_contents($vbE); fclose($vbE);
echo "  Conn time : " . round($connTime * 1000, 1) . "ms\n";
echo "  Pre-trans : " . round($startTran * 1000, 1) . "ms\n";
echo "  cURL code : $codeE — $errE\n";
echo "  Verbatim log (first 500 chars):\n  " . substr(trim($logE), 0, 500) . "\n";

echo "\n";