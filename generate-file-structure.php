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

    // Use embedded templates by default. External audit templates are opt-in
    // to avoid stale snapshots silently overriding generator updates.
    $useAuditTemplates = (getenv('ML_USE_AUDIT_TEMPLATES') === '1');
    if ($useAuditTemplates && is_dir(__DIR__ . '/audit/scaffold_templates')) {
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
<?php require __DIR__ . '/../../../modals/accountmanagement/add-account-modal.php'; ?>
<?php require __DIR__ . '/../../../modals/accountmanagement/edit-account-modal.php'; ?>
<?php require __DIR__ . '/../../../modals/accountmanagement/reset-account-modal.php'; ?>
<?php require __DIR__ . '/../../../modals/accountmanagement/change-status-modal.php'; ?>
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
    'src/controllers/accountmanagement/account-edit-controller.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';

function edit_account_from_request(): array {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return ['ok' => false, 'message' => 'Method not allowed', 'code' => 405];
  }

  $id_number = trim((string)($_POST['id_number'] ?? ''));
  $firstname = trim((string)($_POST['firstname'] ?? ''));
  $middlename = trim((string)($_POST['middlename'] ?? ''));
  $lastname = trim((string)($_POST['lastname'] ?? ''));

  if ($id_number === '') {
    return ['ok' => false, 'message' => 'ID Number is required', 'code' => 400];
  }

  $pdo = userDbConnection();
  $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'my_database'))));
  $usersTable = "`" . $userDb . "`.`users`";
  $userlogsTable = "`" . $userDb . "`.`userlogs`";

  try {
    $pdo->beginTransaction();

    // Update name fields (do not assume `dateModified` exists in this schema)
    $ustmt = $pdo->prepare("UPDATE {$usersTable} SET `firstname` = :first, `middlename` = :middle, `lastname` = :last WHERE `id_number` = :id");
    $ustmt->execute([':first' => $firstname, ':middle' => $middlename, ':last' => $lastname, ':id' => $id_number]);

    $lstmt = $pdo->prepare("UPDATE {$userlogsTable} SET `dateModified` = NOW() WHERE `id_number` = :id");
    $lstmt->execute([':id' => $id_number]);

    $pdo->commit();
    return ['ok' => true, 'message' => 'Account updated'];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Account edit failed: ' . $e->getMessage());
    return ['ok' => false, 'message' => 'Failed to update account'];
  }
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

  // After authentication, check userlogs for forced password change or missing entries
  $cp = new ChangePassController();
  $ulog = $cp->getUserLog((string) ($user['id_number'] ?? ''));
  // Debug: log fetched userlogs for inspection
  try {
    @file_put_contents(__DIR__ . '/../../../logs/login-debug.log', date('c') . " ULOG: " . json_encode(['id' => $user['id_number'] ?? null, 'ulog' => $ulog]) . "\n", FILE_APPEND | LOCK_EX);
  } catch (Throwable $_) {}
  // If the user's id_number does not exist in userlogs, show a specific error so admin can investigate
  if (!is_array($ulog)) {
    $_SESSION['login_error'] = 'Somethings Wrong! Contact Administrator';
    header('Location: ../../public/index.php?login=missing_userlog');
    exit;
  }

  // If the account has been disabled in userlogs, block login with a clear message
  if (isset($ulog['status']) && (string)$ulog['status'] === 'disabled') {
    $_SESSION['login_error'] = 'Your Account has been Disabled!';
    header('Location: ../../public/index.php?login=disabled');
    exit;
  }

  $mustChange = false;
  $status = (string) ($ulog['status'] ?? '');
  $dateModified = $ulog['dateModified'] ?? null;
  // treat NULL, empty string, or zero-datetime as requiring change
  $dmEmpty = ($dateModified === null || $dateModified === '' || $dateModified === '0000-00-00 00:00:00');
  if ($status === 'reset' || ($status === 'active' && $dmEmpty)) {
    $mustChange = true;
  }
  if ($status !== 'active' && $status !== 'reset' && $status !== 'disabled') {
    // unexpected status value — log for admin review
    error_log("Login: unexpected userlogs.status='{$status}' for id=" . (($user['id_number'] ?? '')));
  }

  // Now set session (only after userlogs check passes)
  session_regenerate_id(true);
  $_SESSION[$sessionKey] = $user;
  unset($_SESSION['login_error']);

  if ($mustChange) {
    // mark session so middleware/pages can show the changepass modal
    $_SESSION['must_change_password'] = true;
    header('Location: ../../public/index.php');
    exit;
  }

  // Normal login: update last_online
  try {
    $pdo = userDbConnection();
    $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'my_database'))));
    $userlogsTable = "`" . $userDb . "`.`userlogs`";
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $upd = $pdo->prepare("UPDATE {$userlogsTable} SET last_online = :lo WHERE id_number = :id");
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
          // Debug: log failure to find user
          @file_put_contents(__DIR__ . '/../../../logs/login-debug.log', date('c') . " AUTH: user not found for username={$username}\n", FILE_APPEND | LOCK_EX);
          return null;
        }

        $storedPassword = (string) ($user['password'] ?? '');
        $isValid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

        // Debug: log authentication attempt (do NOT log plaintext password)
        try {
          $dbg = [
            'time' => date('c'),
            'username' => $username,
            'id_number' => $user['id_number'] ?? null,
            'role' => $user['role'] ?? null,
            'stored_password_hinted' => (bool) preg_match('/^\$2[ayb]\$/', $storedPassword),
            'password_verify' => $isValid,
          ];
          @file_put_contents(__DIR__ . '/../../../logs/login-debug.log', json_encode($dbg) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $_) {}

        if (!$isValid) {
          @file_put_contents(__DIR__ . '/../../../logs/login-debug.log', date('c') . " AUTH: password invalid for username={$username}\n", FILE_APPEND | LOCK_EX);
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

        'src/modals/accountmanagement/add-account-modal.php' => <<<'PHP'
<?php
// Use the page-provided $appBaseUrl when included; otherwise compute a safe fallback.
if (!isset($appBaseUrl) || $appBaseUrl === null) {
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
    $appBaseUrl = rtrim((string) $appBaseUrl, '/');
}
if ($appBaseUrl !== '' && strpos($appBaseUrl, '/') !== 0) {
    $appBaseUrl = '/' . $appBaseUrl;
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/accountmanagement/add-account-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<div id="addAccountModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="addAccountTitle">
  <div class="modal-card" role="document">
    <div class="modal-top">
      <div class="modal-icon-bg"><span class="material-icons modal-icon">person_add</span></div>
      <div class="modal-title-wrap">
        <h3 id="addAccountTitle">Create Account</h3>
        <p class="modal-sub">Create a new user account and set initial access level.</p>
      </div>
    </div>

    <div style="padding:0 20px 18px 20px;">
      <form id="addAccountForm" class="add-account-modal__form" autocomplete="off">
        <div class="field input-with-status">
          <label for="aa-id">ID Number</label>
          <input id="aa-id" name="id_number" required>
          <span id="aa-id-status" class="input-status" aria-hidden="true" style="display:none"></span>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="aa-first">Firstname</label>
            <input id="aa-first" name="firstname">
          </div>
          <div class="field">
            <label for="aa-middle">Middlename</label>
            <input id="aa-middle" name="middlename">
          </div>
          <div class="field">
            <label for="aa-last">Lastname</label>
            <input id="aa-last" name="lastname">
          </div>
        </div>
        <div class="field">
          <label for="aa-username">Username</label>
          <input id="aa-username" name="username" required readonly placeholder="will be generated">
        </div>
        <div class="field">
          <label for="aa-role">Role</label>
          <select id="aa-role" name="role">
            <option value="Public">Public</option>
            <option value="Admin">Admin</option>
          </select>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="aa-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary" id="aa-create">Create Account</button>
        </div>
      </form>

      <div id="aa-success" class="account-created" style="display:none">
        <div class="check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg">Account created successfully</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="aa-ok">Okay</button></div>
      </div>
      <div id="aa-failure" class="account-failed" style="display:none">
        <div class="x" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg" id="aa-failure-msg">Failed to create account</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="aa-failure-ok">Okay</button></div>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    var overlay = document.getElementById('addAccountModal');
    var openBtn = document.getElementById('am-add-btn');
    var cancelBtn = document.getElementById('aa-cancel');
    var okBtn = document.getElementById('aa-ok');
    var form = document.getElementById('addAccountForm');
    var success = document.getElementById('aa-success');
    var idField = document.getElementById('aa-id');
    var lastField = document.getElementById('aa-last');
    var usernameField = document.getElementById('aa-username');
    var idStatus = document.getElementById('aa-id-status');
    var createBtn = document.getElementById('aa-create');

    function open(){
      if (!overlay) return;
      overlay.classList.remove('hidden');
      overlay.setAttribute('aria-hidden','false');
      try{ overlay.inert = false; }catch(e){}
      setTimeout(function(){ if (idField && typeof idField.focus === 'function') idField.focus(); }, 10);
    }

    function close(){
      if (!overlay) return;
      try{
        var active = document.activeElement;
        if (overlay.contains(active)){
          if (openBtn && typeof openBtn.focus === 'function'){
            openBtn.focus();
          } else if (document.body && typeof document.body.focus === 'function'){
            document.body.focus();
          } else if (document.documentElement && typeof document.documentElement.focus === 'function'){
            document.documentElement.focus();
          }
        }
      } catch(e) {}

      overlay.classList.add('hidden');
      overlay.setAttribute('aria-hidden','true');
      try{ overlay.inert = true; }catch(e){}
      if (success) success.style.display='none';
      if (form) { form.style.display='block'; form.reset(); }
    }

    if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); open(); });
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ close(); });

    function generateUsername(){
      if (!usernameField) return;
      var id = idField ? idField.value.trim() : '';
      var last = lastField ? lastField.value.trim() : '';
      if (!id || !last) { usernameField.value = ''; return; }
      var part = last.replace(/[^A-Za-z0-9]/g,'').slice(0,4).toLowerCase();
      usernameField.value = part + id;
    }

    function checkIdExists(id){
      if (!idField || !idStatus) return Promise.resolve({ok:false});
      if (!id || id.length === 0){ idStatus.style.display='none'; idField.classList.remove('input-valid','input-invalid'); if (createBtn) createBtn.disabled = false; return Promise.resolve({ok:true, exists:false}); }
      idStatus.style.display='flex'; idStatus.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="#cfcfcf" stroke-width="2"></circle></svg>';
      return fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-check.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && (typeof json.exists !== 'undefined')){
            if (json.exists){
              idStatus.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
              idField.classList.remove('input-valid'); idField.classList.add('input-invalid'); if (createBtn) createBtn.disabled = true;
            } else {
              idStatus.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="#2e7d32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
              idField.classList.remove('input-invalid'); idField.classList.add('input-valid'); if (createBtn) createBtn.disabled = false;
            }
            return { ok:true, exists: !!json.exists };
          }
          idStatus.style.display='none'; idField.classList.remove('input-valid','input-invalid'); if (createBtn) createBtn.disabled = false; return { ok:false };
        })
        .catch(function(){ idStatus.style.display='none'; idField.classList.remove('input-valid','input-invalid'); if (createBtn) createBtn.disabled = false; return { ok:false }; });
    }

    var debouncedCheck = (function(){ var t; return function(id){ clearTimeout(t); t = setTimeout(function(){ checkIdExists(id); }, 300); }; })();

    if (idField) { idField.addEventListener('input', function(e){ generateUsername(); debouncedCheck(idField.value.trim()); }); }
    if (lastField) lastField.addEventListener('input', generateUsername);

    if (form) form.addEventListener('submit', function(ev){
      ev.preventDefault();
      if (createBtn) createBtn.disabled = true;
      var fd = new FormData(form);
      function showFailure(msg){ if (createBtn) createBtn.disabled = false; if (form) form.style.display='none'; if (success) success.style.display='none'; var f = document.getElementById('aa-failure'); if (f) { f.style.display = 'block'; var m = document.getElementById('aa-failure-msg'); if (m) m.textContent = msg || 'Failed to create account'; } }

      fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-create.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function(r){ return r.json().catch(function(){ return { ok:false, message:'Invalid server response' }; }); })
        .then(function(json){
          if (createBtn) createBtn.disabled = false;
          if (json && (json.success || json.ok)){
            if (form) form.style.display='none'; if (success) success.style.display='block';
            if (window.refreshAccountManagement) try{ window.refreshAccountManagement(); }catch(e){}
          } else {
            showFailure((json && json.message) ? json.message : 'Failed to create account');
          }
        })
        .catch(function(){ showFailure('Failed to create account'); });
    });

    if (okBtn) okBtn.addEventListener('click', function(){ close(); });
    var failureOk = document.getElementById('aa-failure-ok');
    if (failureOk) failureOk.addEventListener('click', function(){ close(); });
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  })();
</script>
PHP,

        'src/modals/accountmanagement/add-account-modal.css' => <<<'CSS'
    @import url('../../assets/css/color.css');

    .modal {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.4);
      z-index: 1200;
    }

    .modal.hidden {
      display: none;
    }

    .modal-card {
      background: var(--surface);
      width: 520px;
      border-radius: 8px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .modal-top {
      display: flex;
      gap: 12px;
      padding: 20px;
      align-items: center;
    }

    .modal-icon-bg {
      box-sizing: border-box;
      width: 56px;
      height: 56px;
      min-width: 56px;
      min-height: 56px;
      border-radius: 50%;
      background: color-mix(in srgb, var(--accent) 15%, var(--surface));
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
      padding: 0;
    }

    .modal-icon {
      color: var(--accent-dark);
      font-size: 28px;
      line-height: 1;
      display: inline-block;
    }

    .modal-title-wrap h3 {
      margin: 0 0 6px 0;
      font-size: 18px;
    }

    .modal-sub {
      margin: 0;
      color: var(--muted);
      font-size: 14px;
    }

    .modal-actions {
      display: flex;
      gap: 8px;
      padding: 16px;
      justify-content: flex-end;
    }

    .btn {
      padding: 8px 12px;
      border-radius: 4px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: background-color 160ms ease, color 160ms ease, transform 120ms ease, box-shadow 160ms ease;
    }

    .btn-primary {
      background: var(--accent);
      color: #fff;
    }

    .btn-danger {
      background: var(--accent);
      color: #fff;
    }

    .btn-secondary {
      background: var(--stroke);
      color: var(--ink);
    }

    /* Hover/active states */
    .btn-primary:hover{ background: var(--accent-dark); box-shadow: 0 6px 18px rgba(16,24,40,0.06); transform: translateY(-1px); }
    .btn-primary:active{ transform: translateY(0); }
    .btn-secondary:hover{ background: color-mix(in srgb, var(--accent) 6%, var(--stroke)); }
    .btn-secondary:active{ transform: translateY(0); }

    .add-account-modal__form .field {
      margin-bottom: 10px
    }

    .add-account-modal__form label {
      display: block;
      font-size: 12px;
      margin-bottom: 4px;
      color: var(--ink)
    }

    .add-account-modal__form input,
    .add-account-modal__form select {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--stroke);
      border-radius: 6px;
      background: var(--surface);
      color: var(--ink)
    }

    /* ID input status */
    .input-with-status{ position:relative }
    .input-status{ position:absolute; right:12px; top:auto; bottom:14px; transform:none; pointer-events:none; display:flex; align-items:center; justify-content:center; }
    .input-status svg{ width:18px; height:18px }
    .input-valid{ border-color: color-mix(in srgb, var(--accent) 18%, var(--surface)); }
    .input-invalid{ border-color: #dc3545; }
    .input-with-status input{ padding-right:40px; }

    .account-created {
      text-align: center;
      padding: 20px
    }

    .account-created .check {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--accent) 15%, var(--surface));
      color: var(--accent-dark);
      margin-bottom: 12px
    }

    .account-created .check svg {
      width: 36px;
      height: 36px
    }

    .account-failed { text-align:center; padding:20px }
    .account-failed .x { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 12%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
    .account-failed .x svg { width:36px; height:36px }

    /* Inline field row for firstname/middlename/lastname */
    .field-row{ display:flex; gap:16px; margin-bottom:12px; align-items:flex-start }
    .field-row .field{ flex:1; margin-bottom:0; padding-left:4px; padding-right:4px }
    @media (max-width:720px){
      .field-row{ flex-direction:column }
    }

    .add-account-modal__form .field input, .add-account-modal__form .field select { box-sizing: border-box; }
    CSS,

        'src/modals/accountmanagement/edit-account-modal.php' => <<<'PHP'
<?php
// Use page-provided $appBaseUrl or compute fallback
if (!isset($appBaseUrl) || $appBaseUrl === null) {
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
    $appBaseUrl = rtrim((string) $appBaseUrl, '/');
}
if ($appBaseUrl !== '' && strpos($appBaseUrl, '/') !== 0) { $appBaseUrl = '/' . $appBaseUrl; }
?>

<link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/accountmanagement/edit-account-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<div id="editAccountModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="editAccountTitle">
  <div class="modal-card" role="document">
    <div class="modal-top">
      <div class="modal-icon-bg"><span class="material-icons modal-icon">edit</span></div>
      <div class="modal-title-wrap">
        <h3 id="editAccountTitle">Edit Account</h3>
        <p class="modal-sub">Update the account's name details.</p>
      </div>
    </div>

    <div style="padding:0 20px 18px 20px;">
      <form id="editAccountForm" class="edit-account-modal__form" autocomplete="off">
        <div class="field">
          <label for="ea-id">ID Number</label>
          <input id="ea-id" name="id_number" readonly>
        </div>
        <div class="field">
          <label for="ea-username">Username</label>
          <input id="ea-username" name="username" readonly>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="ea-first">Firstname</label>
            <input id="ea-first" name="firstname">
          </div>
          <div class="field">
            <label for="ea-middle">Middlename</label>
            <input id="ea-middle" name="middlename">
          </div>
          <div class="field">
            <label for="ea-last">Lastname</label>
            <input id="ea-last" name="lastname">
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="ea-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary" id="ea-save">Save Changes</button>
        </div>
      </form>

      <div id="ea-success" class="account-created" style="display:none">
        <div class="check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg">Account updated successfully</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="ea-ok">Okay</button></div>
      </div>
      <div id="ea-failure" class="account-failed" style="display:none">
        <div class="x" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg" id="ea-failure-msg">Failed to update account</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="ea-failure-ok">Okay</button></div>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    var overlay = document.getElementById('editAccountModal');
    var openBtn = document.getElementById('am-edit-btn');
    var cancelBtn = document.getElementById('ea-cancel');
    var okBtn = document.getElementById('ea-ok');
    var form = document.getElementById('editAccountForm');
    var success = document.getElementById('ea-success');
    var idField = document.getElementById('ea-id');
    var usernameField = document.getElementById('ea-username');
    var firstField = document.getElementById('ea-first');
    var middleField = document.getElementById('ea-middle');
    var lastField = document.getElementById('ea-last');
    var failure = document.getElementById('ea-failure');

    function open(){
      if (!overlay) return;
      overlay.classList.remove('hidden');
      overlay.setAttribute('aria-hidden','false');
      try{ overlay.inert = false; }catch(e){}
      setTimeout(function(){ if (firstField && typeof firstField.focus === 'function') firstField.focus(); }, 10);
    }

    function close(){
      if (!overlay) return;
      try{
        var active = document.activeElement;
        if (overlay.contains(active)){
          if (openBtn && typeof openBtn.focus === 'function'){
            openBtn.focus();
          } else if (document.body && typeof document.body.focus === 'function'){
            document.body.focus();
          } else if (document.documentElement && typeof document.documentElement.focus === 'function'){
            document.documentElement.focus();
          }
        }
      } catch(e) {}

      overlay.classList.add('hidden');
      overlay.setAttribute('aria-hidden','true');
      try{ overlay.inert = true; }catch(e){}
      if (success) success.style.display='none';
      if (failure) failure.style.display='none';
      if (form) { form.style.display='block'; }
    }

    function populateFromSelection(){
      var sel = window.amSelectedAccount;
      if (!sel || !sel.row) return false;
      var tr = sel.row;
      var cells = tr.querySelectorAll('td');
      if (!cells || cells.length < 6) return false;
      if (idField) idField.value = (cells[1] && cells[1].textContent) ? cells[1].textContent.trim() : '';
      if (usernameField) usernameField.value = (cells[2] && cells[2].textContent) ? cells[2].textContent.trim() : '';
      if (firstField) firstField.value = (cells[3] && cells[3].textContent) ? cells[3].textContent.trim() : '';
      if (middleField) middleField.value = (cells[4] && cells[4].textContent) ? cells[4].textContent.trim() : '';
      if (lastField) lastField.value = (cells[5] && cells[5].textContent) ? cells[5].textContent.trim() : '';
      generateUsername();
      return true;
    }

    function generateUsername(){ if (!usernameField) return; var id = idField ? idField.value.trim() : ''; var last = lastField ? lastField.value.trim() : ''; if (!id || !last) { return; } var part = last.replace(/[^A-Za-z0-9]/g,'').slice(0,4).toLowerCase(); usernameField.value = part + id; }

    if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); if (!populateFromSelection()) return; open(); });
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ close(); });

    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var btn = document.getElementById('ea-save'); if (btn) btn.disabled = true;
      var fd = new FormData(form);
      function showFailure(msg){ if (btn) btn.disabled = false; if (form) form.style.display='none'; if (success) success.style.display='none'; if (failure) { failure.style.display = 'block'; var m = document.getElementById('ea-failure-msg'); if (m) m.textContent = msg || 'Failed to update account'; } }

      fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-edit.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function(r){ return r.json().catch(function(){ return { ok:false, message:'Invalid server response' }; }); })
        .then(function(json){
          if (btn) btn.disabled = false;
          if (json && json.ok){ if (form) form.style.display='none'; if (success) success.style.display='block'; if (window.refreshAccountManagement) try{ window.refreshAccountManagement(); }catch(e){} } else { showFailure((json && json.message) ? json.message : 'Failed to update account'); }
        })
        .catch(function(){ showFailure('Failed to update account'); });
    });

    if (okBtn) okBtn.addEventListener('click', function(){ close(); });
    var failureOk = document.getElementById('ea-failure-ok'); if (failureOk) failureOk.addEventListener('click', function(){ close(); });
    if (lastField) lastField.addEventListener('input', generateUsername);
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  })();
</script>
PHP,

        'src/modals/accountmanagement/edit-account-modal.css' => <<<'CSS'

      .modal {
          position: fixed;
          inset: 0;
          display: flex;
          align-items: center;
          justify-content: center;
          background: rgba(0, 0, 0, 0.4);
          z-index: 1200;
      }

      .modal.hidden { display: none; }

      .modal-card { background: var(--surface); width: 520px; border-radius: 8px; box-shadow: var(--shadow); overflow: hidden; }

      .modal-top { display:flex; gap:12px; padding:20px; align-items:center }
      .modal-icon-bg { box-sizing: border-box; width:56px; height:56px; min-width:56px; min-height:56px; border-radius:50%; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; padding:0 }
      .modal-icon { color: var(--accent-dark); font-size:28px; line-height:1 }
      .modal-title-wrap h3 { margin:0 0 6px 0; font-size:18px }
      .modal-sub { margin:0; color:var(--muted); font-size:14px }

      .modal-actions { display:flex; gap:8px; padding:16px; justify-content:flex-end }
      .btn { padding:8px 12px; border-radius:4px; border:1px solid transparent; cursor:pointer; transition: background-color 160ms ease, color 160ms ease, transform 120ms ease, box-shadow 160ms ease }
      .btn-primary { background: var(--accent); color:#fff }
      .btn-secondary { background: var(--stroke); color: var(--ink) }
      .btn-primary:hover{ background: var(--accent-dark); box-shadow: 0 6px 18px rgba(16,24,40,0.06); transform: translateY(-1px); }

      .edit-account-modal__form .field { margin-bottom:10px }
      .edit-account-modal__form label { display:block; font-size:12px; margin-bottom:4px; color:var(--ink) }
      .edit-account-modal__form input, .edit-account-modal__form select { width:100%; padding:10px; border:1px solid var(--stroke); border-radius:6px; background:var(--surface); color:var(--ink) }

      .field-row{ display:flex; gap:16px; margin-bottom:12px; align-items:flex-start }
      .field-row .field{ flex:1; margin-bottom:0; padding-left:4px; padding-right:4px }
      @media (max-width:720px){ .field-row{ flex-direction:column } }

      .account-created { text-align:center; padding:20px }
      .account-created .check { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
      .account-created .check svg { width:36px; height:36px }
      .account-failed { text-align:center; padding:20px }
      .account-failed .x { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 12%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
      .account-failed .x svg { width:36px; height:36px }

      .edit-account-modal__form .field input, .edit-account-modal__form .field select { box-sizing: border-box }
      CSS,

          'src/modals/accountmanagement/reset-account-modal.php' => <<<'PHP'
  <?php
  // Use the page-provided $appBaseUrl when included; otherwise compute a safe fallback.
  if (!isset($appBaseUrl) || $appBaseUrl === null) {
      $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
      $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
      $appBaseUrl = rtrim((string) $appBaseUrl, '/');
  }
  if ($appBaseUrl !== '' && strpos($appBaseUrl, '/') !== 0) {
      $appBaseUrl = '/' . $appBaseUrl;
  }
  ?>

  <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/accountmanagement/reset-account-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

  <div id="resetAccountModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="resetAccountTitle">
    <div class="modal-card" role="document">
      <div class="modal-top">
        <div class="modal-icon-bg"><span class="material-icons modal-icon">vpn_key</span></div>
        <div class="modal-title-wrap">
          <h3 id="resetAccountTitle">Reset this Account?</h3>
          <p class="modal-sub">This will reset the user's password to the default format.</p>
        </div>
      </div>

      <div style="padding:0 20px 18px 20px;">
        <form id="resetAccountForm" class="reset-account-modal__form" autocomplete="off">
          <div class="field">
            <label for="ra-id">ID Number</label>
            <input id="ra-id" name="id_number" readonly>
          </div>
          <div class="field">
            <label for="ra-username">Username</label>
            <input id="ra-username" name="username" readonly>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="ra-cancel">Cancel</button>
            <button type="submit" class="btn btn-primary" id="ra-reset">Reset Password</button>
          </div>
        </form>

        <div id="ra-success" class="account-created" style="display:none">
          <div class="check" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div class="msg">Password Reset Successfully</div>
          <div style="margin-top:12px"><button class="btn btn-primary" id="ra-ok">Okay</button></div>
        </div>
        <div id="ra-failure" class="account-failed" style="display:none">
          <div class="x" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div class="msg" id="ra-failure-msg">Password Reset Failed</div>
          <div style="margin-top:12px"><button class="btn btn-primary" id="ra-failure-ok">Okay</button></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var overlay = document.getElementById('resetAccountModal');
      var openBtn = document.getElementById('am-reset-btn');
      var cancelBtn = document.getElementById('ra-cancel');
      var okBtn = document.getElementById('ra-ok');
      var form = document.getElementById('resetAccountForm');
      var success = document.getElementById('ra-success');
      var failure = document.getElementById('ra-failure');
      var idField = document.getElementById('ra-id');
      var usernameField = document.getElementById('ra-username');

      function open(){ if (!overlay) return; try{ overlay.inert = false; }catch(e){} overlay.classList.remove('hidden'); overlay.setAttribute('aria-hidden','false'); }
      function close(){ if (!overlay) return;
        try{
          var active = document.activeElement;
          if (overlay.contains(active)){
            if (openBtn && typeof openBtn.focus === 'function'){
              openBtn.focus();
            } else if (document.body && typeof document.body.focus === 'function'){
              document.body.focus();
            } else if (document.documentElement && typeof document.documentElement.focus === 'function'){
              document.documentElement.focus();
            }
          }
        }catch(e){}
        try{ overlay.inert = true; }catch(e){}
        overlay.classList.add('hidden'); overlay.setAttribute('aria-hidden','true'); if (success) success.style.display='none'; if (failure) failure.style.display='none'; if (form) { form.style.display='block'; } }

      function populateFromSelection(){
        var sel = window.amSelectedAccount;
        if (!sel || !sel.row) return false;
        var tr = sel.row;
        var cells = tr.querySelectorAll('td');
        if (!cells || cells.length < 3) return false;
        if (idField) idField.value = (cells[1] && cells[1].textContent) ? cells[1].textContent.trim() : '';
        if (usernameField) usernameField.value = (cells[2] && cells[2].textContent) ? cells[2].textContent.trim() : '';
        return true;
      }

      if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); if (!populateFromSelection()) return; open(); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ close(); });

      form.addEventListener('submit', function(ev){
        ev.preventDefault();
        var btn = document.getElementById('ra-reset'); if (btn) btn.disabled = true;
        var fd = new FormData(form);
        function showFailure(msg){ if (btn) btn.disabled = false; if (form) form.style.display='none'; if (success) success.style.display='none'; if (failure) { failure.style.display = 'block'; var m = document.getElementById('ra-failure-msg'); if (m) m.textContent = msg || 'Password Reset Failed'; } }

        fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-reset.php', { method: 'POST', credentials: 'same-origin', body: fd })
          .then(function(r){ return r.json().catch(function(){ return { ok:false, message:'Invalid server response' }; }); })
          .then(function(json){
            if (btn) btn.disabled = false;
            if (json && json.ok){
              if (form) form.style.display='none'; if (success) success.style.display='block';
              try{
                var sel = window.amSelectedAccount;
                if (sel && sel.row){
                  var tr = sel.row;
                  var cells = tr.querySelectorAll('td');
                  if (cells && cells.length > 7){
                    cells[7].textContent = json.dateModified || new Date().toLocaleString();
                  }
                  tr.dataset.status = 'reset';
                }
              }catch(e){}
              if (window.refreshAccountManagement) try{ window.refreshAccountManagement(); }catch(e){}
            } else {
              showFailure((json && json.message) ? json.message : 'Password Reset Failed');
            }
          })
          .catch(function(){ showFailure('Password Reset Failed'); });
      });

      if (okBtn) okBtn.addEventListener('click', function(){ close(); });
      var failureOk = document.getElementById('ra-failure-ok');
      if (failureOk) failureOk.addEventListener('click', function(){ close(); });
      overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
    })();
  </script>
  PHP,

          'src/modals/accountmanagement/reset-account-modal.css' => <<<'CSS'
  .modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); z-index: 1200 }
  .modal.hidden { display: none }
  .modal-card { background: var(--surface); width: 520px; border-radius: 8px; box-shadow: var(--shadow); overflow: hidden }
  .modal-top { display:flex; gap:12px; padding:20px; align-items:center }
  .modal-icon-bg { box-sizing: border-box; width:56px; height:56px; min-width:56px; min-height:56px; border-radius:50%; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; padding:0 }
  .modal-icon { color: var(--accent-dark); font-size:28px; line-height:1 }
  .modal-title-wrap h3 { margin:0 0 6px 0; font-size:18px }
  .modal-sub { margin:0; color:var(--muted); font-size:14px }
  .modal-actions { display:flex; gap:8px; padding:16px; justify-content:flex-end }
  .btn { padding:8px 12px; border-radius:4px; border:1px solid transparent; cursor:pointer; transition: background-color 160ms ease, color 160ms ease, transform 120ms ease, box-shadow 160ms ease }
  .btn-primary { background: var(--accent); color:#fff }
  .btn-secondary { background: var(--stroke); color: var(--ink) }
  .btn-primary:hover{ background: var(--accent-dark); box-shadow: 0 6px 18px rgba(16,24,40,0.06); transform: translateY(-1px); }
  .reset-account-modal__form .field { margin-bottom:10px }
  .reset-account-modal__form label { display:block; font-size:12px; margin-bottom:4px; color:var(--ink) }
  .reset-account-modal__form input, .reset-account-modal__form select { width:100%; padding:10px; border:1px solid var(--stroke); border-radius:6px; background:var(--surface); color:var(--ink) }
  .account-created { text-align:center; padding:20px }
  .account-created .check { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
  .account-created .check svg { width:36px; height:36px }
  .account-failed { text-align:center; padding:20px }
  .account-failed .x { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 12%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
  .account-failed .x svg { width:36px; height:36px }
  .reset-account-modal__form .field input, .reset-account-modal__form .field select { box-sizing: border-box }
  CSS,

        'src/modals/accountmanagement/change-status-modal.php' => <<<'PHP'
<?php
// Change Status modal copied/adapted from samples
if (!isset($appBaseUrl) || $appBaseUrl === null) {
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
    $appBaseUrl = rtrim((string) $appBaseUrl, '/');
}
if ($appBaseUrl !== '' && strpos($appBaseUrl, '/') !== 0) {
    $appBaseUrl = '/' . $appBaseUrl;
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/accountmanagement/change-status-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<div id="changeStatusModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="changeStatusTitle">
  <div class="modal-card" role="document">
    <div class="modal-top">
      <div class="modal-icon-bg"><span class="material-icons modal-icon">swap_horiz</span></div>
      <div class="modal-title-wrap">
        <h3 id="changeStatusTitle">Change Account Status</h3>
        <p class="modal-sub">Set the user's account to Active or Disabled.</p>
      </div>
    </div>

    <div style="padding:0 20px 18px 20px;">
      <form id="changeStatusForm" class="change-status-modal__form" autocomplete="off">
        <div class="field">
          <label for="cs-id">ID Number</label>
          <input id="cs-id" name="id_number" readonly>
        </div>
        <div class="field">
          <label for="cs-username">Username</label>
          <input id="cs-username" name="username" readonly>
        </div>
        <div class="field">
          <label for="cs-status">Status</label>
          <select id="cs-status" name="status">
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
          </select>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="cs-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary" id="cs-save">Change Status</button>
        </div>
      </form>

      <div id="cs-success" class="account-created" style="display:none">
        <div class="check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg">Status updated successfully</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="cs-ok">Okay</button></div>
      </div>
      <div id="cs-failure" class="account-failed" style="display:none">
        <div class="x" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="msg" id="cs-failure-msg">Failed to change status</div>
        <div style="margin-top:12px"><button class="btn btn-primary" id="cs-failure-ok">Okay</button></div>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    var overlay = document.getElementById('changeStatusModal');
    var openBtn = document.getElementById('am-status-btn');
    var cancelBtn = document.getElementById('cs-cancel');
    var okBtn = document.getElementById('cs-ok');
    var form = document.getElementById('changeStatusForm');
    var success = document.getElementById('cs-success');
    var failure = document.getElementById('cs-failure');
    var idField = document.getElementById('cs-id');
    var usernameField = document.getElementById('cs-username');
    var statusField = document.getElementById('cs-status');

    function open(){ if (!overlay) return; try{ overlay.inert = false; }catch(e){} overlay.classList.remove('hidden'); overlay.setAttribute('aria-hidden','false'); }
    function close(){ if (!overlay) return; try{ overlay.inert = true; }catch(e){} overlay.classList.add('hidden'); overlay.setAttribute('aria-hidden','true'); if (success) success.style.display='none'; if (failure) failure.style.display='none'; if (form) { form.style.display='block'; } }

    function populateFromSelection(){
      var sel = window.amSelectedAccount;
      if (!sel || !sel.row) return false;
      var tr = sel.row;
      var cells = tr.querySelectorAll('td');
      if (!cells || cells.length < 8) return false;
      if (idField) idField.value = (cells[1] && cells[1].textContent) ? cells[1].textContent.trim() : '';
      if (usernameField) usernameField.value = (cells[2] && cells[2].textContent) ? cells[2].textContent.trim() : '';
      var current = tr.dataset.status || (cells[7] && cells[7].textContent ? cells[7].textContent.trim().toLowerCase() : 'active');
      if (statusField) statusField.value = (current === 'disabled' ? 'disabled' : 'active');
      return true;
    }

    if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); if (!populateFromSelection()) return; open(); });
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ close(); });

    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var btn = document.getElementById('cs-save'); if (btn) btn.disabled = true;
      var fd = new FormData(form);
      function showFailure(msg){ if (btn) btn.disabled = false; if (form) form.style.display='none'; if (success) success.style.display='none'; if (failure) { failure.style.display = 'block'; var m = document.getElementById('cs-failure-msg'); if (m) m.textContent = msg || 'Failed to change status'; } }

      fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/account-change-status.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function(r){ return r.json().catch(function(){ return { ok:false, message:'Invalid server response' }; }); })
        .then(function(json){
          if (btn) btn.disabled = false;
          if (json && json.ok){
            if (form) form.style.display='none'; if (success) success.style.display='block';
            try{
              var sel = window.amSelectedAccount;
              if (sel && sel.row){
                var tr = sel.row;
                var cells = tr.querySelectorAll('td');
                if (cells && cells.length > 7){
                  cells[7].textContent = json.dateModified || new Date().toLocaleString();
                }
                tr.dataset.status = (statusField && statusField.value) ? statusField.value : 'active';
              }
            }catch(e){}
            if (window.refreshAccountManagement) try{ window.refreshAccountManagement(); }catch(e){}
          } else {
            showFailure((json && json.message) ? json.message : 'Failed to change status');
          }
        })
        .catch(function(){ showFailure('Failed to change status'); });
    });

    if (okBtn) okBtn.addEventListener('click', function(){ close(); });
    var failureOk = document.getElementById('cs-failure-ok');
    if (failureOk) failureOk.addEventListener('click', function(){ close(); });
    overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  })();
</script>
PHP,

        'src/modals/accountmanagement/change-status-modal.css' => <<<'CSS'
.modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.4); z-index: 1200 }
.modal.hidden { display: none }
.modal-card { background: var(--surface); width: 520px; border-radius: 8px; box-shadow: var(--shadow); overflow: hidden }
.modal-top { display: flex; gap: 12px; padding: 20px; align-items: center }
.modal-icon-bg { box-sizing: border-box; width: 56px; height: 56px; min-width: 56px; min-height: 56px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; padding: 0 }
.modal-icon { color: var(--accent-dark); font-size: 28px; line-height: 1 }
.modal-title-wrap h3 { margin: 0 0 6px 0; font-size: 18px }
.modal-sub { margin: 0; color: var(--muted); font-size: 14px }
.modal-actions { display: flex; gap: 8px; padding: 16px; justify-content: flex-end }
.btn { padding: 8px 12px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; transition: background-color 160ms ease, color 160ms ease, transform 120ms ease, box-shadow 160ms ease }
.btn-primary { background: var(--accent); color: #fff }
.btn-secondary { background: var(--stroke); color: var(--ink) }
.btn-primary:hover{ background: var(--accent-dark); box-shadow: 0 6px 18px rgba(16,24,40,0.06); transform: translateY(-1px); }
.change-status-modal__form .field { margin-bottom: 10px }
.change-status-modal__form label { display: block; font-size: 12px; margin-bottom: 4px; color: var(--ink) }
.change-status-modal__form input, .change-status-modal__form select { width: 100%; padding: 10px; border: 1px solid var(--stroke); border-radius: 6px; background: var(--surface); color: var(--ink) }
.account-created { text-align: center; padding: 20px }
.account-created .check { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 15%, var(--surface)); color: var(--accent-dark); margin-bottom: 12px }
.account-created .check svg { width: 36px; height: 36px }
.account-failed { text-align:center; padding:20px }
.account-failed .x { width:72px; height:72px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--accent) 12%, var(--surface)); color: var(--accent-dark); margin-bottom:12px }
.account-failed .x svg { width:36px; height:36px }
.change-status-modal__form .field input, .change-status-modal__form .field select { box-sizing: border-box }
CSS,

        'src/controllers/accountmanagement/account-change-status-controller.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $id_number = trim((string)($_POST['id_number'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));

    if ($id_number === '' || $status === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'ID Number and status are required']);
        exit;
    }

    $allowed = ['active','disabled'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid status value']);
        exit;
    }

    $pdo = userDbConnection();
    $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'my_database'))));
    $userlogsTable = "`" . $userDb . "`.`userlogs`";

    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();

    $lstmt = $pdo->prepare("UPDATE {$userlogsTable} SET `status` = :status, `dateModified` = :dt WHERE `id_number` = :id");
    $lstmt->execute([':status' => $status, ':dt' => $now, ':id' => $id_number]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Status updated', 'dateModified' => $now]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Account change status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to change status', 'debug' => $e->getMessage()]);
    exit;
}
PHP,

  'public/api/account-change-status.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/accountmanagement/account-change-status-controller.php';
PHP,

        'src/controllers/accountmanagement/account-creation-controller.php' => <<<'PHP'
    <?php
    declare(strict_types=1);

    require_once __DIR__ . '/../../config/env.php';
    require_once __DIR__ . '/../../config/db.php';

    function create_account_from_request(): array {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['ok' => false, 'message' => 'Method not allowed', 'code' => 405];
      }

      $id_number = trim((string)($_POST['id_number'] ?? ''));
      $firstname = trim((string)($_POST['firstname'] ?? ''));
      $middlename = trim((string)($_POST['middlename'] ?? ''));
      $lastname = trim((string)($_POST['lastname'] ?? ''));
      $username = trim((string)($_POST['username'] ?? ''));
      $role = trim((string)($_POST['role'] ?? 'Public'));

      if ($id_number === '' || $username === '') {
        return ['ok' => false, 'message' => 'ID Number and Username are required', 'code' => 400];
      }

      $pdo = userDbConnection();
      $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'my_database'))));
      $usersTable = "`" . $userDb . "`.`users`";
      $userlogsTable = "`" . $userDb . "`.`userlogs`";

      // Default password: first 4 letters of last name + id_number
      $part = substr($lastname, 0, 4);
      $defaultPlain = $part . $id_number;
      $passwordHash = password_hash($defaultPlain, PASSWORD_DEFAULT);

      // access_level mapping: Admin => full (-1), Public => no access (0) by default
      $access_level = ($role === 'Admin') ? -1 : 0;

      try {
        $pdo->beginTransaction();

        // Insert into users
        $ustmt = $pdo->prepare("INSERT INTO {$usersTable} (`id_number`,`username`,`firstname`,`middlename`,`lastname`,`role`,`password`,`dateCreated`) VALUES (:id,:username,:first,:middle,:last,:role,:pw,NOW())");
        $ustmt->execute([
          ':id' => $id_number,
          ':username' => $username,
          ':first' => $firstname,
          ':middle' => $middlename,
          ':last' => $lastname,
          ':role' => $role,
          ':pw' => $passwordHash
        ]);

        // Insert or update userlogs — prefer explicit SELECT then UPDATE/INSERT to avoid duplicate rows
        $sel = $pdo->prepare("SELECT `id_number` FROM {$userlogsTable} WHERE TRIM(CAST(id_number AS CHAR)) = TRIM(:id) LIMIT 1");
        $sel->execute([':id' => $id_number]);
        $exists = $sel->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
          $lstmt = $pdo->prepare("UPDATE {$userlogsTable} SET `status` = 'active', `dateModified` = NOW() WHERE TRIM(CAST(id_number AS CHAR)) = TRIM(:id)");
          $lstmt->execute([':id' => $id_number]);
        } else {
          // New account: set `dateModified` to NULL so the workflow requires password change
          $lstmt = $pdo->prepare("INSERT INTO {$userlogsTable} (`id_number`,`status`,`dateModified`) VALUES (:id,'active', NULL)");
          $lstmt->execute([':id' => $id_number]);
        }

        // (No RBAC table in this project) — skip RBAC initialization.

        $pdo->commit();

        return ['ok' => true, 'default_password' => $defaultPlain, 'username' => $username];
      } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Account creation failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Failed to create account', 'debug' => $e->getMessage()];
      }
    }

    PHP,
    'src/controllers/accountmanagement/account-reset-controller.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
  exit;
}

try {
  $id_number = trim((string)($_POST['id_number'] ?? ''));

  if ($id_number === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID Number is required']);
    exit;
  }

  $pdo = userDbConnection();
  $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'my_database'))));
  $usersTable = "`" . $userDb . "`.`users`";
  $userlogsTable = "`" . $userDb . "`.`userlogs`";

  // fetch lastname to compute default password
  $q = $pdo->prepare("SELECT `lastname` FROM {$usersTable} WHERE `id_number` = :id LIMIT 1");
  $q->execute([':id' => $id_number]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Account not found']);
    exit;
  }
  $lastname = (string)($row['lastname'] ?? '');

  $part = substr($lastname, 0, 4);
  $defaultPlain = $part . $id_number;
  $passwordHash = password_hash($defaultPlain, PASSWORD_DEFAULT);

  // use PHP timestamp so we can return the value to the client
  $now = date('Y-m-d H:i:s');

  $pdo->beginTransaction();

  // some schemas don't have dateModified on users table; only update password here
  $ustmt = $pdo->prepare("UPDATE {$usersTable} SET `password` = :pw WHERE `id_number` = :id");
  $ustmt->execute([':pw' => $passwordHash, ':id' => $id_number]);

  $lstmt = $pdo->prepare("UPDATE {$userlogsTable} SET `status` = 'reset', `dateModified` = :dt WHERE `id_number` = :id");
  $lstmt->execute([':dt' => $now, ':id' => $id_number]);

  $pdo->commit();

  echo json_encode(['ok' => true, 'message' => 'Password reset', 'dateModified' => $now]);
  exit;

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  error_log('Account reset failed: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Failed to reset password', 'debug' => $e->getMessage()]);
  exit;
}
PHP,

    'public/api/account-create.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/controllers/accountmanagement/account-creation-controller.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $res = create_account_from_request();
  echo json_encode($res);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
PHP,

  'public/api/account-reset.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/accountmanagement/account-reset-controller.php';
PHP,

  'public/api/account-edit.php' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/controllers/accountmanagement/account-edit-controller.php';

header('Content-Type: application/json; charset=utf-8');

// Delegate to controller function
$res = edit_account_from_request();
if (isset($res['code'])) {
    http_response_code((int)$res['code']);
}
echo json_encode($res);
exit;
PHP,

        'public/api/account-check.php' => <<<'PHP'
<?php
require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['ok'=>true,'exists'=>false]); exit; }

$pdo = userDbConnection();
$stmt = $pdo->prepare('SELECT no FROM users WHERE id_number = ? LIMIT 1');
$stmt->execute([$id]);
$exists = (bool)$stmt->fetch();

echo json_encode(['ok' => true, 'exists' => $exists]);
PHP,

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

    /* Make the hover hit-area slightly wider to avoid collapsing when the
       cursor moves over labels that render outside the collapsed width. */
    .sidebar { overflow: visible; }
    .sidebar::after {
      content: '';
      position: absolute;
      right: -80px;
      top: 0;
      bottom: 0;
      width: 80px;
      pointer-events: auto;
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

    // Keep critical account-management templates mirrored from /test (source of truth)
    // when this repository includes it.
    $testRoot = __DIR__ . DIRECTORY_SEPARATOR . 'test';
    $syncFromTest = [
      'src/modals/accountmanagement/add-account-modal.css',
      'src/modals/accountmanagement/add-account-modal.php',
      'src/modals/accountmanagement/edit-account-modal.php',
      'src/pages/maintenance/accountmanagement/accountmanagement.php',
    ];
    if (is_dir($testRoot)) {
      foreach ($syncFromTest as $relativePath) {
        $sourcePath = $testRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($sourcePath)) {
          continue;
        }
        $content = @file_get_contents($sourcePath);
        if ($content === false) {
          continue;
        }
        $templates[$relativePath] = $content;
      }
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
