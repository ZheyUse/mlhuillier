<?php
/**
 * scripts/test-nim-fix.php
 *
 * Narrow down exactly what makes the NIM POST work in Test B
 * (9s, HTTP 200) vs timeout in the original script.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

$configPath = 'C:\ML CLI\Tools\mlcli-config.json';
$cfg = json_decode((string) file_get_contents($configPath), true);
$apiKey = $cfg['nvidia_api_key'] ?? '';
$url = 'https://integrate.api.nvidia.com/v1/chat/completions';

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

/**
 * Run one test variant and return (httpCode, elapsedMs, curlCode, curlErr, body)
 */
function run(string $label, array $opts): array {
    $defaults = [
        CURLOPT_URL => $GLOBALS['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($GLOBALS['payload']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $GLOBALS['apiKey'],
        ],
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $ch = curl_init();
    curl_setopt_array($ch, $defaults);
    curl_setopt_array($ch, $opts);

    $vb = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $vb);

    $t0 = microtime(true);
    $body = curl_exec($ch);
    $el = round((microtime(true) - $t0) * 1000);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlCode = curl_errno($ch);
    $curlErr  = curl_error($ch);

    rewind($vb); $log = stream_get_contents($vb); fclose($vb);
    curl_close($ch);

    $result = ['label' => $label, 'ms' => $el, 'http' => $httpCode,
               'curl' => $curlCode, 'err' => $curlErr, 'body' => $body, 'log' => $log];
    $mark = ($curlCode === 0 && $httpCode < 400) ? '[OK]' : '[FAIL]';
    printf("  %-6s %-45s %5dms  HTTP %d  curl[%d] %s\n",
        $mark, $label, $el, $httpCode, $curlCode,
        $curlCode ? $curlErr : '');
    return $result;
}

echo "\n";
echo "=================================================\n";
echo "  NIM POST — Fix Isolation Tests\n";
echo "=================================================\n\n";

// ── Original sidebar-add-menu.php settings (baseline) ─────────────────────────
run("ORIGINAL (no extras)", []);

// ── + Host header ─────────────────────────────────────────────────────────────
run("+ Host header", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
    ],
]);

// ── + Keep-Alive header ───────────────────────────────────────────────────────
run("+ Host + Keep-Alive", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
        'Connection: keep-alive',
    ],
]);

// ── + Accept: application/json ────────────────────────────────────────────────
run("+ Host + Accept: application/json", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
        'Accept: application/json',
    ],
]);

// ── + User-Agent (curl style) ──────────────────────────────────────────────────
run("+ Host + UA = curl", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
        'User-Agent: curl/8.7.0',
    ],
]);

// ── TCP_NODELAY (disable Nagle) ────────────────────────────────────────────────
run("+ Host + TCP_NODELAY", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
    ],
    CURLOPT_TCP_NODELAY => true,
]);

// ── No SSL_VERIFYHOST ─────────────────────────────────────────────────────────
run("+ Host + SSL_VERIFYHOST=0", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
    ],
    CURLOPT_SSL_VERIFYHOST => 0,
]);

// ── POST withoutExpect (no 100-Continue) ───────────────────────────────────────
run("+ Host + Expect: (empty)", [
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
        'Expect:',             // disables 100-Continue
    ],
]);

// ── PUT instead of POST ─────────────────────────────────────────────────────────
run("CUSTOMREQUEST PUT (+ Host)", [
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
    ],
]);

echo "\n";
echo "  If ONLY '+ Host header' passes but original fails:\n";
echo "  -> Add 'Host: integrate.api.nvidia.com' to sidebar-add-menu.php\n";
echo "\n";