<?php
/**
 * script/sidebar-add-menu.php
 *
 * Adds a sidebar menu and its submenus, optionally generating scaffold files.
 *
 * Usage:
 *   php script/sidebar-add-menu.php
 *   ml add menu
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the terminal.\n");
    exit(1);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function ask(string $prompt): string
{
    fwrite(STDOUT, $prompt . ' ');
    $line = fgets(STDIN);
    return trim((string) $line);
}

function confirm(string $prompt): bool
{
    $line = ask($prompt . ' (Y/N): ');
    return strtoupper(substr($line, 0, 1)) === 'Y';
}

function slug(string $text): string
{
    return strtolower(preg_replace('/\s+/', '-', trim($text)) ?? '');
}

function kebabToTitle(string $text): string
{
    return ucwords(str_replace('-', ' ', $text));
}

function hasScaffoldRoot(string $path): bool
{
    return is_dir($path)
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php')
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env.php')
        && is_file($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'sidebar.php');
}

function resolveProjectRoot(): ?string
{
    $cwd = getcwd();
    if ($cwd === false) {
        return null;
    }
    $current = realpath($cwd);
    while (is_string($current) && $current !== '' && $current !== dirname($current)) {
        if (hasScaffoldRoot($current)) {
            return $current;
        }
        $current = dirname($current);
    }
    return hasScaffoldRoot($cwd) ? realpath($cwd) : null;
}

// ── File writers ──────────────────────────────────────────────────────────────

function writePhpFile(string $path, string $menuSlug, string $menuTitle, string $submenuSlug, string $submenuTitle): void
{
    $menuTitleEsc   = addslashes($menuTitle);
    $submenuTitleEsc = addslashes($submenuTitle);
    $submenuLabel   = kebabToTitle($submenuSlug);

    $content = <<<PHP
<?php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../controllers/usercontroller.php';
require_once __DIR__ . '/../../../templates/header_ui.php';

requireAuth();

\$userController = new UserController();
\$user = \$userController->profile();

\$displayName = trim((string) ((\$user['firstname'] ?? '') . ' ' . (\$user['lastname'] ?? '')));
if (\$displayName === '') {
  \$displayName = (string) (\$user['username'] ?? 'User');
}

\$scriptName = (string) (\$_SERVER['SCRIPT_NAME'] ?? '');
\$appBaseUrl = preg_replace('#/src/.*$#', '', \$scriptName);
\$appBaseUrl = rtrim((string) \$appBaseUrl, '/');

\$isEntry = (realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__));
if (\$isEntry) {
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$submenuLabel}</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(\$appBaseUrl . '/src/assets/images/logo2.png', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/public/index.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/src/assets/css/color.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/src/templates/header_ui.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/src/templates/sidebar.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/src/modals/logout-modal/logout-modal.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(\$appBaseUrl . '/src/pages/{$menuSlug}/{$submenuSlug}/{$submenuSlug}.css', ENT_QUOTES, 'UTF-8'); ?>">
  </head>
  <body>
  <?php
}
?>
<div class="app-layout">
  <?php require __DIR__ . '/../../../templates/sidebar.php'; ?>

  <main class="main-content">
    <section class="{$submenuSlug}-page" id="{$submenuSlug}-root">
      <?php bp_section_header_html('question_mark','{$menuTitleEsc} — {$submenuTitleEsc}','Edit this part later'); ?>

      <div style="padding: 40px; text-align: center;">
        <h2 style="color: var(--text-secondary, #6c757d); font-size: 1.5rem; font-weight: 600;">
          This page is for: {$submenuLabel}
        </h2>
        <p style="color: var(--muted, #999); margin-top: 8px;">
          Edit this part later
        </p>
      </div>

    </section>
  </main>
</div>

<?php require __DIR__ . '/../../../modals/logout-modal/logout-modal.php'; ?>
<?php
if (\$isEntry) {
  echo "</body>\n</html>\n";
}
?>
PHP;

    if (@file_put_contents($path, $content) !== false) {
        out("  -> src/pages/{$menuSlug}/{$submenuSlug}/{$submenuSlug}.php ... OK");
    } else {
        out("  -> src/pages/{$menuSlug}/{$submenuSlug}/{$submenuSlug}.php ... FAILED");
    }
}

function writeCssFile(string $path, string $submenuSlug): void
{
    $content = <<<CSS
/* {$submenuSlug} page styles */
@import url('../../../assets/css/color.css');

.{$submenuSlug}-page { padding: 18px; }
CSS;

    if (@file_put_contents($path, $content) !== false) {
        out("  -> src/pages/*/{$submenuSlug}/{$submenuSlug}.css ... OK");
    } else {
        out("  -> src/pages/*/{$submenuSlug}/{$submenuSlug}.css ... FAILED");
    }
}

// ── Main ─────────────────────────────────────────────────────────────────────

$projectRoot = resolveProjectRoot();
if ($projectRoot === null) {
    err('Error: run this command inside a scaffolded project directory.');
    exit(2);
}

out('');
out('===== Add Sidebar Menu =====');
out('');

// Step 1 — menu name
$menuName = ask('Enter menu to be added in sidebar (e.g. Profile):');
if ($menuName === '') {
    err('Menu name cannot be empty.');
    exit(1);
}

$menuSlug = slug($menuName);
$menuTitle = trim($menuName);

// Step 2 — submenus
$rawSubmenus = ask('Enter Submenu(s) for ' . $menuTitle . ' (comma-separated, e.g. Settings,Extensions,Passwords,Details):');
if ($rawSubmenus === '') {
    err('At least one submenu is required.');
    exit(1);
}

$submenuNames = array_filter(
    array_map('trim', explode(',', $rawSubmenus))
);
if (count($submenuNames) === 0) {
    err('No valid submenus provided.');
    exit(1);
}

// Step 3 — display creation report
out('');
out("{$menuTitle} has been Created");
foreach ($submenuNames as $sm) {
    out("  -> {$sm} has been added");
}
out('');

// Step 4 — template generation prompt
if (!confirm("Do you want me to create the necessary template of the created {$menuTitle} and Submenu(s)?")) {
    out('Template creation cancelled.');
    exit(0);
}

// Step 5 — generate scaffold
$menuDir = $projectRoot . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'pages'
    . DIRECTORY_SEPARATOR . $menuSlug;

if (!is_dir($menuDir)) {
    if (@mkdir($menuDir, 0755, true)) {
        out("Creating src/pages/{$menuSlug} ... OK");
    } else {
        err("Failed to create directory: src/pages/{$menuSlug}");
        exit(3);
    }
} else {
    out("Creating src/pages/{$menuSlug} ... SKIPPED (already exists)");
}

foreach ($submenuNames as $submenuName) {
    $submenuSlug  = slug($submenuName);
    $submenuDir   = $menuDir . DIRECTORY_SEPARATOR . $submenuSlug;

    if (!is_dir($submenuDir)) {
        if (!@mkdir($submenuDir, 0755, true)) {
            err("  Failed to create directory: src/pages/{$menuSlug}/{$submenuSlug}");
            continue;
        }
    }

    // Determine relative path for CSS link (menu could be at depth 3, so ../.. is correct)
    $relMenu = $menuSlug;

    writePhpFile(
        $submenuDir . DIRECTORY_SEPARATOR . $submenuSlug . '.php',
        $menuSlug,
        $menuTitle,
        $submenuSlug,
        trim($submenuName)
    );

    writeCssFile(
        $submenuDir . DIRECTORY_SEPARATOR . $submenuSlug . '.css',
        $submenuSlug
    );
}

out('');
out('Done. Add the following to your sidebar:');
out("  -> {$menuTitle}");
foreach ($submenuNames as $sm) {
    out("      -> {$sm}");
}
out('');
exit(0);