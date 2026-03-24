<?php
declare(strict_types=1);
$base = __DIR__ . '/../';
$tplFile = $base . 'generate-file-structure.php';
if (!file_exists($tplFile)) {
    fwrite(STDERR, "templates file not found: $tplFile\n");
    exit(1);
}
$code = file_get_contents($tplFile);
$needle = 'return [';
$pos = strrpos($code, $needle); // find the last return [ which holds the templates array
if ($pos === false) {
    fwrite(STDERR, "Could not find return [ in generate-file-structure.php\n");
    exit(1);
}
$start = $pos + strlen('return ');
$len = strlen($code);
$depth = 0;
$inString = false;
$stringChar = '';
$heredoc = false;
$heredocLabel = null;
$endPos = null;
for ($i = $start; $i < $len; $i++) {
    $ch = $code[$i];
    $next2 = substr($code, $i, 3);
    // handle heredoc start
    if (!$inString && !$heredoc && preg_match('/<<<\'?(\w+)\'?/', substr($code, $i, 64), $m)) {
        $heredoc = true;
        $heredocLabel = $m[1];
        // advance index to end of that marker
        $i += strpos(substr($code, $i), "\n");
        continue;
    }
    if ($heredoc) {
        // look for end label at line start
        $rest = substr($code, $i);
        if (preg_match("/^(?:\\r?\\n)?$heredocLabel;?\\r?\\n/m", $rest, $mm)) {
            // move i to end of label line
            $i += strlen($mm[0]) - 1;
            $heredoc = false;
            $heredocLabel = null;
            continue;
        }
        continue;
    }
    if ($inString) {
        if ($ch === $stringChar && $code[$i-1] !== '\\') {
            $inString = false;
            $stringChar = '';
        }
        continue;
    }
    if ($ch === '"' || $ch === "'") {
        $inString = true;
        $stringChar = $ch;
        continue;
    }
    if ($ch === '[') { $depth++; }
    if ($ch === ']') { $depth--; if ($depth === 0) { $endPos = $i; break; } }
}
if ($endPos === null) {
    fwrite(STDERR, "Unable to locate end of return array\n");
    exit(1);
}
$arrayCode = substr($code, $start, $endPos - $start + 1);
// Build a PHP eval wrapper
$evalCode = "<?php\nreturn " . $arrayCode . ";\n";
// Evaluate in isolated scope
$tpl = null;
try {
    $tpl = eval($evalCode);
} catch (Throwable $e) {
    fwrite(STDERR, "Eval failed: " . $e->getMessage() . "\n");
    exit(1);
}
if (!is_array($tpl)) {
    fwrite(STDERR, "Parsed value is not an array\n");
    exit(1);
}
foreach ($tpl as $k => $v) {
    $path = $base . 'scaffold_templates/' . ltrim($k, '/');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $v);
}
echo "OK: scaffold_templates written (fixed)\n";
