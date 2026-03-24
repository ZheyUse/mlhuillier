<?php
declare(strict_types=1);
$base = __DIR__ . '/../';
$tplFile = $base . 'generate-file-structure.php';
if (!file_exists($tplFile)) {
    fwrite(STDERR, "templates file not found: $tplFile\n");
    exit(1);
}
$tpl = include $tplFile;
foreach ($tpl as $k => $v) {
    $path = $base . 'scaffold_templates/' . ltrim($k, '/');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $v);
}
echo "OK: scaffold_templates written\n";
