<?php
/**
 * generate-file-structure.php
 *
 * Usage:
 *   php generate-file-structure.php create <project_name>
 *   ml create <project_name>
 *
 * Legacy usage (scaffold in current directory):
 *   php generate-file-structure.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the terminal.\n");
    exit(1);
}

$scriptName = basename($argv[0] ?? 'generate-file-structure.php');
$command = $argv[1] ?? null;

if ($command === '--help' || $command === '-h') {
    printUsage($scriptName);
    exit(0);
}

if ($command === null) {
    $cwd = getcwd();
    if ($cwd === false) {
        fwrite(STDERR, "Unable to detect current working directory.\n");
        exit(1);
    }

    $projectName = basename($cwd);
    $ok = scaffoldProject($cwd, $projectName);
    if ($ok) {
      echo "Project structure successfully generated." . PHP_EOL;
      printMadeBy();
      exit(0);
    }

    echo "Project generation failed." . PHP_EOL;
    exit(1);
}

if ($command === 'create') {
    $projectName = $argv[2] ?? null;
    if ($projectName === null || trim($projectName) === '') {
        fwrite(STDERR, "Missing project name.\n\n");
        printUsage($scriptName);
        exit(1);
    }

    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-_]*$/', $projectName)) {
        fwrite(STDERR, "Invalid project name '{$projectName}'. Use letters, numbers, dash, underscore only.\n");
        exit(1);
    }

    $cwd = getcwd();
    if ($cwd === false) {
        fwrite(STDERR, "Unable to detect current working directory.\n");
        exit(1);
    }

    $projectRoot = $cwd . DIRECTORY_SEPARATOR . $projectName;
    if (file_exists($projectRoot)) {
        fwrite(STDERR, "Target already exists: {$projectRoot}\n");
        exit(1);
    }

    echo "Creating project: {$projectName}\n";

    if (!mkdir($projectRoot, 0777, true) && !is_dir($projectRoot)) {
        fwrite(STDERR, "Failed to create project directory: {$projectRoot}\n");
        exit(1);
    }

    report('dir', $projectRoot, $projectRoot, 'OK');

    $ok = scaffoldProject($projectRoot, $projectName);
    if ($ok) {
      echo "Project created successfully\n";
      printMadeBy();
      exit(0);
    }

    echo "Project creation finished with errors\n";
    exit(1);
}

if (in_array($command, ['make:page', 'make:component', 'serve'], true)) {
    fwrite(STDERR, "Command '{$command}' is reserved for a future release.\n");
    exit(2);
}

fwrite(STDERR, "Unknown command: {$command}\n\n");
printUsage($scriptName);
exit(1);

function printUsage(string $scriptName): void
{
    echo "ML CLI\n";
    echo "Usage:\n";
    echo "  php {$scriptName} create <project_name>\n";
    echo "  ml create <project_name>\n";
    echo "  php {$scriptName}                # legacy scaffold in current directory\n";
    echo "\n";
    echo "Reserved commands:\n";
    echo "  ml make:page <name>\n";
    echo "  ml make:component <name>\n";
    echo "  ml serve\n";
}

function scaffoldProject(string $projectRoot, string $projectName): bool
{
    $projectTitle = humanizeProjectName($projectName);

    $directories = [
        'src',
        'src/assets',
        'src/assets/css',
        'src/assets/js',
        'src/assets/images',
        'src/assets/fonts',
        'src/config',
        'src/controllers',
        'src/models',
        'src/modals',
        'src/modals/login-modal',
        'src/pages',
        'src/pages/home',
        'public',
        'public/components',
    ];

    foreach ($directories as $relativeDir) {
        $absoluteDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!ensureDirectory($absoluteDir, $projectRoot)) {
            return false;
        }
    }

    $templates = [
        '.env' => <<<'ENV'
APP_NAME="{{PROJECT_TITLE}}"
APP_ENV=local
APP_DEBUG=true
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
ENV,

        'src/config/auth.php' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'session_key' => 'auth_user',
];
PHP,

        'src/config/csrf.php' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'token_key' => '_csrf',
];
PHP,

        'src/config/db.php' => <<<'PHP'
  <?php

  declare(strict_types=1);

  use PDO;
  use PDOException;

  if (!function_exists('userDbConnection')) {
    function userDbConnection(): PDO
    {
      $driver = env('DB_DRIVER', 'mysql') ?? 'mysql';
      $host = env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
      $port = (int) (env('DB_PORT', '3306') ?? 3306);
      $dbname = env('DB_DATABASE', 'my_database') ?? 'my_database';
      $user = env('DB_USERNAME', 'root') ?? 'root';
      $pass = env('DB_PASSWORD', '') ?? '';
      $charset = env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

      $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset={$charset}";

      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ];

      $sslCa = env('DB_SSL_CA', '');
      if ($sslCa) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
      }

      try {
        return new PDO($dsn, $user, $pass, $options);
      } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
      }
    }
  }
  PHP,

        'src/config/env.php' => <<<'PHP'
  <?php

  declare(strict_types=1);

  if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
      static $loaded = false;
      static $values = [];

      if (!$loaded) {
        $loaded = true;
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath) && is_readable($envPath)) {
          $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
              continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
              $k = trim($parts[0]);
              $v = trim($parts[1]);
              if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
              }
              $values[$k] = $v;
            }
          }
        }
      }

      return $values[$key] ?? $default;
    }
  }
  PHP,

        'src/config/error-handling.php' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'display_errors' => true,
    'log_errors' => true,
];
PHP,

        'src/config/helper.php' => <<<'PHP'
<?php

declare(strict_types=1);

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/src/assets/' . ltrim($path, '/');
    }
}
PHP,

        'src/config/login-handler.php' => <<<'PHP'
<?php

declare(strict_types=1);

// Handle login POST here.
PHP,

        'src/config/logout-handler.php' => <<<'PHP'
<?php

declare(strict_types=1);

// Handle logout here.
PHP,

        'src/config/middleware.php' => <<<'PHP'
<?php

declare(strict_types=1);

// Register middleware here.
PHP,

        'src/config/session.php' => <<<'PHP'
<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
PHP,

        'src/controllers/login-controller.php' => <<<'PHP'
<?php

declare(strict_types=1);

class LoginController
{
    public function show(): void
    {
        // Render login modal/page.
    }
}
PHP,

        'src/controllers/usercontroller.php' => <<<'PHP'
<?php

declare(strict_types=1);

class UserController
{
    public function profile(): void
    {
        // Render user profile.
    }
}
PHP,

        'src/models/user-model.php' => <<<'PHP'
<?php

declare(strict_types=1);

class UserModel
{
    // Define user model logic here.
}
PHP,

        'src/modals/login-modal/login-modal.php' => <<<'PHP'
<div class="login-modal" id="loginModal" aria-hidden="true">
  <div class="login-modal__overlay" data-close-login-modal></div>
  <div class="login-modal__content" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
    <button class="login-modal__close" type="button" data-close-login-modal aria-label="Close login modal"><span class="material-icons">close</span></button>
    <div class="login-modal__header">
      <img src="../src/assets/images/logo2.png" alt="logo" class="login-modal__logo">
      <h2 class="login-modal__welcome">Welcome back</h2>
      <div class="login-modal__subtitle">Sign in to your ML account</div>
    </div>

    <form id="loginForm" class="login-modal__form" method="post" action="../src/config/login-handler.php" autocomplete="on">
      <div class="field">
        <label for="username">Username</label>
        <div class="input-with-icon">
          <span class="material-icons input-icon">person</span>
          <input type="text" id="username" name="username" placeholder="Username" required>
        </div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-with-icon password-wrap">
          <span class="material-icons input-icon">lock</span>
          <input type="password" id="password" name="password" placeholder="Password" required>
          <button class="password-toggle" id="togglePassword" type="button" aria-label="Show password"><span class="material-icons">visibility</span></button>
        </div>
      </div>

      <label class="remember-wrap" for="rememberMe">
        <input type="checkbox" id="rememberMe" name="remember_me">
        <span>Save Login</span>
      </label>

      <button class="login-submit" type="submit">Login</button>
    </form>
  </div>
</div>

<script>
  (function () {
    var modal = document.getElementById('loginModal');
    var openButton = document.getElementById('openLoginModal');
    var closeButtons = document.querySelectorAll('[data-close-login-modal]');
    var passwordInput = document.getElementById('password');
    var usernameInput = document.getElementById('username');
    var rememberMe = document.getElementById('rememberMe');
    var togglePassword = document.getElementById('togglePassword');
    var loginForm = document.getElementById('loginForm');
    var toggleIcon = togglePassword ? togglePassword.querySelector('.material-icons') : null;

    function openModal() {
      if (!modal) return;
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
      if (!modal) return;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }

    if (openButton) {
      openButton.addEventListener('click', openModal);
    }

    closeButtons.forEach(function (button) {
      button.addEventListener('click', closeModal);
    });

    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', function () {
        var isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        if (toggleIcon) toggleIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
        togglePassword.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      });
    }

    var savedUsername = localStorage.getItem('remember_username');
    var savedPassword = localStorage.getItem('remember_password');

    if (savedUsername && savedPassword && usernameInput && passwordInput && rememberMe) {
      usernameInput.value = savedUsername;
      passwordInput.value = savedPassword;
      rememberMe.checked = true;
    }

    if (loginForm) {
      loginForm.addEventListener('submit', function () {
        if (!usernameInput || !passwordInput || !rememberMe) return;

        if (rememberMe.checked) {
          localStorage.setItem('remember_username', usernameInput.value);
          localStorage.setItem('remember_password', passwordInput.value);
        } else {
          localStorage.removeItem('remember_username');
          localStorage.removeItem('remember_password');
        }
      });
    }
  })();
</script>
PHP,

        'src/modals/login-modal/login-modal.css' => <<<'CSS'
.login-modal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.login-modal.is-open {
  display: flex;
}

.login-modal__overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
}

.login-modal__content {
  position: relative;
  width: min(520px, 96vw);
  background: var(--surface);
  border-radius: 12px;
  border: 1px solid var(--stroke);
  box-shadow: var(--shadow);
  padding: 26px 22px 28px 22px;
  z-index: 2;
}

.login-modal__close {
  position: absolute;
  right: 12px;
  top: 12px;
  border: 0;
  background: transparent;
  font-size: 20px;
  cursor: pointer;
  color: var(--muted);
}

.login-modal__header {
  text-align: center;
  margin-bottom: 12px;
}

.login-modal__logo {
  width: 56px;
  height: 56px;
  object-fit: contain;
  border-radius: 10px;
  background: #fff;
  display: inline-block;
  padding: 8px;
  margin-bottom: 10px;
}

.login-modal__welcome {
  margin: 0;
  color: var(--accent);
  font-size: 22px;
  font-weight: 800;
}

.login-modal__subtitle {
  color: var(--muted);
  font-size: 13px;
  margin-top: 6px;
}

.login-modal__form {
  display: grid;
  gap: 14px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: var(--ink);
}

.input-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 10px 12px;
  background: #fff;
}

.input-with-icon .input-icon {
  color: var(--accent);
  font-size: 18px;
}

.input-with-icon input {
  border: 0;
  outline: none;
  flex: 1 1 auto;
  font: inherit;
}

.password-wrap { position: relative; }

.password-toggle {
  background: transparent;
  border: 0;
  cursor: pointer;
  color: var(--muted);
}

.remember-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
}

.login-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  border: 0;
  border-radius: 10px;
  padding: 14px 16px;
  cursor: pointer;
  background: var(--accent);
  color: #fff;
  font-weight: 800;
  font-size: 15px;
  box-shadow: 0 8px 20px rgba(220,53,69,0.18);
}

.login-submit:hover {
  background: var(--accent-dark);
}
CSS,

        'src/pages/home/home.php' => <<<'PHP'
<section class="home-page">
  <h1>Home Page</h1>
  <p>Welcome to your scaffolded project.</p>
</section>
PHP,

        'src/pages/home/home.css' => <<<'CSS'
.home-page {
  padding: 2rem 1rem;
}
CSS,

        'public/index.php' => <<<'PHP'
<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{PROJECT_TITLE}}</title>
  <link rel="icon" type="image/png" href="../src/assets/images/logo2.png">
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="components/index-header.css">
  <link rel="stylesheet" href="components/hero-section.css">
  <link rel="stylesheet" href="components/index-footer.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="../src/modals/login-modal/login-modal.css">
</head>
<body>
  <?php require __DIR__ . '/components/index-header.php'; ?>
  <?php require __DIR__ . '/components/hero-section.php'; ?>
  <?php require __DIR__ . '/../src/modals/login-modal/login-modal.php'; ?>
  <?php require __DIR__ . '/components/index-footer.php'; ?>
</body>
</html>
PHP,

        'public/index.css' => <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');
@import url('../src/assets/css/color.css');

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: 'Roboto', sans-serif;
  background: var(--bg);
  color: var(--ink);
}
CSS,

        'src/assets/css/color.css' => <<<'CSS'
:root {
  color-scheme: light;
  --bg: #f5f7fb;
  --surface: #ffffff;
  --ink: #0f172a;
  --muted: #6b7280;
  --accent: #dc3545;
  --accent-dark: #b02a37;
  --stroke: #e6eef8;
  --shadow: 0 24px 40px rgba(16,24,40,0.06);
}
CSS,

        'public/components/index-header.php' => <<<'PHP'
<header class="site-header">
  <div class="site-header__brand">
    <img src="../src/assets/images/logo1.png" alt="Company Logo" class="site-header__logo">
    <span class="site-header__title">M LHUILLIER FINANCIAL SERVICES, INC.</span>
  </div>
  <button id="openLoginModal" class="site-header__login-btn" type="button">Login</button>
</header>
PHP,

        'public/components/index-header.css' => <<<'CSS'
.site-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 20px;
  background: var(--accent);
  color: #fff; 
}

.site-header__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.site-header__logo {
  width: 48px;
  height: 48px;
  object-fit: contain;
  background: transparent;
  border-radius: 6px;
  padding: 0;
}

.site-header__title {
  font-weight: 700;
  letter-spacing: 0.2px;
  font-size: 15px;
}

.site-header__login-btn {
  border: 1px solid rgba(255, 255, 255, 0.6);
  background: #fff;
  color: var(--accent);
  border-radius: 8px;
  padding: 9px 14px;
  cursor: pointer;
  font-weight: 600;
}

.site-header__login-btn:hover {
  background: #ffe8eb;
}

CSS,

        'public/components/hero-section.php' => <<<'PHP'
<section class="hero-centered">
  <div class="hero-centered__bg" aria-hidden="true"></div>
  <div class="hero-centered__inner">
    <div class="hero-centered__content">
      <h1 class="hc-heading">Tulay ng <span class="hc-accent">PaMLyang</span> Pilipino</h1>
      <p class="hc-sub">Serbisyong pinansyal at solusyong pang-negosyo para sa bawat pamilya: ligtas na padala, pawning, bayad-serbisyo, at micro-insurance — abot-kamay sa buong bansa.</p>
    </div>

    <div class="hero-centered__decor" aria-hidden="true">
      <span class="badge b1"><span class="material-icons badge-icon">send</span></span>
      <span class="badge b2"><span class="material-icons badge-icon">paid</span></span>
      <span class="badge b3"><span class="material-icons badge-icon">payments</span></span>
      <span class="badge b4"><span class="material-icons badge-icon">folder</span></span>
      <span class="badge b5"><span class="material-icons badge-icon">people</span></span>
    </div>
  </div>
</section>
PHP,

        'public/components/hero-section.css' => <<<'CSS'
.hero-section { padding: 56px 20px; background: var(--bg); }

.hero-container { max-width: 900px; margin: 0 auto; text-align: center; padding: 48px 20px; }

/* Centered Stacker-style hero (no CTA buttons) */
.hero-centered { position: relative; overflow: hidden; padding: 72px 12px; }
.hero-centered__bg { position: absolute; left: 0; right: 0; top: 0; height: 56%; background: linear-gradient(180deg, rgba(255,255,255,0), rgba(240,246,255,0.6)); pointer-events: none; }
.hero-centered__inner { position: relative; max-width: 1200px; margin: 0 auto; display:flex; align-items:center; justify-content:center; min-height: 420px; }
.hero-centered__content { text-align:center; padding: 40px 28px; }
.hc-heading { font-size: 3.6rem; line-height: 1.02; margin: 0 0 16px 0; font-weight:800; color:var(--ink); }
.hc-accent { color: var(--accent); }
.hc-sub { color: var(--muted); max-width:76ch; margin: 0 auto; font-size:1.05rem; }

.hero-centered__decor { position: absolute; inset: 0; pointer-events: none; }
.badge { position: absolute; width:56px; height:56px; border-radius:12px; background:#fff; box-shadow: 0 12px 30px rgba(16,24,40,0.08); opacity:0.95; display:block; }
.badge.b1 { left: 8%; top: 18%; }
.badge.b2 { left: 18%; top: 60%; width:48px; height:48px; border-radius:12px; }
.badge.b3 { right: 16%; top: 22%; }
.badge.b4 { right: 10%; top: 58%; width:48px; height:48px; }
.badge.b5 { left: 50%; bottom: 8%; transform: translateX(-50%); width:64px; height:64px; border-radius:14px; }

.badge { display:flex; align-items:center; justify-content:center; }
.badge-icon { font-size:20px; color: var(--accent); }
.badge.small-icon { width:48px; height:48px; }

@media (max-width: 900px) {
  .hc-heading { font-size: 2rem; }
  .hero-centered { padding: 48px 12px; }
  .badge { display:none; }
  .hero-centered__inner { min-height: 300px; }
}
CSS,

        'public/components/index-footer.php' => <<<'PHP'
<footer class="site-footer">
  <p>&copy; <?= date('Y'); ?> M Lhuillier Financial Services, Inc.</p>
</footer>
PHP,

        'public/components/index-footer.css' => <<<'CSS'
.site-footer {
  margin-top: 40px;
  padding: 16px 20px;
  text-align: center;
  color: var(--muted);
}
CSS,

        'public/.README-INDEX.md' => "# Index Sections\n\nPut all your sections here for index.\n",

        'README.md' => <<<'MD'
# {{PROJECT_TITLE}}

This repository contains a minimal PHP project scaffold and a CLI generator
(`generate-file-structure.php`) that creates a ready-to-edit project layout
including a `public/` web root, `src/` application code, starter UI components,
and configuration helpers for environment-driven settings and secure database
connections.

## Features

- CLI scaffolder to create directories and starter files
- Environment loader (`src/config/env.php`) with `.env` support
- Secure PDO connection helper (`src/config/db.php`) via `userDbConnection()`
- Pre-built UI components: header, centered hero, footer, and login modal
- Root `.htaccess` with `RewriteBase` configured by the generator for XAMPP/Apache

## Requirements

- PHP 8.0+ (CLI for the generator)
- Apache (XAMPP recommended on Windows) or another web server pointing to `public/`

## Quick Start

1. From the project root, run the generator:

```bash
php generate-file-structure.php
```

On Windows with XAMPP:

```powershell
C:\xampp\php\php.exe generate-file-structure.php
```

2. Configure environment values in `.env` (the generator creates a default). Use
   `.env.example` as a template for sharing non-sensitive defaults.

3. Point your web server document root to the `public/` folder and open the
   site in your browser. For XAMPP the generator writes a root `.htaccess` with
   `RewriteBase` set to the project folder to avoid redirect issues.

## Project Layout (important files)

- `public/` — Web root. Contains `index.php`, component includes and CSS
- `src/` — Application source: `config/`, `controllers/`, `models/`, `modals/`, `assets/`
- `src/config/env.php` — `env($key, $default)` helper that reads `.env`
- `src/config/db.php` — `userDbConnection()` returns a configured `PDO` instance
- `.env` / `.env.example` — Local configuration (DB credentials, app flags)
- `generate-file-structure.php` — CLI scaffolder that creates the structure and templates

## Configuration

- Keep secrets out of version control. Add `.env` to your `.gitignore`.
- `env()` loads simple KEY=VALUE pairs. Values wrapped in quotes are supported.
- `userDbConnection()` reads DB_* env variables and configures PDO with
  recommended options (exceptions, native prepares, and optional SSL CA).

## Web Server Notes

- The generator writes a root `.htaccess` with `RewriteBase /<project-folder>/`
  to ensure redirects work correctly under XAMPP/Apache. If you move the
  project, regenerate the `.htaccess` or update `RewriteBase` accordingly.

## Troubleshooting

- If included assets (images/CSS) 404, confirm your server document root is
  `public/` and that asset paths are referenced relative to `public/`.
- If PHP errors occur while loading config/db, ensure `.env` exists and
  contains valid DB_* values, and that `src/config/env.php` is required before
  other config files in your bootstrap.

## Contributing / Extending

- Edit the generator templates in `generate-file-structure.php` to customize
  default components or add new files produced when scaffolding new projects.

## License & Support

This scaffold is provided without warranty. If you need help, paste the
generator output and any error messages into an issue or chat for assistance.

MD,

        'color.md' => <<<'MD'
:root {
  color-scheme: light;
  --bg: #f5f7fb;
  --surface: #ffffff;
  --ink: #0f172a;
  --muted: #6b7280;
  --accent: #dc3545;
  --accent-dark: #b02a37;
  --stroke: #e6eef8;
  --shadow: 0 24px 40px rgba(16,24,40,0.06);
}
MD,
    ];

    foreach ($templates as $relativeFile => $content) {
        $absoluteFile = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);
        $compiled = renderTemplate($content, $projectName, $projectTitle);

        if (!ensureFile($absoluteFile, $projectRoot, rtrim($compiled, "\r\n") . PHP_EOL)) {
            return false;
        }
    }

    $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9sM5ux8AAAAASUVORK5CYII=', true);
    if ($transparentPng === false) {
      report('file', $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images', $projectRoot, 'FAILED');
      return false;
    }

    $sourceImagesRoot = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
    foreach (['logo1.png', 'logo2.png'] as $logoFileName) {
      $logoPath = $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $logoFileName;
      if (file_exists($logoPath)) {
        report('file', $logoPath, $projectRoot, 'EXISTS');
        continue;
      }

      $sourceLogoPath = $sourceImagesRoot . DIRECTORY_SEPARATOR . $logoFileName;
      if (file_exists($sourceLogoPath) && is_readable($sourceLogoPath)) {
        $copied = copy($sourceLogoPath, $logoPath);
        report('file', $logoPath, $projectRoot, $copied ? 'OK' : 'FAILED');
        if (!$copied) {
          return false;
        }
        continue;
      }

      // Attempt to download the logo from the repository raw URL before
      // falling back to a transparent placeholder.
      $repoRawBase = 'https://raw.githubusercontent.com/ZheyUse/mlgen/main/assets/images';
      $rawUrl = $repoRawBase . '/' . $logoFileName;
      $downloaded = null;

      if (function_exists('curl_version')) {
        $ch = curl_init($rawUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $body !== false;
        curl_close($ch);
        if ($ok) $downloaded = $body;
      } else {
        $opts = stream_context_create(['http' => ['timeout' => 15]]);
        $body = @file_get_contents($rawUrl, false, $opts);
        if ($body !== false) $downloaded = $body;
      }

      if ($downloaded !== null) {
        $written = file_put_contents($logoPath, $downloaded);
        report('file', $logoPath, $projectRoot, $written === false ? 'FAILED' : 'OK');
        if ($written === false) {
          return false;
        }
        continue;
      }

      // Fallback: write a tiny transparent PNG so scaffold still succeeds.
      $written = file_put_contents($logoPath, $transparentPng);
      report('file', $logoPath, $projectRoot, $written === false ? 'FAILED' : 'OK');
      if ($written === false) {
        return false;
      }
    }

    $projectFolder = basename($projectRoot);
    $rewriteBase = '/' . $projectFolder . '/';
    $htPath = $projectRoot . DIRECTORY_SEPARATOR . '.htaccess';
    $htContent = "RewriteEngine On\nRewriteBase {$rewriteBase}\nRewriteRule ^$ public/ [R=302,L]\n";

    if (file_put_contents($htPath, $htContent) === false) {
        report('file', $htPath, $projectRoot, 'FAILED');
        return false;
    }
    report('file', $htPath, $projectRoot, 'OK');

    $publicHt = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess';
    if (file_exists($publicHt)) {
        try {
            if (@unlink($publicHt)) {
                report('file', $publicHt, $projectRoot, 'REMOVED');
            } else {
                report('file', $publicHt, $projectRoot, 'FAILED-REMOVE');
            }
        } catch (Throwable $e) {
            report('file', $publicHt, $projectRoot, 'FAILED-REMOVE');
        }
    }

    return true;
}

function renderTemplate(string $content, string $projectName, string $projectTitle): string
{
    return strtr($content, [
        '{{PROJECT_NAME}}' => $projectName,
        '{{PROJECT_TITLE}}' => $projectTitle,
    ]);
}

function humanizeProjectName(string $projectName): string
{
    $normalized = str_replace(['-', '_'], ' ', strtolower($projectName));
    return ucwords(trim($normalized));
}

function relativePath(string $absolutePath, string $root): string
{
    $normalized = str_replace('\\', '/', $absolutePath);
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');

    if (str_starts_with($normalized, $normalizedRoot . '/')) {
        return substr($normalized, strlen($normalizedRoot) + 1);
    }

    if ($normalized === $normalizedRoot) {
        return basename($normalizedRoot);
    }

    return $normalized;
}

function report(string $type, string $absolutePath, string $root, string $status): void
{
    $label = $type === 'dir' ? 'Creating' : 'Creating';
    echo $label . ' ' . relativePath($absolutePath, $root) . ' ... ' . $status . PHP_EOL;
}

function ensureDirectory(string $path, string $root): bool
{
    if (is_dir($path)) {
        report('dir', $path, $root, 'EXISTS');
        return true;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        report('dir', $path, $root, 'FAILED');
        return false;
    }

    report('dir', $path, $root, 'OK');
    return true;
}

function ensureFile(string $path, string $root, string $content = ''): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!ensureDirectory($dir, $root)) {
            return false;
        }
    }

    if (file_exists($path)) {
        report('file', $path, $root, 'EXISTS');
        return true;
    }

    $bytes = file_put_contents($path, $content);
    if ($bytes === false) {
        report('file', $path, $root, 'FAILED');
        return false;
    }

    report('file', $path, $root, 'OK');
    return true;
}

function printMadeBy(): void
{
  $art = <<<'TXT'

┏┳┓┏━┓╺┳┓┏━╸   ┏┓ ╻ ╻
┃┃┃┣━┫ ┃┃┣╸    ┣┻┓┗┳┛
╹ ╹╹ ╹╺┻┛┗━╸   ┗━┛ ╹ 
 ██████╗ ██████╗ ██████╗ ███████╗███████╗
██╔════╝██╔═══██╗██╔══██╗██╔════╝╚══███╔╝
██║     ██║   ██║██║  ██║█████╗    ███╔╝ 
██║     ██║   ██║██║  ██║██╔══╝   ███╔╝  
╚██████╗╚██████╔╝██████╔╝███████╗███████╗
 ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝╚══════╝

Follow: https://github.com/ZheyUse
TXT;

  echo $art . PHP_EOL;
}
