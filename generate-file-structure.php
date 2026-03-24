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

function printUsage(string $scriptName): void
{
  echo "ML CLI\n";
  echo "Usage:\n";
  echo "  php {$scriptName} create <project_name>\n";
  echo "  ml create <project_name>\n";
  echo "  php {$scriptName} --dump-templates [output_dir]\n";
  echo "  php {$scriptName}                # legacy scaffold in current directory\n";
  echo "\n";
  echo "Reserved commands:\n";
  echo "  ml make:page <name>\n";
  echo "  ml make:component <name>\n";
  echo "  ml serve\n";
}

if ($command === '--help' || $command === '-h') {
    printUsage($scriptName);
    exit(0);
}

if ($command === '--dump-templates') {
  $outputDir = $argv[2] ?? 'scaffold_templates';
  $cwd = getcwd();
  if ($cwd === false) {
    fwrite(STDERR, "Unable to detect current working directory.\n");
    exit(1);
  }

  $targetRoot = $outputDir;
  if (!preg_match('/^[A-Za-z]:\\\\|^\\\\\\\\|^\//', $outputDir)) {
    $targetRoot = $cwd . DIRECTORY_SEPARATOR . $outputDir;
  }

  $ok = dumpTemplatesFromSource(__FILE__, $targetRoot);
  exit($ok ? 0 : 1);
}

if ($command === 'create') {
  $projectName = trim((string) ($argv[2] ?? ''));
  if ($projectName === '') {
    fwrite(STDERR, "Missing project name. Usage: php {$scriptName} create <project_name>\n");
    exit(2);
  }

  if (preg_match('/[\\\\\/]/', $projectName)) {
    fwrite(STDERR, "Invalid project name. Use a simple folder name without slashes.\n");
    exit(2);
  }

  $cwd = getcwd();
  if ($cwd === false) {
    fwrite(STDERR, "Unable to detect current working directory.\n");
    exit(1);
  }

  $targetRoot = $cwd . DIRECTORY_SEPARATOR . $projectName;
  $ok = scaffoldProject($targetRoot, $projectName);
  if ($ok) {
    printMadeBy();
  }
  exit($ok ? 0 : 1);
}

if ($command !== null && $command !== '') {
  fwrite(STDERR, "Unknown command: {$command}\n");
  printUsage($scriptName);
  exit(2);
}

$cwd = getcwd();
if ($cwd === false) {
  fwrite(STDERR, "Unable to detect current working directory.\n");
  exit(1);
}

$legacyProjectName = basename($cwd);
if (!is_string($legacyProjectName) || $legacyProjectName === '' || $legacyProjectName === '.' || $legacyProjectName === DIRECTORY_SEPARATOR) {
  $legacyProjectName = 'project';
}

$ok = scaffoldProject($cwd, $legacyProjectName);
if ($ok) {
  printMadeBy();
}
exit($ok ? 0 : 1);

function dumpTemplatesFromSource(string $scriptFile, string $outputRoot): bool
{
    // Generate a temporary project using the scaffolder and copy the produced
    // files into the requested output directory. This is robust and avoids
    // brittle source parsing of embedded nowdocs/heredocs.
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ml_scaffold_dump_' . uniqid('', true);
    if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
        fwrite(STDERR, "Unable to create temporary directory for scaffold dump: {$tmp}\n");
        return false;
    }

    $okGen = scaffoldProject($tmp, 'ml_audit_temp');
    if (!$okGen) {
        fwrite(STDERR, "Fallback generation failed. Cannot dump templates.\n");
        return false;
    }

    if (!is_dir($outputRoot) && !mkdir($outputRoot, 0777, true) && !is_dir($outputRoot)) {
        fwrite(STDERR, "Unable to create output directory: {$outputRoot}\n");
        return false;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $file) {
        $relPath = substr($file->getPathname(), strlen($tmp) + 1);
        $destPath = rtrim($outputRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', '/', $relPath));
        if ($file->isDir()) {
            if (!is_dir($destPath) && !mkdir($destPath, 0777, true) && !is_dir($destPath)) {
                fwrite(STDERR, "Unable to create directory: {$destPath}\n");
                return false;
            }
            continue;
        }
        $destDir = dirname($destPath);
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            fwrite(STDERR, "Unable to create directory: {$destDir}\n");
            return false;
        }
        if (!@copy($file->getPathname(), $destPath)) {
            fwrite(STDERR, "Unable to copy file to: {$destPath}\n");
            return false;
        }
    }

    echo "Templates dumped to: {$outputRoot} (via generated scaffold)\n";
    return true;
}

function scaffoldProject(string $projectRoot, string $projectName): bool
{
    $projectTitle = humanizeProjectName($projectName);

    $directories = [
      'migration',
      'migration/userdb',
        'src',
        'src/assets',
        'src/assets/css',
        'src/assets/js',
        'src/assets/images',
        'src/assets/fonts',
        'src/config',
        'src/controllers',
        'src/controllers/password-controller',
        'src/models',
        'src/modals',
        'src/modals/login-modal',
        'src/modals/logout-modal',
        'src/pages',
        'src/pages/home',
        'src/pages/maintenance',
        'src/pages/maintenance/accountmanagement',
        'src/templates',
        'src/controllers/accountmanagement',
        'public',
        'public/api',
        'public/components',
    ];

    foreach ($directories as $relativeDir) {
        $absoluteDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!ensureDirectory($absoluteDir, $projectRoot)) {
            return false;
        }
    }

    function loadTemplatesFromDir(string $dir): array
    {
      $result = [];
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
      foreach ($it as $file) {
        if (!$file->isFile()) {
          continue;
        }
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
        $content = @file_get_contents($file->getPathname());
        if ($content === false) {
          $content = '';
        }
        $result[$path] = $content;
      }
      return $result;
    }

    if (is_dir(__DIR__ . '/audit/scaffold_templates')) {
      $templates = loadTemplatesFromDir(__DIR__ . '/audit/scaffold_templates');
    } else {
    $templates = [
        '.env' => <<<'ENV'
      APP_NAME="{{PROJECT_TITLE}}"
      APP_ENV=local
      APP_DEBUG=true

      # Primary application database (root schema DB)
      DB_DRIVER=mysql
      DB_HOST=localhost
      DB_PORT=3306
      DB_DATABASE= #put system DB Here
      DB_USERNAME=root
      DB_PASSWORD=Password1
      DB_CHARSET=utf8mb4

      # Authentication schema name (same server/credentials can access multiple schemas)
      USERDB_HOST=localhost
      USERDB_NAME=userdb
      ENV,

        'src/config/auth.php' => <<<'PHP'
<?php

declare(strict_types=1);

return [
    'session_key' => 'auth_user',
];
PHP,
    'src/templates/header_ui.css' => <<<'CSS'
@import url('../assets/css/color.css');
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

:root {
  font-family: 'Roboto', sans-serif;
}

.bp-section-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 20px;
  background: transparent;
}

.bp-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: var(--surface);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow);
}

.bp-icon {
  font-size: 22px;
  color: var(--accent);
}

.bp-text {
  display: flex;
  flex-direction: column;
}

.bp-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 4px;
}

.bp-desc {
  font-size: 13px;
  color: var(--muted);
}

@media (max-width:600px) {
  .bp-section-header {
    padding: 12px;
  }

  .bp-icon-wrap {
    width: 40px;
    height: 40px;
  }

  .bp-title {
    font-size: 16px;
  }
.bp-section-header { background: var(--surface); }
.bp-icon { color: var(--accent); }
.bp-title { color: var(--ink); }
.bp-desc { color: var(--muted); opacity: 1; }
}
CSS,

        'src/pages/maintenance/accountmanagement/accountmanagement.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../controllers/usercontroller.php';
require_once __DIR__ . '/../../../templates/header_ui.php';

requireAuth();

$userController = new UserController();
$user = $userController->profile();

$displayName = trim((string) (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')));
if ($displayName === '') {
  $displayName = (string) ($user['username'] ?? 'User');
}

$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
$appBaseUrl = rtrim((string) $appBaseUrl, '/');

$isEntry = (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__));
if ($isEntry) {
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($appBaseUrl . '/src/assets/images/logo2.png', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/public/index.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/assets/css/color.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/templates/header_ui.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/templates/sidebar.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/logout-modal/logout-modal.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/pages/maintenance/accountmanagement/accountmanagement.css', ENT_QUOTES, 'UTF-8'); ?>">
  </head>
  <body>
  <?php
}
?>
<div class="app-layout">
  <?php require __DIR__ . '/../../../templates/sidebar.php'; ?>

  <main class="main-content">
    <section class="account-management" id="account-management-root">
      <?php bp_section_header_html('person','Account Management','Manage user accounts and statuses'); ?>

      <div class="am-controls">
        <div class="am-left">
          <div class="am-search">
            <label for="am-search-input">Search</label>
            <input id="am-search-input" placeholder="Search by name, ID or username..." autocomplete="off">
          </div>
          <div class="am-filters">
            <div class="filter-item">
              <label for="am-status-filter">Status</label>
              <select id="am-status-filter">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="reset">Reset</option>
              </select>
            </div>
          </div>
        </div>

        <div class="am-right">
          <div class="am-actions">
            <button class="btn btn-primary" id="am-add-btn"><span class="material-icons">person_add</span> Add</button>
            <button class="btn" id="am-edit-btn" disabled><span class="material-icons">edit</span> Edit</button>
            <button class="btn" id="am-reset-btn" disabled><span class="material-icons">vpn_key</span> Reset Password</button>
            <button class="btn" id="am-status-btn" disabled><span class="material-icons">swap_horiz</span> Change Status</button>
          </div>
        </div>
      </div>

      <div class="am-table-wrap">
        <div class="table-scroll">
          <table class="am-table" id="am-table" aria-label="Account table">
            <thead>
              <tr>
                <th>No.</th>
                <th>ID Number</th>
                <th>Username</th>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>Last Name</th>
                <th>Last Online</th>
                <th>Date Modified</th>
              </tr>
            </thead>
            <tbody id="am-tbody">
              <tr><td colspan="8" class="placeholder">Loading accounts...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </section>
  </main>
</div>

<?php require __DIR__ . '/../../../modals/logout-modal/logout-modal.php'; ?>
<?php
if ($isEntry) {
  echo "</body>\n</html>\n";
}

// Inline account-management client script (adapted from sample)
?>
<script>
  (function(){
    function esc(v){ return String(v == null ? '' : v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    var root = document.getElementById('account-management-root');
    if (!root) return;
    if (root.dataset.inited==='1') return; root.dataset.inited='1';

    var tbody = document.getElementById('am-tbody');
    var allRows = [];

    function renderRows(rows){
      if (!tbody) return;
      if (!rows || rows.length === 0){ tbody.innerHTML = '<tr><td colspan="9" class="placeholder">No accounts found.</td></tr>'; return; }
      var html = '';
      rows.forEach(function(r){
        var rid = esc(r.id_number || r.id || r.no || '');
        html += '<tr data-id="'+rid+'">' +
                '<td>' + esc(r.no) + '</td>' +
                '<td>' + esc(r.id_number) + '</td>' +
                '<td>' + esc(r.username) + '</td>' +
                '<td>' + esc(r.firstname) + '</td>' +
                '<td>' + esc(r.middlename) + '</td>' +
                '<td>' + esc(r.lastname) + '</td>' +
                '<td>' + esc(r.last_online) + '</td>' +
                '<td>' + esc(r.dateModified) + '</td>' +
                '</tr>';
      });
      tbody.innerHTML = html;
    }

    function applyFilters(){
      var q = (document.getElementById('am-search-input') || {value:''}).value.trim().toLowerCase();
      var status = (document.getElementById('am-status-filter') || {value:''}).value;
      var filtered = allRows.filter(function(r){
        if (status && String((r.status||'')).toLowerCase() !== status.toLowerCase()) return false;
        if (!q) return true;
        var hay = (r.id_number+' '+r.username+' '+r.firstname+' '+r.middlename+' '+r.lastname).toLowerCase();
        return hay.indexOf(q) !== -1;
      });
      renderRows(filtered);
    }

    function debounce(fn, wait){ var t; return function(){ clearTimeout(t); var args=arguments; t = setTimeout(function(){ fn.apply(null, args); }, wait); }; }

    function loadAccounts(){
      fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-load.php', { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.ok && Array.isArray(json.rows)) {
            allRows = json.rows;
            renderRows(allRows);
            var si = document.getElementById('am-search-input');
            var sf = document.getElementById('am-status-filter');
            if (si) si.addEventListener('input', debounce(applyFilters, 200));
            if (sf) sf.addEventListener('change', applyFilters);
          } else {
            tbody.innerHTML = '<tr><td colspan="9" class="placeholder">Unable to load accounts.</td></tr>';
          }
        })
        .catch(function(){ tbody.innerHTML = '<tr><td colspan="9" class="placeholder">Unable to load accounts.</td></tr>'; });
    }

    if (!root.dataset.selectionInit) {
      tbody.addEventListener('click', function(e){
        var tr = e.target.closest && e.target.closest('tr');
        if (!tr || !tbody.contains(tr)) return;
        if (!tr.dataset.id) return;
        var prev = tbody.querySelector('tr.selected');
        if (prev && prev === tr) {
          tr.classList.remove('selected');
          var edit = document.getElementById('am-edit-btn'); if (edit) edit.disabled = true;
          var reset = document.getElementById('am-reset-btn'); if (reset) reset.disabled = true;
          var status = document.getElementById('am-status-btn'); if (status) status.disabled = true;
          window.amSelectedAccount = null;
          return;
        }
        if (prev) prev.classList.remove('selected');
        tr.classList.add('selected');
        var selectedId = tr.dataset.id;
        var edit = document.getElementById('am-edit-btn'); if (edit) edit.disabled = false;
        var reset = document.getElementById('am-reset-btn'); if (reset) reset.disabled = false;
        var status = document.getElementById('am-status-btn'); if (status) status.disabled = false;
        window.amSelectedAccount = { id: selectedId, row: tr };
      });
      root.dataset.selectionInit = '1';
    }

    loadAccounts();
  })();
</script>
PHP,

        'src/pages/maintenance/accountmanagement/accountmanagement.css' => <<<'CSS'
/* Account Management styles (component) */
@import url('../../../assets/css/color.css');

.account-management { padding: 18px; }
.am-controls { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
.am-left { display:flex; gap:16px; align-items:flex-end; flex:1 1 auto; min-width:0; }
.am-right { flex:0 0 auto; display:flex; align-items:center; justify-content:flex-end; }
.am-search { min-width: 160px; max-width: 320px; flex: 0 0 260px; }
.am-search label { display:block; font-weight:600; margin-bottom:6px; }
.am-search input { width:100%; padding:8px 12px; border:1px solid var(--stroke); border-radius:6px; box-sizing:border-box; margin-right:12px; }
.am-filters { display:flex; gap:12px; align-items:flex-end; margin-left:12px; }
.filter-item { display:flex; flex-direction:column; }
.filter-item label { display:block; font-size:13px; margin-bottom:6px; }
.filter-item select { padding:8px 10px; border-radius:6px; border:1px solid var(--stroke); background:#fff; min-width:140px; }
.am-actions .btn { margin-left:8px; padding:8px 12px; border-radius:8px; border:0; cursor:pointer; display:inline-flex; gap:8px; align-items:center; white-space:nowrap; transition:transform .08s ease, box-shadow .12s ease, background .12s ease; background:var(--accent); color:#fff; font-weight:700; box-shadow: 0 8px 20px rgba(220,53,69,0.08); }
.am-actions .btn .material-icons { font-size:18px; line-height:1; }
.am-actions .btn:hover { background:var(--accent-dark); transform: translateY(-2px); box-shadow: 0 12px 28px rgba(220,53,69,0.12); }
.btn-primary { background:var(--accent); color:#fff; font-weight:700; }
.am-table-wrap { margin-top:8px; }
.table-scroll { overflow:auto; max-height:60vh; border:1px solid var(--stroke); border-radius:8px; background:var(--surface); }
.am-table { width:100%; border-collapse:collapse; min-width:900px; }
.am-table th, .am-table td { padding:10px 12px; text-align:left; border-bottom:1px solid rgba(0,0,0,0.05); }
.am-table thead th { background: rgba(0,0,0,0.03); font-weight:600; }
.placeholder { padding:18px; color:var(--muted); text-align:center; }

/* Row hover and selection */
.am-table tbody tr{ cursor: pointer; }
.am-table tbody tr:hover{ background: color-mix(in srgb, var(--accent) 4%, var(--surface)); }
.am-table tbody tr.selected{ background: var(--accent); color: #fff; }
.am-table tbody tr.selected td { color: #fff; }

/* Disabled action buttons */
.am-actions .btn[disabled], .am-actions .btn.disabled { background: var(--stroke) !important; color: var(--muted) !important; cursor: default; transform:none !important; box-shadow:none !important; }

@media (max-width: 900px) {
	.am-controls { flex-direction:column; align-items:stretch; }
	.am-left { width:100%; }
	.am-right { width:100%; display:flex; justify-content:flex-start; margin-top:8px; }
}
CSS,

        'src/controllers/accountmanagement/account-load-controller.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = userDbConnection();
    // Determine user DB name safely from environment
    $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('DB_DATABASE', 'my_database')));
    $usersTable = "`" . $userDb . "`.`users`";
    $userlogsTable = "`" . $userDb . "`.`userlogs`";

    $sql = "SELECT u.no AS no, u.id_number AS id_number, u.username AS username, u.firstname, u.middlename, u.lastname, l.last_online AS last_online, l.dateModified AS dateModified, l.status AS status
            FROM {$usersTable} u
            LEFT JOIN {$userlogsTable} l ON l.id_number = u.id_number
            ORDER BY u.no ASC LIMIT 1000";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'rows' => $rows]);
    exit;

} catch (Throwable $e) {
    error_log('Account load failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to load accounts']);
    exit;
}
PHP,

        'public/api/account-load.php' => <<<'PHP'
<?php
// Public API endpoint that loads account data.
// This simply delegates to the controller implementation in src/controllers.
require_once __DIR__ . '/../../src/controllers/accountmanagement/account-load-controller.php';
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

if (!function_exists('userDbConnection')) {
  function userDbConnection(): PDO
  {
    $driver = env('DB_DRIVER', 'mysql') ?? 'mysql';
    $host = env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port = (int) (env('DB_PORT', '3306') ?? 3306);
    $dbname = env('USERDB_NAME', env('DB_DATABASE', 'my_database') ?? 'my_database') ?? 'my_database';
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

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/login-controller.php';
require_once __DIR__ . '/../controllers/password-controller/changepass-controller.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Location: ../../public/index.php');
  exit;
}

$csrf = require __DIR__ . '/csrf.php';
$tokenKey = (string) ($csrf['token_key'] ?? '_csrf');
$postedToken = (string) ($_POST[$tokenKey] ?? '');
$sessionToken = (string) ($_SESSION[$tokenKey] ?? '');

if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
  $_SESSION['login_error'] = 'Invalid session token. Please try again.';
  header('Location: ../../public/index.php?login=invalid_csrf');
  exit;
}

$username = (string) ($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

try {
  $controller = new LoginController();
  $user = $controller->authenticate($username, $password);

  if ($user === null) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: ../../public/index.php?login=failed');
    exit;
  }

  $auth = require __DIR__ . '/auth.php';
  $sessionKey = (string) ($auth['session_key'] ?? 'auth_user');

  session_regenerate_id(true);
  $_SESSION[$sessionKey] = $user;
  unset($_SESSION['login_error']);

  // After authentication, check userlogs for forced password change
  $cp = new ChangePassController();
  $ulog = $cp->getUserLog((string) ($user['id_number'] ?? ''));
  $mustChange = false;
  if (is_array($ulog)) {
    $status = (string) ($ulog['status'] ?? '');
    $dateModified = $ulog['dateModified'] ?? null;
    if ($status === 'reset' || ($status === 'active' && ($dateModified === null || $dateModified === ''))) {
      $mustChange = true;
    }
  }

  if ($mustChange) {
    // mark session so middleware/pages can show the changepass modal
    $_SESSION['must_change_password'] = true;
    header('Location: ../../public/index.php');
    exit;
  }

  // Normal login: update last_online
  try {
    $pdo = userDbConnection();
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $upd = $pdo->prepare('UPDATE userlogs SET last_online = :lo WHERE id_number = :id');
    $upd->execute(['lo' => $now, 'id' => (string) ($user['id_number'] ?? '')]);
  } catch (Throwable $_) {
    // ignore update failures for now
  }

  header('Location: ../../src/pages/home/home.php');
  exit;
} catch (Throwable $e) {
  $_SESSION['login_error'] = 'Login unavailable right now. Please try again.';
  header('Location: ../../public/index.php?login=error');
  exit;
}
PHP,

        'src/config/logout-handler.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../controllers/logout-controller.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Location: ../../src/pages/home/home.php');
  exit;
}

$auth = require __DIR__ . '/auth.php';
$sessionKey = (string) ($auth['session_key'] ?? 'auth_user');

$controller = new LogoutController();
$controller->logout($sessionKey);

header('Location: ../../public/index.php?logout=1');
exit;
PHP,

    'src/config/changepass-handler.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/password-controller/changepass-controller.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Location: ../public/index.php');
  exit;
}

$csrf = require __DIR__ . '/csrf.php';
$tokenKey = (string) ($csrf['token_key'] ?? '_csrf');
$posted = (string) ($_POST[$tokenKey] ?? '');
$sessionToken = (string) ($_SESSION[$tokenKey] ?? '');
if ($posted === '' || $sessionToken === '' || !hash_equals($sessionToken, $posted)) {
  $_SESSION['changepass_error'] = 'Invalid session token.';
  header('Location: ../public/index.php');
  exit;
}

$current = (string) ($_POST['current_password'] ?? '');
$new = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

if ($new === '' || $confirm === '' || $current === '') {
  $_SESSION['changepass_error'] = 'All fields are required.';
  header('Location: ../public/index.php');
  exit;
}

if ($new !== $confirm) {
  $_SESSION['changepass_error'] = 'New password and confirmation do not match.';
  header('Location: ../public/index.php');
  exit;
}

if (!isset($_SESSION['auth_user']) && !isset($_SESSION[(require __DIR__ . '/auth.php')['session_key']])) {
  $_SESSION['changepass_error'] = 'Not authenticated.';
  header('Location: ../public/index.php');
  exit;
}

$auth = require __DIR__ . '/auth.php';
$sessionKey = (string) ($auth['session_key'] ?? 'auth_user');
$user = $_SESSION[$sessionKey] ?? null;
if (!is_array($user) || empty($user['id_number'])) {
  $_SESSION['changepass_error'] = 'Invalid session user.';
  header('Location: ../public/index.php');
  exit;
}

$id = (string) $user['id_number'];
$controller = new ChangePassController();
if (!$controller->verifyCurrentPassword($id, $current)) {
  $_SESSION['changepass_error'] = 'Current password is incorrect.';
  header('Location: ../public/index.php');
  exit;
}

if ($controller->changePassword($id, $new)) {
  unset($_SESSION['must_change_password']);
  header('Location: ../pages/home/home.php');
  exit;
}

$_SESSION['changepass_error'] = 'Unable to update password. Please try again later.';
header('Location: ../public/index.php');
exit;
PHP,

        'src/config/middleware.php' => <<<'PHP'
<?php

declare(strict_types=1);

if (!function_exists('redirect')) {
  function redirect(string $to): never
  {
    header('Location: ' . $to);
    exit;
  }
}

if (!function_exists('appBase')) {
  function appBase(): string
  {
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/(?:(?:public)|(?:src))/.*$#', '', $script);
    if ($base === null) {
      return '';
    }
    return $base === '' ? '' : $base;
  }
}

if (!function_exists('guestOnly')) {
  function guestOnly(): void
  {
    $auth = require __DIR__ . '/auth.php';
    $sessionKey = (string) ($auth['session_key'] ?? 'auth_user');

    if (!empty($_SESSION[$sessionKey])) {
      // If user is authenticated but flagged to change password, allow staying on public index
      if (empty($_SESSION['must_change_password'])) {
        $base = appBase();
        $target = $base . '/src/pages/home/home.php';
        header('Location: ' . $target);
        exit;
      }
    }
  }
}

if (!function_exists('requireAuth')) {
  function requireAuth(): void
  {
    $auth = require __DIR__ . '/auth.php';
    $sessionKey = (string) ($auth['session_key'] ?? 'auth_user');

    if (empty($_SESSION[$sessionKey])) {
      $base = appBase();
      $target = $base . '/public/index.php';
      header('Location: ' . $target);
      exit;
    }

    // If user is authenticated but required to change password, force them back to public index
    if (!empty($_SESSION[$sessionKey]) && !empty($_SESSION['must_change_password'])) {
      $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
      if (strpos($script, '/public/index.php') === false) {
        $base = appBase();
        header('Location: ' . $base . '/public/index.php');
        exit;
      }
    }
  }
}
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

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';

class LoginController
{
    public function authenticate(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $pdo = userDbConnection();
        $stmt = $pdo->prepare('SELECT id_number, username, firstname, middlename, lastname, role, password FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            return null;
        }

        $storedPassword = (string) ($user['password'] ?? '');
        $isValid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

        if (!$isValid) {
            return null;
        }

        unset($user['password']);
        return $user;
    }
}
PHP,

        'src/controllers/usercontroller.php' => <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

class UserController
{
    public function profile(): ?array
    {
        $auth = require __DIR__ . '/../config/auth.php';
        $sessionKey = (string) ($auth['session_key'] ?? 'auth_user');

        $user = $_SESSION[$sessionKey] ?? null;
        return is_array($user) ? $user : null;
    }
}
PHP,

        'src/controllers/logout-controller.php' => <<<'PHP'
<?php

declare(strict_types=1);

class LogoutController
{
  public function logout(string $sessionKey): void
  {
    unset($_SESSION[$sessionKey]);
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
      );
    }

    session_destroy();
  }
}
PHP,

    'src/controllers/password-controller/changepass-controller.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';

class ChangePassController
{
  public function getUserLog(string $id_number): ?array
  {
    $pdo = userDbConnection();
    $stmt = $pdo->prepare('SELECT id_number, status, dateModified, last_online FROM userlogs WHERE id_number = :id LIMIT 1');
    $stmt->execute(['id' => $id_number]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
  }

  public function verifyCurrentPassword(string $id_number, string $currentPassword): bool
  {
    $pdo = userDbConnection();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id_number = :id LIMIT 1');
    $stmt->execute(['id' => $id_number]);
    $row = $stmt->fetch();
    if (!is_array($row)) return false;
    $stored = (string) ($row['password'] ?? '');
    if ($stored === '') return false;
    return password_verify($currentPassword, $stored) || hash_equals($stored, $currentPassword);
  }

  public function changePassword(string $id_number, string $newPassword): bool
  {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hash === false) return false;

    $pdo = userDbConnection();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('UPDATE users SET password = :pw WHERE id_number = :id');
      $stmt->execute(['pw' => $hash, 'id' => $id_number]);

      $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
      $ulog = $pdo->prepare('UPDATE userlogs SET dateModified = :dm, last_online = :lo, status = :st WHERE id_number = :id');
      $ulog->execute(['dm' => $now, 'lo' => $now, 'st' => 'active', 'id' => $id_number]);

      $pdo->commit();
      return true;
    } catch (Throwable $e) {
      $pdo->rollBack();
      return false;
    }
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
<?php
require_once __DIR__ . '/../../config/session.php';
$csrf = require __DIR__ . '/../../config/csrf.php';
$tokenKey = (string) ($csrf['token_key'] ?? '_csrf');

if (empty($_SESSION[$tokenKey])) {
  $_SESSION[$tokenKey] = bin2hex(random_bytes(32));
}

$loginError = (string) ($_SESSION['login_error'] ?? '');
unset($_SESSION['login_error']);
?>

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
      <input type="hidden" name="<?= htmlspecialchars($tokenKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string) $_SESSION[$tokenKey], ENT_QUOTES, 'UTF-8'); ?>">

      <?php if ($loginError !== ''): ?>
        <div class="login-error" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="username">Username</label>
        <div class="input-with-icon">
          <span class="material-icons input-icon">person</span>
          <input type="text" id="username" name="username" placeholder="Username" required style="text-transform:uppercase">
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

    var shouldOpenFromError = window.location.search.indexOf('login=') !== -1 || !!document.querySelector('.login-error');

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

    if (shouldOpenFromError) {
      openModal();
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

        'src/modals/login-modal/changepass-modal.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../../config/session.php';
$csrf = require __DIR__ . '/../../config/csrf.php';
$tokenKey = (string) ($csrf['token_key'] ?? '_csrf');

$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/(?:(?:public)|(?:src))/.*$#', '', $scriptName);
$appBaseUrl = $appBaseUrl === null ? '' : rtrim((string) $appBaseUrl, '/');
$action = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/config/changepass-handler.php';

$error = $_SESSION['changepass_error'] ?? '';
unset($_SESSION['changepass_error']);
?>

<div class="changepass-modal" id="changepassModal" aria-hidden="true">
  <div class="changepass-modal__overlay"></div>
  <div class="changepass-modal__content" role="dialog" aria-modal="true" aria-labelledby="changepassTitle">
    <div class="changepass-modal__header">
      <div class="changepass-modal__illustration" aria-hidden="true">
        <span class="material-icons changepass-modal__icon">lock</span>
      </div>

      <div class="changepass-modal__textblock">
        <h2 class="changepass-modal__title" id="changepassTitle">Change Password</h2>
        <p class="changepass-modal__subtitle">Set a new password to secure your account.</p>
      </div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="changepass-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form id="changepassForm" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="<?= htmlspecialchars($tokenKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string) $_SESSION[$tokenKey] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

      <div class="changepass-modal__body">
        <div class="field">
          <label for="currentPassword">Current password</label>
          <div class="input-with-icon password-wrap">
            <span class="material-icons input-icon">lock</span>
            <input type="password" id="currentPassword" name="current_password" required>
            <button class="password-toggle" data-toggle="currentPassword" type="button" aria-label="Toggle visibility"><span class="material-icons">visibility</span></button>
          </div>
        </div>

        <div class="field">
          <label for="newPassword">New password</label>
          <div class="input-with-icon password-wrap">
            <span class="material-icons input-icon">lock</span>
            <input type="password" id="newPassword" name="new_password" required>
            <button class="password-toggle" data-toggle="newPassword" type="button" aria-label="Toggle visibility"><span class="material-icons">visibility</span></button>
          </div>
        </div>

        <div class="field">
          <label for="confirmPassword">Confirm password</label>
          <div class="input-with-icon password-wrap">
            <span class="material-icons input-icon">lock</span>
            <input type="password" id="confirmPassword" name="confirm_password" required>
            <button class="password-toggle" data-toggle="confirmPassword" type="button" aria-label="Toggle visibility"><span class="material-icons">visibility</span></button>
          </div>
        </div>
      </div>
      <div class="changepass-client-error" id="changepassClientError" aria-live="polite"></div>

      <div class="changepass-modal__actions">
        <button type="submit" class="changepass-submit">
          <span class="material-icons">done</span>
          <span class="changepass-submit__label">Change Password</span>
        </button>
      </div>
    </form>
  </div>
</div>

<link rel="stylesheet" href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/modals/login-modal/changepass-modal.css', ENT_QUOTES, 'UTF-8'); ?>">

<script>
  (function () {
    function toggle(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.type = el.type === 'password' ? 'text' : 'password';
    }

    document.querySelectorAll('.password-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-toggle');
        toggle(target);
        var icon = btn.querySelector('.material-icons');
        if (icon) icon.textContent = icon.textContent === 'visibility' ? 'visibility_off' : 'visibility';
      });
    });

    var form = document.getElementById('changepassForm');
    if (form) {
      var clientError = document.getElementById('changepassClientError');
      function showClientError(msg) {
        if (!clientError) return;
        clientError.textContent = msg;
        clientError.style.display = 'block';
      }
      function clearClientError() {
        if (!clientError) return;
        clientError.textContent = '';
        clientError.style.display = 'none';
      }

      // Real-time match validator
      var newInput = document.getElementById('newPassword');
      var confInput = document.getElementById('confirmPassword');
      function updateMatchState() {
        if (!newInput || !confInput) return;
        var a = newInput.value;
        var b = confInput.value;
        var aWrap = newInput.closest('.input-with-icon');
        var bWrap = confInput.closest('.input-with-icon');
        if ((!a && !b) || (a === '' && b === '')) {
          if (aWrap) { aWrap.classList.remove('input-valid', 'input-invalid'); }
          if (bWrap) { bWrap.classList.remove('input-valid', 'input-invalid'); }
          clearClientError();
          return;
        }
        if (a === b) {
          if (aWrap) { aWrap.classList.add('input-valid'); aWrap.classList.remove('input-invalid'); }
          if (bWrap) { bWrap.classList.add('input-valid'); bWrap.classList.remove('input-invalid'); }
          clearClientError();
          return;
        }
        // Not matching
        if (aWrap) { aWrap.classList.add('input-invalid'); aWrap.classList.remove('input-valid'); }
        if (bWrap) { bWrap.classList.add('input-invalid'); bWrap.classList.remove('input-valid'); }
        // show client error only when user has started typing confirmation
        if (b.length > 0) {
          showClientError('Passwords do not match.');
        }
      }

      if (newInput) newInput.addEventListener('input', updateMatchState);
      if (confInput) confInput.addEventListener('input', updateMatchState);

      form.addEventListener('submit', function (e) {
        var newP = document.getElementById('newPassword').value;
        var conf = document.getElementById('confirmPassword').value;
        var cur = document.getElementById('currentPassword').value;
        clearClientError();
        if (newP !== conf) {
          e.preventDefault();
          showClientError('New password and confirmation do not match.');
          updateMatchState();
          return false;
        }
        if (newP === cur) {
          e.preventDefault();
          showClientError('New password must be different from current password.');
          return false;
        }
        clearClientError();
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

.login-error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 13px;
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
        'src/modals/login-modal/changepass-modal.css' => <<<'CSS'
@import url('../../assets/css/color.css');

.changepass-modal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1300;
  padding: 20px;
}

.changepass-modal.is-open { display: flex; }

.changepass-modal__overlay { position: absolute; inset: 0; background: rgba(16,24,40,0.45); }
.changepass-modal__content {
  position: relative;
  width: min(520px, 96vw);
  background: var(--surface);
  color: var(--ink);
  border: 0;
  border-radius: 12px;
  box-shadow: 0 14px 30px rgba(16,24,40,0.12);
  z-index: 2;
  padding: 18px 20px;
}

.changepass-modal__header {
  display: flex;
  gap: 16px;
  align-items: center;
}

.changepass-modal__illustration {
  flex: 0 0 56px;
  height: 56px;
  border-radius: 999px;
  background: rgba(220,53,69,0.08);
  display: flex;
  align-items: center;
  justify-content: center;
}

.changepass-modal__icon { color: var(--accent); font-size: 22px; }

.changepass-modal__textblock { flex: 1 1 auto; }

.changepass-modal__title { margin:0 0 6px; font-size:18px; font-weight:700; }
.changepass-modal__subtitle { margin:0; color:var(--muted); font-size:13px; }

.changepass-error { border:1px solid #fecaca; background:#fff1f0; color:#991b1b; padding:8px 10px; border-radius:8px; margin:12px 0; }

.changepass-modal__body { margin-top: 12px; }

.field { margin-bottom:12px; }
.field label { display:block; margin-bottom:6px; font-weight:600; color:var(--ink); }
.input-with-icon { display:flex; align-items:center; gap:8px; border:1px solid var(--stroke); border-radius:8px; padding:8px 10px; background:#fff; }
.input-with-icon input { border:0; outline:none; flex:1 1 auto; }
.password-toggle { background:transparent; border:0; cursor:pointer; color:var(--muted); }

.changepass-modal__actions { display:flex; justify-content:flex-end; margin-top: 18px; }
.changepass-submit { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:0; padding:10px 16px; border-radius:10px; cursor:pointer; font-weight:700; }
.changepass-submit:hover { background:var(--accent-dark); }

.changepass-client-error {
  margin-top: 12px;
  color: #991b1b;
  background: #fff7f6;
  border: 1px solid #f5c6cb;
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 13px;
  display: none;
}

/* Real-time validation states for new/confirm inputs */
.input-with-icon.input-valid {
  border-color: #16a34a; /* green */
  box-shadow: 0 4px 10px rgba(22,163,74,0.08);
}
.input-with-icon.input-invalid {
  border-color: #ef4444; /* red */
  box-shadow: 0 4px 10px rgba(239,68,68,0.06);
}
CSS,

        'src/modals/logout-modal/logout-modal.php' => <<<'PHP'
<?php
$appBaseUrl = isset($appBaseUrl) ? rtrim((string) $appBaseUrl, '/') : '';
$logoutAction = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/config/logout-handler.php';
?>

<div class="logout-modal" id="logoutModal" aria-hidden="true">
  <div class="logout-modal__overlay" data-close-logout-modal></div>
  <div class="logout-modal__content" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
    <div class="logout-modal__header">
      <div class="logout-modal__illustration" aria-hidden="true">
        <span class="material-icons logout-modal__icon">logout</span>
      </div>

      <div class="logout-modal__textblock">
        <h2 class="logout-modal__title" id="logoutModalTitle">Confirm Logout</h2>
        <p class="logout-modal__text">Are you sure you want to logout?</p>
      </div>
    </div>

    <div class="logout-modal__actions" role="group" aria-label="Logout actions">
      <button type="button" class="logout-modal__cancel" data-close-logout-modal>
        <span class="material-icons" aria-hidden="true">close</span>
        <span class="logout-modal__label">Cancel</span>
      </button>

      <form method="post" action="<?= htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="logout-modal__confirm">
          <span class="material-icons" aria-hidden="true">done</span>
          <span class="logout-modal__label">Yes, Logout</span>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    var modal = document.getElementById('logoutModal');
    var openBtn = document.getElementById('openLogoutModal');
    var closeBtns = document.querySelectorAll('[data-close-logout-modal]');

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

    if (openBtn) {
      openBtn.addEventListener('click', openModal);
    }

    closeBtns.forEach(function (btn) {
      btn.addEventListener('click', closeModal);
    });
  })();
</script>
PHP,

        'src/modals/logout-modal/logout-modal.css' => <<<'CSS'
@import url('../../assets/css/color.css');

.logout-modal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1200;
  padding: 20px;
}

.logout-modal.is-open {
  display: flex;
}

.logout-modal__overlay {
  position: absolute;
  inset: 0;
  background: var(--muted);
  opacity: 0.45;
}


.logout-modal__content {
  position: relative;
  width: min(520px, 96vw);
  background: var(--surface);
  color: var(--ink);
  border: 0;
  border-radius: 12px;
  box-shadow: 0 14px 30px rgba(16,24,40,0.12);
  z-index: 2;
  padding: 18px 20px;
}

.logout-modal__header {
  display: flex;
  gap: 18px;
  align-items: center;
}

.logout-modal__illustration {
  flex: 0 0 56px;
  height: 56px;
  border-radius: 999px;
  background: rgba(220,53,69,0.10);
  display: flex;
  align-items: center;
  justify-content: center;
}

.logout-modal__icon {
  color: var(--accent);
  font-size: 22px;
}

.logout-modal__textblock { flex: 1 1 auto; }

.logout-modal__title {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
}

.logout-modal__text {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}

.logout-modal__actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  align-items: center;
  margin-top: 18px;
}

.logout-modal__cancel,
.logout-modal__confirm {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 10px 16px;
  cursor: pointer;
  font-weight: 700;
  font-size: 15px;
}

.logout-modal__cancel {
  background: #f3f4f6;
  color: var(--ink);
  border: 0;
  box-shadow: none;
}

.logout-modal__cancel .material-icons { font-size: 18px; color: #374151; }

.logout-modal__confirm {
  background: var(--accent);
  color: var(--surface);
  border: 0;
  box-shadow: 0 6px 18px rgba(220,53,69,0.12);
}

.logout-modal__confirm .material-icons { font-size: 18px; color: #fff; }

.logout-modal__label { display: inline-block; }

.logout-modal__confirm:hover { background: var(--accent-dark); }
CSS,

        'src/pages/home/home.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../controllers/usercontroller.php';

requireAuth();

$userController = new UserController();
$user = $userController->profile();

$displayName = trim((string) (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')));
if ($displayName === '') {
  $displayName = (string) ($user['username'] ?? 'User');
}
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/src/pages/home/home\.php$#', '', $scriptName);
$appBaseUrl = rtrim((string) $appBaseUrl, '/');
$isEntry = (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__));
if ($isEntry) {
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($appBaseUrl . '/src/assets/images/logo2.png', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/public/index.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/templates/sidebar.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/logout-modal/logout-modal.css', ENT_QUOTES, 'UTF-8'); ?>">
  </head>
  <body>
  <?php
}
?>
<div class="app-layout">
  <?php require __DIR__ . '/../../templates/sidebar.php'; ?>

  <main class="main-content">
    <section class="home-page">
      <h1>Home Page</h1>
      <p>Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>.</p>
      <p>You are now logged in.</p>
    </section>
  </main>
</div>

<?php require __DIR__ . '/../../modals/logout-modal/logout-modal.php'; ?>
<?php
if ($isEntry) {
  echo "</body>\n</html>\n";
}
PHP,

        'src/pages/home/home.css' => <<<'CSS'
.home-page {
  padding: 2rem 1rem;
}
CSS,

        'src/templates/sidebar.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/usercontroller.php';

$userController = new UserController();
$user = $userController->profile();
$username = htmlspecialchars(strtoupper((string) ($user['username'] ?? 'Guest')), ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES, 'UTF-8');
$appBaseUrl = isset($appBaseUrl) ? rtrim((string) $appBaseUrl, '/') : '';
// Fallback: if the including page didn't set `$appBaseUrl`, derive a sensible base from the
// current script name so asset links (CSS/images) resolve correctly when this template is
// included from different paths.
if ($appBaseUrl === '') {
  $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
  $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
  $appBaseUrl = rtrim((string) $appBaseUrl, '/');
}
$logoSrc = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/assets/images/logo1.png';
?>

<aside class="sidebar" id="appSidebar" aria-label="Sidebar">
  <div class="sidebar__top">
    <div class="sidebar__brand">
      <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="sidebar__brand-logo">
      <div class="sidebar__brand-text">
        <div class="sidebar__brand-title">Project Title</div>
        <div class="sidebar__brand-sub">Project Subtitle</div>
      </div>
    </div>

    <div class="sidebar__user">
      <div class="sidebar__user-avatar" aria-hidden="true">
        <span class="material-icons">person</span>
      </div>
      <div class="sidebar__user-text">
        <div class="sidebar__user-name"><?= $username; ?></div>
        <div class="sidebar__user-role"><?= $role !== '' ? $role : '&nbsp;'; ?></div>
      </div>
    </div>
  </div>

  <div class="sidebar__content">
    <nav class="sidebar__nav" aria-label="Main navigation">
      <ul class="sidebar__nav-list">
        <li class="sidebar__nav-item has-submenu">
          <button type="button" class="sidebar__nav-link" aria-expanded="false">
            <span class="material-icons sidebar__nav-icon" aria-hidden="true">build</span>
            <span class="sidebar__nav-label">Maintenance</span>
            <span class="material-icons sidebar__nav-chev" aria-hidden="true">expand_more</span>
          </button>
          <ul class="sidebar__submenu">
            <li class="sidebar__submenu-item"><a href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/pages/maintenance/accountmanagement/accountmanagement.php', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar__submenu-link"><span class="sidebar__submenu-label">Account Management</span></a></li>
          </ul>
        </li>
      </ul>
    </nav>
    <div class="sidebar__bottom">
      <button type="button" class="sidebar__logout" id="openLogoutModal" aria-label="Logout">
        <span class="material-icons" aria-hidden="true">logout</span>
        <span class="sidebar__logout-label">Logout</span>
      </button>
    </div>
  </div>
</aside>

<!-- Sidebar expands/collapses on hover (no JS toggle required) -->
<script>
  (function(){
    if (typeof document === 'undefined') return;
    var sidebar = document.getElementById('appSidebar');
    var toggles = Array.prototype.slice.call(document.querySelectorAll('.sidebar__nav-item.has-submenu > .sidebar__nav-link'));

    function closeAll(except){
      toggles.forEach(function(btn){
        if (btn !== except) btn.setAttribute('aria-expanded', 'false');
      });
    }

    toggles.forEach(function(btn){
      btn.addEventListener('click', function(e){
        var expanded = this.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          this.setAttribute('aria-expanded', 'false');
        } else {
          closeAll(this);
          this.setAttribute('aria-expanded', 'true');
        }
      });
      // allow keyboard toggle via Enter/Space
      btn.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    });

    // auto-close when sidebar collapses (mouse leaves)
    if (sidebar) {
      var hideTimeout = null;
      sidebar.addEventListener('mouseleave', function(){
        // small delay so user can move pointer to submenu
        hideTimeout = setTimeout(function(){ closeAll(); }, 180);
      });
      sidebar.addEventListener('mouseenter', function(){ if (hideTimeout) { clearTimeout(hideTimeout); hideTimeout = null; } });
    }
  })();
</script>
PHP,

        'src/templates/header_ui.php' => <<<'PHP'
<?php
// Standalone header UI component (sanitized for copying between projects)
// - No auth/session side-effects
// - Provides `bp_section_header_html($icon, $title, $desc)` for rendering
// - Demo works when opened directly

// Reusable section header component
// Outputs a small header block with icon, title, and description
// Usage: bp_section_header_html('file_upload', 'Title', 'Description');

function bp_section_header_html($iconName, $title, $desc){
  // Normalize icon: prefer Material Icons names; if user passed fa-* fall back to 'file_upload'
  $icon = 'file_upload';
  if (is_string($iconName) && strlen($iconName) > 0){
    if (strpos($iconName, 'fa-') !== false){
      $icon = 'file_upload';
    } else {
      $icon = $iconName;
    }
  }

  // Resolve a safe href for the component CSS. Prefer an absolute web path when the
  // file is under DOCUMENT_ROOT; otherwise fall back to a relative path next to this file.
  $cssHref = (function(){
    $cssFile = __DIR__ . '/header_ui.css';
    if (is_file($cssFile) && isset($_SERVER['DOCUMENT_ROOT'])){
      $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
      $realCss = realpath($cssFile);
      if ($docRoot && $realCss && strpos($realCss, $docRoot) === 0){
        $webPath = str_replace('\\','/', substr($realCss, strlen($docRoot)));
        if ($webPath === '' || $webPath[0] !== '/') $webPath = '/' . $webPath;
        return $webPath;
      }
    }
    // fallback: return a path relative to the current script include
    return 'header_ui.css';
  })();

  // NOTE: the component does NOT automatically inject its stylesheet when used
  // as an include. Please add `<link rel="stylesheet" href="/path/to/header_ui.css">`
  // to your page head (the demo below includes the stylesheet for preview).
  echo '<div class="bp-section-header">';
  echo '<div class="bp-icon-wrap"><span class="material-icons bp-icon">' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '</span></div>';
  echo '<div class="bp-text">';
  echo '<div class="bp-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
  echo '<div class="bp-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>';
  echo '</div></div>';
}

// If this file is opened directly, render a small demo page with a placeholder header
$is_direct = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__));
if ($is_direct){
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Header UI — Demo</title>
    <?php
      // Demo page: include the local stylesheet so preview works
        $demoCssPath = __DIR__ . '/header_ui.css';
        if (is_file($demoCssPath) && isset($_SERVER['SCRIPT_NAME'])){
          $scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
          $demoHref = $scriptDir . '/header_ui.css';
          echo '<link rel="stylesheet" href="' . htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') . '">';
        }
    ?>
  </head>
  <body>
    <?php bp_section_header_html('file_upload','Signature','Manage your signature image'); ?>

  </body>
  </html>
  <?php
}
PHP,

        'src/templates/sidebar.css' => <<<'CSS'
@import url('../assets/css/color.css');

:root {
    --sidebar-width-expanded: 240px;
    --sidebar-width-collapsed: 76px;
}

body {
    margin: 0;
}

.app-layout {
    min-height: 100vh;
}


.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-width-collapsed);
    background: var(--accent);
    color: var(--surface);
    display: flex;
    flex-direction: column;
    transition: width 0.18s ease;
    box-shadow: var(--shadow);
    z-index: 100;
}

.sidebar:hover {
    width: var(--sidebar-width-expanded);
}

.sidebar__top {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 18px 12px;
    gap: 14px;
    min-height: 96px;
    border-bottom: 0.5px solid var(--accent-dark);
}

.sidebar__brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar__brand-logo {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 6px;
    background: transparent;
    padding: 0;
}

.sidebar__brand-text {
    display: none;
    line-height: 1.0;
    white-space: nowrap;
    flex-direction: column;
    justify-content: center;
}

.sidebar:hover .sidebar__brand-text {
    display: flex;
}

.sidebar__brand-title {
    font-size: 18px;
    color: var(--surface);
    font-weight: 700;
}

.sidebar__brand-sub {
    font-size: 14px;
    color: var(--surface);
    opacity: 0.85;
    margin-top: 2px;
}

/* User block */
.sidebar__user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar__user-avatar {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    color: var(--surface);
}

.sidebar__user-text {
    display: none;
    flex-direction: column;
    line-height: 1.0;
}

.sidebar__user-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--surface);
}

.sidebar__user-role {
    font-size: 11px;
    color: var(--surface);
    opacity: 0.85;
    margin-top: 2px;
}

.sidebar:hover .sidebar__user-text {
    display: flex;
}

.sidebar__toggle {
    border: 1px solid var(--surface);
    background: transparent;
    color: var(--surface);
    border-radius: 8px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.sidebar__content {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    flex: 1 1 auto;
    padding: 12px;
}

.sidebar__bottom {
    border-top: 0.5px solid var(--accent-dark);
    padding-top: 12px;
}

.sidebar__logout {
    width: 100%;
    border: 1px solid var(--surface);
    background: var(--accent);
    color: var(--surface);
    border-radius: 10px;
    padding: 10px 12px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: background-color 160ms ease, transform 160ms ease, box-shadow 160ms ease;
}

.sidebar__logout-label {
    white-space: nowrap;
}

.sidebar__logout {
    justify-content: flex-start;
}

.sidebar:hover .sidebar__logout {
    justify-content: flex-start;
}

.sidebar__logout-label {
    display: none;
}

.sidebar:hover .sidebar__logout-label {
    display: inline-block;
}

.sidebar__logout:hover {
    background: var(--accent-dark);
    transform: translateX(3px);
    box-shadow: 0 8px 20px rgba(16, 24, 40, 0.12);
}

/* Navigation (menu + submenu) revamp */
.sidebar__nav {
    margin-bottom: auto;
    padding-top: 4px;
}

.sidebar__nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar__nav-item {
    display: block;
    position: relative;
}

.sidebar__nav-link {
    position: relative;
    width: 100%;
    text-align: left;
    background: transparent;
    border: 1px solid transparent;
    color: var(--surface);
    padding: 11px 12px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
}

.sidebar__nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
    transform: translateX(2px);
}

.sidebar:hover .sidebar__nav-link[aria-expanded="true"] {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
}

.sidebar__nav-label {
    display: none;
    white-space: nowrap;
}

.sidebar:hover .sidebar__nav-label {
    display: inline-block;
}

.sidebar__nav-item.has-submenu .sidebar__submenu {
    display: none;
    list-style: none;
    margin: 8px 0 0 12px;
    padding: 4px 0 2px 18px;
    position: relative;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 170ms ease, transform 170ms ease;
}

/* show submenu only when sidebar expanded AND parent is active (aria-expanded) */
.sidebar__nav-item.has-submenu .sidebar__nav-link[aria-expanded="true"]+.sidebar__submenu {
  /* allow submenu to open when parent is toggled via JS (aria-expanded) */
  display: block;
  opacity: 1;
  transform: translateY(0);
}

.sidebar:hover .sidebar__nav-item.has-submenu .sidebar__nav-link[aria-expanded="true"]+.sidebar__submenu {
  display: block;
  opacity: 1;
  transform: translateY(0);
}

/* Ensure chevron is visible when the parent link is expanded, even if the
   sidebar is collapsed (not hovered). */
.sidebar__nav-link[aria-expanded="true"] .sidebar__nav-chev {
  display: inline-block;
}

.sidebar__nav-item.has-submenu .sidebar__submenu::before {
    content: '';
    position: absolute;
    left: 3px;
    top: 2px;
    bottom: 2px;
    width: 2px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.10);
}

.sidebar__submenu-item {
    position: relative;
    list-style: none;
    margin: 6px 0;
}

.sidebar__submenu-link{
    display:flex;
    align-items:center;
    gap:10px;
    padding:9px 10px 9px 12px;
    border-radius:10px;
    color:var(--surface);
    text-decoration:none;
    font-size:0.95rem;
    font-weight:400;
    transition:background 120ms ease,transform 120ms ease;
}

.sidebar__submenu-link:hover{background:rgba(255,255,255,0.06);transform:translateX(2px)}

.sidebar__submenu-link.is-active{background:rgba(255,255,255,0.10);font-weight:600}

.sidebar__submenu-item::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    width: 10px;
    height: 2px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
}

/* small marker for submenu (removed - using tree connector instead) */

.sidebar__submenu-label {
    display: none;
}

.sidebar:hover .sidebar__submenu-label {
    display: inline-block;
}

.sidebar__submenu-label {
    color: rgba(255, 255, 255, 0.94);
    font-size: 0.94rem;
    font-weight: 400;
}

/* connector marker from parent down to submenu */
.sidebar__nav-item.has-submenu::after {
    content: none;
    position: static;
    left: auto;
    top: auto;
    height: auto;
    width: auto;
    background: transparent;
    border-radius: 0;
    display: none;
}

.sidebar:hover .sidebar__nav-item.has-submenu::after {
    display: block;
}

/* top-level menu icon + chevron */
.sidebar__nav-icon {
    font-size: 18px;
    line-height: 1;
    margin-right: 0;
}

.sidebar__nav-chev {
    margin-left: auto;
    font-size: 18px;
    transform-origin: center;
    transition: transform 160ms ease;
    opacity: 0.9;
    display: none;
}

.sidebar:hover .sidebar__nav-chev {
    display: inline-block;
}

.sidebar__nav-link[aria-expanded="true"] .sidebar__nav-chev {
    transform: rotate(180deg);
}

.main-content {
    margin-left: var(--sidebar-width-collapsed);
    padding: 24px;
    transition: margin-left 0.18s ease;
}

.sidebar:hover~.main-content,
.sidebar:hover+.main-content {
    margin-left: var(--sidebar-width-expanded);
}
CSS,

        'public/index.php' => <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/middleware.php';
guestOnly();
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
  <?php require __DIR__ . '/../src/modals/login-modal/changepass-modal.php'; ?>
  <?php if (!empty($_SESSION['must_change_password'])): ?>
  <script>
    (function () {
      var loginModal = document.getElementById('loginModal');
      if (loginModal) {
        loginModal.classList.remove('is-open');
        loginModal.setAttribute('aria-hidden', 'true');
      }
      var changeModal = document.getElementById('changepassModal');
      if (changeModal) {
        changeModal.classList.add('is-open');
        changeModal.setAttribute('aria-hidden', 'false');
      }
    })();
  </script>
  <?php endif; ?>
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
  - Root `.htaccess` using a relative redirect to `public/` (preserves parent path)

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

- The generator writes a root `.htaccess` that redirects requests for the
  project root to `public/` using a relative rule. This preserves the full
  parent path (useful when the project is served under a subpath). If you
  move the project, regenerate the `.htaccess` to update behavior if needed.

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
    }

    foreach ($templates as $relativeFile => $content) {
        $absoluteFile = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);
        $compiled = renderTemplate($content, $projectName, $projectTitle);

      if (str_ends_with($relativeFile, '.php')) {
        $compiled = ltrim($compiled, "\xEF\xBB\xBF \t\n\r\0\x0B");
      }

        if (!ensureFile($absoluteFile, $projectRoot, rtrim($compiled, "\r\n") . PHP_EOL)) {
            return false;
        }
    }

    if (!downloadMigrationFiles($projectRoot)) {
      return false;
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
      $repoRawBase = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/assets/images';
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

    return finalizeGeneratedProject($projectRoot);
}

function finalizeGeneratedProject(string $projectRoot): bool
{
  $htPath = $projectRoot . DIRECTORY_SEPARATOR . '.htaccess';
  $htContent = "RewriteEngine On\nRewriteRule ^$ public/ [R=302,L]\n";

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

function downloadMigrationFiles(string $projectRoot): bool
{
  $migrationDir = $projectRoot . DIRECTORY_SEPARATOR . 'migration' . DIRECTORY_SEPARATOR . 'userdb';
  if (!ensureDirectory($migrationDir, $projectRoot)) {
    return false;
  }

  $baseRawUrl = 'https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/migration/userdb';
  $files = ['userdb_users.sql', 'userdb_userlogs.sql'];

  foreach ($files as $fileName) {
    $url = $baseRawUrl . '/' . $fileName;
    $target = $migrationDir . DIRECTORY_SEPARATOR . $fileName;

    if (file_exists($target)) {
      report('file', $target, $projectRoot, 'EXISTS');
      continue;
    }

    $content = downloadRemoteText($url);
    if ($content === null) {
      report('file', $target, $projectRoot, 'FAILED');
      return false;
    }

    $written = file_put_contents($target, rtrim($content, "\r\n") . PHP_EOL);
    report('file', $target, $projectRoot, $written === false ? 'FAILED' : 'OK');
    if ($written === false) {
      return false;
    }
  }

  return true;
}

function downloadRemoteText(string $url): ?string
{
  if (function_exists('curl_version')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $body = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $body !== false;
    curl_close($ch);
    return $ok ? (string) $body : null;
  }

  $opts = stream_context_create(['http' => ['timeout' => 20]]);
  $body = @file_get_contents($url, false, $opts);
  if ($body === false) {
    return null;
  }

  return $body;
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
