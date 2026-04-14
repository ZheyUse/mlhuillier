<?php
/**
 * package/pbac/pbac-application.php
 *
 * Applies PBAC scaffold files into an existing generated project.
 *
 * Usage:
 *   php package/pbac/pbac-application.php <project_name> [--dry-run]
 */

declare(strict_types=1);

function pbac_cli_out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function pbac_cli_err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function pbac_sanitize_name(string $value): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', trim($value)) ?? '';
}

function pbac_join(string $base, string $relative): string
{
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
}

function pbac_has_scaffold_root(string $path): bool
{
    if (!is_dir($path)) {
        return false;
    }
    return is_file(pbac_join($path, 'src/templates/sidebar.php'))
        && is_file(pbac_join($path, 'src/config/login-handler.php'));
}

function pbac_resolve_project_root(string $projectArg): ?string
{
    $cwd = getcwd() ?: '.';
    $candidates = [];

    if ($projectArg !== '') {
        if (preg_match('/^[A-Za-z]:\\\\|^\\\\/', $projectArg) === 1) {
            $candidates[] = $projectArg;
        }
        $candidates[] = pbac_join($cwd, $projectArg);
        $candidates[] = pbac_join('C:\\xampp\\htdocs', $projectArg);
    }

    $candidates[] = $cwd;

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real === false) {
            continue;
        }
        if (pbac_has_scaffold_root($real)) {
            return $real;
        }
    }

    return null;
}

function pbac_ensure_dir(string $dir, bool $dryRun, array &$report): bool
{
    if (is_dir($dir)) {
        $report[] = 'DIR EXISTS  ' . $dir;
        return true;
    }
    if ($dryRun) {
        $report[] = 'DIR CREATE  ' . $dir;
        return true;
    }
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        $report[] = 'DIR FAILED  ' . $dir;
        return false;
    }
    $report[] = 'DIR CREATED ' . $dir;
    return true;
}

function pbac_write_file(string $path, string $content, bool $dryRun, array &$report): bool
{
    $status = 'WRITE';
    if (is_file($path)) {
        $existing = (string) file_get_contents($path);
        if ($existing === $content) {
            $report[] = 'FILE OK     ' . $path;
            return true;
        }
        $status = 'UPDATE';
    }

    if ($dryRun) {
        $report[] = 'FILE ' . $status . ' ' . $path;
        return true;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        $report[] = 'FILE FAILED ' . $path;
        return false;
    }

    $ok = file_put_contents($path, rtrim($content, "\r\n") . PHP_EOL) !== false;
    $report[] = $ok
        ? ('FILE ' . str_pad($status, 6, ' ', STR_PAD_RIGHT) . ' ' . $path)
        : ('FILE FAILED ' . $path);

    return $ok;
}

function pbac_patch_login_handler(string $path, string $pbacTable, bool $dryRun, array &$report): bool
{
    if (!is_file($path)) {
        $report[] = 'PATCH SKIP  login-handler not found';
        return true;
    }

    $content = (string) file_get_contents($path);
    if ($content === '') {
        $report[] = 'PATCH FAIL  login-handler empty';
        return false;
    }

    $changed = false;

    $includeLine = "require_once __DIR__ . '/pbac-session.php';";
    if (strpos($content, $includeLine) === false) {
        $anchor = "require_once __DIR__ . '/../controllers/password-controller/changepass-controller.php';";
        if (strpos($content, $anchor) !== false) {
            $content = str_replace($anchor, $anchor . PHP_EOL . $includeLine, $content);
            $changed = true;
        }
    }

    $callSnippet = "if (function_exists('loadPbacSession')) {\n    loadPbacSession((array) \$user, (string) \$username, '" . $pbacTable . "');\n  }";
    if (strpos($content, "loadPbacSession((array) \$user") === false) {
        $anchor = "unset(\$_SESSION['login_error']);";
        if (strpos($content, $anchor) !== false) {
            $content = str_replace($anchor, $callSnippet . PHP_EOL . PHP_EOL . "  " . $anchor, $content);
            $changed = true;
        }
    }

    if (!$changed) {
        $report[] = 'PATCH OK    login-handler already patched';
        return true;
    }

    if ($dryRun) {
        $report[] = 'PATCH APPLY login-handler.php';
        return true;
    }

    $ok = file_put_contents($path, $content) !== false;
    $report[] = $ok ? 'PATCH DONE  login-handler.php' : 'PATCH FAIL  login-handler.php';
    return $ok;
}

function pbac_auth_template(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

$__auth_session_key = 'auth_user';

if (!function_exists('auth_session_key')) {
    function auth_session_key(): string
    {
        global $__auth_session_key;
        return $__auth_session_key;
    }
}

if (!function_exists('auth_start')) {
    function auth_start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('auth_set_user')) {
    function auth_set_user(array $user): void
    {
        auth_start();
        $_SESSION[auth_session_key()] = $user;
    }
}

if (!function_exists('auth_get_user')) {
    function auth_get_user(): ?array
    {
        auth_start();
        $data = $_SESSION[auth_session_key()] ?? null;
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('auth_is_authenticated')) {
    function auth_is_authenticated(): bool
    {
        return auth_get_user() !== null;
    }
}

if (!function_exists('auth_has_role')) {
    function auth_has_role(string $role): bool
    {
        $user = auth_get_user();
        if (!$user) {
            return false;
        }
        $current = strtolower(trim((string) ($user['role'] ?? '')));
        return $current !== '' && $current === strtolower(trim($role));
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        auth_start();
        unset($_SESSION[auth_session_key()]);
        unset($_SESSION['user_permissions']);
        unset($_SESSION['user_access_level']);
        session_regenerate_id(true);
    }
}

if (!function_exists('get_current_user_permissions')) {
    function get_current_user_permissions(): array
    {
        auth_start();
        $raw = $_SESSION['user_permissions'] ?? [];
        $out = [];
        foreach ((array) $raw as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $key): bool
    {
        auth_start();
        $key = strtolower(trim($key));
        if ($key === '') {
            return false;
        }

        if (auth_has_role('admin')) {
            return true;
        }

        $level = isset($_SESSION['user_access_level']) ? (int) $_SESSION['user_access_level'] : 0;
        if ($level === -1) {
            return true;
        }

        foreach (get_current_user_permissions() as $perm) {
            if (strtolower(trim((string) $perm)) === $key) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('has_any_permission')) {
    function has_any_permission(array $keys): bool
    {
        foreach ($keys as $key) {
            if (has_permission((string) $key)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('has_menu_access')) {
    function has_menu_access(string $menuKey): bool
    {
        auth_start();

        if (auth_has_role('admin')) {
            return true;
        }

        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return false;
        }

        $level = isset($_SESSION['user_access_level']) ? (int) $_SESSION['user_access_level'] : 0;
        if ($level === -1) {
            return true;
        }

        $userPerms = get_current_user_permissions();
        if (empty($userPerms)) {
            return false;
        }

        $mapPath = dirname(__DIR__) . '/assets/js/accesslevel-map.json';
        if (is_file($mapPath)) {
            $raw = file_get_contents($mapPath);
            $map = json_decode((string) $raw, true);
            if (is_array($map)) {
                $catalog = isset($map['permission_catalog']) && is_array($map['permission_catalog']) ? $map['permission_catalog'] : [];
                foreach ($catalog as $menu) {
                    $label = trim((string) ($menu['label'] ?? ''));
                    $id = trim((string) ($menu['id'] ?? ($menu['key'] ?? '')));
                    if (strcasecmp($label, $menuKey) !== 0 && strcasecmp($id, $menuKey) !== 0) {
                        continue;
                    }
                    $children = isset($menu['children']) && is_array($menu['children']) ? $menu['children'] : [];
                    foreach ($children as $child) {
                        $childId = trim((string) ($child['id'] ?? ($child['key'] ?? '')));
                        if ($childId !== '' && in_array($childId, $userPerms, true)) {
                            return true;
                        }
                    }
                    return false;
                }
            }
        }

        // Fallback heuristic when map file is not ready yet.
        $prefix = strtolower($menuKey) . ' ';
        foreach ($userPerms as $perm) {
            $candidate = strtolower(trim((string) $perm));
            if ($candidate === strtolower($menuKey) || str_starts_with($candidate, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

return [
    'session_key' => $__auth_session_key,
];
PHP;
}

function pbac_sidebar_template(): string
{
    return <<<'PHP'
<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/usercontroller.php';
require_once __DIR__ . '/../config/auth.php';

auth_start();

$userController = new UserController();
$user = $userController->profile();
$username = htmlspecialchars(strtoupper((string) ($user['username'] ?? 'Guest')), ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES, 'UTF-8');
$appBaseUrl = isset($appBaseUrl) ? rtrim((string) $appBaseUrl, '/') : '';
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
        <?php if (has_menu_access('Maintenance')): ?>
        <?php if (has_any_permission(['Maintenance Account Management', 'Maintenance Access Level'])): ?>
        <li class="sidebar__nav-item has-submenu">
          <button type="button" class="sidebar__nav-link" aria-expanded="false">
            <span class="material-icons sidebar__nav-icon" aria-hidden="true">build</span>
            <span class="sidebar__nav-label">Maintenance</span>
            <span class="material-icons sidebar__nav-chev" aria-hidden="true">expand_more</span>
          </button>
          <ul class="sidebar__submenu">
            <?php if (has_permission('Maintenance Account Management')): ?>
            <li class="sidebar__submenu-item"><a href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/pages/maintenance/accountmanagement/accountmanagement.php', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar__submenu-link"><span class="sidebar__submenu-label">Account Management</span></a></li>
            <?php endif; ?>
            <?php if (has_permission('Maintenance Access Level')): ?>
            <li class="sidebar__submenu-item"><a href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/pages/maintenance/accesslevel/accesslevel.php', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar__submenu-link"><span class="sidebar__submenu-label">Access Level</span></a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>
        <?php endif; ?>
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
      btn.addEventListener('click', function(){
        var expanded = this.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          this.setAttribute('aria-expanded', 'false');
        } else {
          closeAll(this);
          this.setAttribute('aria-expanded', 'true');
        }
      });
      btn.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    });

    if (sidebar) {
      var hideTimeout = null;
      sidebar.addEventListener('mouseleave', function(){
        hideTimeout = setTimeout(function(){ closeAll(); }, 180);
      });
      sidebar.addEventListener('mouseenter', function(){
        if (hideTimeout) {
          clearTimeout(hideTimeout);
          hideTimeout = null;
        }
      });
    }
  })();
</script>
PHP;
}

function pbac_accesslevel_page_template(): string
{
    return <<<'PHP'
<?php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../controllers/usercontroller.php';
require_once __DIR__ . '/../../../templates/header_ui.php';
require_once __DIR__ . '/../../../config/auth.php';

requireAuth();
auth_start();

$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
$appBaseUrl = rtrim((string) $appBaseUrl, '/');

$canView = has_permission('Maintenance Access Level') || auth_has_role('admin');

$isEntry = (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__));
if ($isEntry) {
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Level</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($appBaseUrl . '/src/assets/images/logo2.png', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/public/index.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/assets/css/color.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/templates/header_ui.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/templates/sidebar.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/modals/logout-modal/logout-modal.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/src/pages/maintenance/accesslevel/accesslevel.css', ENT_QUOTES, 'UTF-8'); ?>">
  </head>
  <body>
  <?php
}
?>
<div class="app-layout">
  <?php require __DIR__ . '/../../../templates/sidebar.php'; ?>

  <main class="main-content">
    <section class="access-level-page" id="access-level-root">
      <?php bp_section_header_html('security', 'Access Level', 'Manage access level and permissions'); ?>

      <?php if (!$canView): ?>
        <div class="access-level-denied">You do not have permission to access this page.</div>
      <?php else: ?>
        <div class="al-grid">
          <div class="al-card">
            <h3>Accounts</h3>
            <div class="al-table-wrap">
              <table class="al-table">
                <thead>
                  <tr>
                    <th>ID Number</th>
                    <th>Username</th>
                    <th>Access Level</th>
                  </tr>
                </thead>
                <tbody id="al-tbody">
                  <tr><td colspan="3">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="al-card">
            <h3>Update Access</h3>
            <form id="al-form" autocomplete="off">
              <label>ID Number</label>
              <input id="al-id-number" name="id_number" readonly>

              <label>Access Level</label>
              <input id="al-access-level" name="access_level" type="number" value="0">

              <label>Permissions (comma-separated)</label>
              <textarea id="al-permissions" name="permissions" rows="8" placeholder="Maintenance Account Management, Maintenance Access Level"></textarea>

              <button type="submit" class="btn btn-primary">Save</button>
              <div id="al-message" class="al-message" aria-live="polite"></div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<?php require __DIR__ . '/../../../modals/logout-modal/logout-modal.php'; ?>
<?php
if ($isEntry) {
  echo "</body>\n</html>\n";
}
?>

<script>
(function(){
  var root = document.getElementById('access-level-root');
  if (!root) return;
  if (root.dataset.inited === '1') return;
  root.dataset.inited = '1';

  var tbody = document.getElementById('al-tbody');
  var form = document.getElementById('al-form');
  var idInput = document.getElementById('al-id-number');
  var levelInput = document.getElementById('al-access-level');
  var permsInput = document.getElementById('al-permissions');
  var msg = document.getElementById('al-message');
  var rows = [];

  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function parsePerms(raw){
    if (Array.isArray(raw)) return raw;
    if (raw == null) return [];
    var s = String(raw).trim();
    if (!s) return [];
    if (s.charAt(0) === '[' || s.charAt(0) === '{') {
      try {
        var j = JSON.parse(s);
        if (Array.isArray(j)) return j.map(function(x){ return String(x).trim(); }).filter(Boolean);
      } catch (e) {}
    }
    return s.split(/[,;|]/).map(function(x){ return String(x).trim(); }).filter(Boolean);
  }

  function render(list){
    if (!tbody) return;
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="3">No accounts found.</td></tr>';
      return;
    }
    var html = '';
    list.forEach(function(r){
      html += '<tr data-id="' + esc(r.id_number || '') + '">' +
              '<td>' + esc(r.id_number || '') + '</td>' +
              '<td>' + esc(r.username || '') + '</td>' +
              '<td>' + esc(r.access_level == null ? 0 : r.access_level) + '</td>' +
              '</tr>';
    });
    tbody.innerHTML = html;
  }

  function pickRow(id){
    var row = rows.find(function(r){ return String(r.id_number || '') === String(id); });
    if (!row) return;
    if (idInput) idInput.value = row.id_number || '';
    if (levelInput) levelInput.value = row.access_level == null ? 0 : row.access_level;
    if (permsInput) permsInput.value = parsePerms(row.active_permissions).join(', ');
  }

  function load(){
    fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/accesslevel-fetch.php', { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (json && json.ok && Array.isArray(json.rows)) {
          rows = json.rows;
          render(rows);
          if (rows.length) pickRow(rows[0].id_number || '');
          return;
        }
        render([]);
      })
      .catch(function(){ render([]); });
  }

  if (tbody) {
    tbody.addEventListener('click', function(e){
      var tr = e.target.closest && e.target.closest('tr[data-id]');
      if (!tr) return;
      pickRow(tr.getAttribute('data-id'));
    });
  }

  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      if (!idInput || !idInput.value) {
        if (msg) msg.textContent = 'Select an account first.';
        return;
      }
      var payload = {
        id_number: idInput.value,
        access_level: parseInt(levelInput && levelInput.value ? levelInput.value : '0', 10) || 0,
        permissions: parsePerms(permsInput ? permsInput.value : '')
      };

      fetch('<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/public/api/accesslevel-update.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (json && json.success) {
          if (msg) msg.textContent = 'Access updated.';
          load();
          return;
        }
        if (msg) msg.textContent = (json && (json.message || json.error)) ? String(json.message || json.error) : 'Update failed.';
      })
      .catch(function(){
        if (msg) msg.textContent = 'Update failed.';
      });
    });
  }

  load();
})();
</script>
PHP;
}

function pbac_accesslevel_css_template(): string
{
    return <<<'CSS'
.access-level-page {
  padding: 12px;
}

.access-level-denied {
  background: #ffeaea;
  color: #8b1f1f;
  border: 1px solid #f3c2c2;
  border-radius: 8px;
  padding: 12px;
}

.al-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 16px;
}

.al-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.al-card h3 {
  margin: 0 0 10px;
}

.al-table-wrap {
  overflow: auto;
  max-height: 520px;
}

.al-table {
  width: 100%;
  border-collapse: collapse;
}

.al-table th,
.al-table td {
  border-bottom: 1px solid #eceff3;
  padding: 8px;
  text-align: left;
  font-size: 13px;
}

.al-table tbody tr {
  cursor: pointer;
}

.al-table tbody tr:hover {
  background: #f8fafc;
}

#al-form {
  display: grid;
  gap: 8px;
}

#al-form label {
  font-weight: 600;
  font-size: 13px;
}

#al-form input,
#al-form textarea {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
  box-sizing: border-box;
}

.al-message {
  min-height: 20px;
  font-size: 13px;
  color: #0f5132;
}

@media (max-width: 980px) {
  .al-grid {
    grid-template-columns: 1fr;
  }
}
CSS;
}

function pbac_generate_access_map_template(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sidebarPath = $root . '/src/templates/sidebar.php';
$outputPath = $root . '/src/assets/js/accesslevel-map.json';

if (!is_file($sidebarPath)) {
    fwrite(STDERR, "Sidebar not found: {$sidebarPath}\n");
    exit(1);
}

$content = (string) file_get_contents($sidebarPath);
if ($content === '') {
    fwrite(STDERR, "Sidebar is empty: {$sidebarPath}\n");
    exit(1);
}

function pbac_norm(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return (string) $value;
}

function pbac_perm(string $menu, string $sub): string
{
    return pbac_norm($menu . ' ' . $sub);
}

$lines = preg_split('/\R/', $content) ?: [];
$menus = [];
$inMenu = false;
$depth = 0;
$current = null;

foreach ($lines as $line) {
    $lineStr = (string) $line;

    if (!$inMenu && preg_match('/<li\s+class="sidebar__nav-item\s+has-submenu"/', $lineStr)) {
        $inMenu = true;
        $depth = 0;
        $current = [
            'label' => '',
            'icon' => 'folder',
            'subs' => [],
        ];
    }

    if ($inMenu && is_array($current)) {
        if ($current['label'] === '' && preg_match('/<span\s+class="sidebar__nav-label">([^<]+)<\/span>/', $lineStr, $m)) {
            $current['label'] = pbac_norm((string) $m[1]);
        }

        if (preg_match('/<span\s+class="sidebar__submenu-label">([^<]+)<\/span>/', $lineStr, $sm)) {
            $label = pbac_norm((string) $sm[1]);
            $current['subs'][] = [
                'label' => $label,
                'key' => '',
            ];
        }

        $depth += substr_count($lineStr, '<li');
        $depth -= substr_count($lineStr, '</li>');

        if ($depth <= 0) {
            if (!empty($current['label']) && !empty($current['subs'])) {
                $menus[] = $current;
            }
            $inMenu = false;
            $depth = 0;
            $current = null;
        }
    }
}

$catalog = [];
foreach ($menus as $menu) {
    $children = [];
    foreach ($menu['subs'] as $sub) {
        $children[] = [
            'id' => pbac_perm($menu['label'], $sub['label']),
            'key' => pbac_perm($menu['label'], $sub['label']),
            'label' => $sub['label'],
        ];
    }

    $catalog[] = [
        'id' => $menu['label'],
        'key' => $menu['label'],
        'label' => $menu['label'],
        'icon' => 'folder',
        'children' => $children,
    ];
}

if (empty($catalog)) {
    fwrite(STDERR, "No menu/submenu structure detected from sidebar.\n");
    exit(1);
}

$accessLevels = [];
$count = count($catalog);
for ($mask = 1; $mask < (1 << $count); $mask++) {
    $perms = [];
    for ($i = 0; $i < $count; $i++) {
        if (($mask & (1 << $i)) !== 0) {
            foreach ($catalog[$i]['children'] as $child) {
                $perms[] = (string) $child['id'];
            }
        }
    }
    $perms = array_values(array_unique($perms));
    sort($perms);
    $accessLevels[] = [
        'access_level' => $mask,
        'permissions' => $perms,
    ];
}

$all = [];
foreach ($catalog as $menu) {
    foreach ($menu['children'] as $child) {
        $all[] = (string) $child['id'];
    }
}
$all = array_values(array_unique($all));
sort($all);
$accessLevels[] = [
    'access_level' => -1,
    'permissions' => $all,
];

$map = [
    'version' => 1,
    'generated_at' => date('c'),
    'source_file' => 'src/templates/sidebar.php',
    'permission_catalog' => $catalog,
    'access_levels' => $accessLevels,
];

$outDir = dirname($outputPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "Failed to encode map JSON.\n");
    exit(1);
}

file_put_contents($outputPath, $json . PHP_EOL);
echo "Wrote access map: src/assets/js/accesslevel-map.json\n";
PHP;
}

function pbac_session_template(string $pbacTable): string
{
    return str_replace('{{PBAC_TABLE}}', $pbacTable, <<<'PHP'
<?php

declare(strict_types=1);

if (!function_exists('pbac_normalize_permissions')) {
    function pbac_normalize_permissions($raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $item) {
                $value = trim((string) $item);
                if ($value !== '') {
                    $out[] = $value;
                }
            }
            return array_values(array_unique($out));
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        if ($text[0] === '[' || $text[0] === '{') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return pbac_normalize_permissions($decoded);
            }
        }

        $parts = preg_split('/[;,|]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return pbac_normalize_permissions($parts ?: []);
    }
}

if (!function_exists('pbac_permissions_for_level')) {
    function pbac_permissions_for_level(int $level): array
    {
        $mapPath = dirname(__DIR__) . '/assets/js/accesslevel-map.json';
        if (!is_file($mapPath)) {
            return [];
        }

        $raw = file_get_contents($mapPath);
        $map = json_decode((string) $raw, true);
        if (!is_array($map) || !isset($map['access_levels']) || !is_array($map['access_levels'])) {
            return [];
        }

        foreach ($map['access_levels'] as $entry) {
            if ((int) ($entry['access_level'] ?? 0) !== $level) {
                continue;
            }
            return pbac_normalize_permissions($entry['permissions'] ?? []);
        }

        return [];
    }
}

if (!function_exists('pbac_resolve_id_number')) {
    function pbac_resolve_id_number(PDO $pdo, string $userDb, array $user, string $usernameHint): ?string
    {
        $id = trim((string) ($user['id_number'] ?? ''));
        if ($id !== '') {
            return $id;
        }

        $table = "`" . $userDb . "`.`users`";
        $username = trim((string) ($user['username'] ?? $usernameHint));
        if ($username === '') {
            return null;
        }

        $stmt = $pdo->prepare("SELECT id_number FROM {$table} WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $resolved = trim((string) ($row['id_number'] ?? ''));
        return $resolved !== '' ? $resolved : null;
    }
}

if (!function_exists('loadPbacSession')) {
    function loadPbacSession(array $user, string $username, string $pbacTableName = '{{PBAC_TABLE}}'): void
    {
        try {
            $pdo = userDbConnection();
            $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'userdb'))));
            $idNumber = pbac_resolve_id_number($pdo, $userDb, $user, $username);

            $_SESSION['user_access_level'] = 0;
            $_SESSION['user_permissions'] = [];

            if ($idNumber === null) {
                return;
            }

            $table = "`" . $userDb . "`.`" . preg_replace('/[^A-Za-z0-9_]/', '', $pbacTableName) . "`";
            $stmt = $pdo->prepare("SELECT access_level, permissions FROM {$table} WHERE id_number = :id LIMIT 1");
            $stmt->execute([':id' => $idNumber]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $level = isset($row['access_level']) ? (int) $row['access_level'] : 0;
            $perms = pbac_normalize_permissions($row['permissions'] ?? []);

            if (empty($perms) && $level !== 0) {
                $perms = pbac_permissions_for_level($level);
            }

            $_SESSION['user_access_level'] = $level;
            $_SESSION['user_permissions'] = $perms;
        } catch (Throwable $e) {
            $_SESSION['user_access_level'] = 0;
            $_SESSION['user_permissions'] = [];
        }
    }
}
PHP
);
}

function pbac_accesslevel_fetch_controller_template(string $pbacTable): string
{
    return str_replace('{{PBAC_TABLE}}', $pbacTable, <<<'PHP'
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/config/auth.php';

auth_start();
header('Content-Type: application/json; charset=utf-8');

if (!auth_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthenticated']);
    exit;
}

if (!auth_has_role('admin') && !has_permission('Maintenance Access Level')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $pdo = userDbConnection();
    $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'userdb'))));

    $usersTable = "`" . $userDb . "`.`users`";
    $logsTable = "`" . $userDb . "`.`userlogs`";
    $pbacTable = "`" . $userDb . "`.`{{PBAC_TABLE}}`";

    $sql = "SELECT u.id_number, u.username, u.firstname, u.middlename, u.lastname,
                   p.access_level, p.permissions AS active_permissions,
                   l.status, l.last_online
            FROM {$usersTable} u
            LEFT JOIN {$logsTable} l ON l.id_number = u.id_number
            LEFT JOIN {$pbacTable} p ON p.id_number = u.id_number
            ORDER BY u.no ASC
            LIMIT 1000";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'rows' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to load access levels']);
    exit;
}
PHP
);
}

function pbac_accesslevel_update_controller_template(string $pbacTable): string
{
    return str_replace('{{PBAC_TABLE}}', $pbacTable, <<<'PHP'
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

auth_start();
header('Content-Type: application/json; charset=utf-8');

if (!auth_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthenticated']);
    exit;
}

if (!auth_has_role('admin') && !has_permission('Maintenance Access Level')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== false && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (empty($data)) {
    $data = $_POST;
}

$idNumber = trim((string) ($data['id_number'] ?? ''));
$accessLevel = isset($data['access_level']) ? (int) $data['access_level'] : 0;
$permissions = [];

if (isset($data['permissions']) && is_array($data['permissions'])) {
    $permissions = $data['permissions'];
} elseif (isset($data['permissions'])) {
    $permissions = preg_split('/[;,|]+/', (string) $data['permissions'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

$permissions = array_values(array_unique(array_filter(array_map(static function ($item): string {
    return trim((string) $item);
}, $permissions), static function (string $value): bool {
    return $value !== '';
})));

if ($idNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_id_number']);
    exit;
}

try {
    $pdo = userDbConnection();
    $userDb = preg_replace('/[^A-Za-z0-9_]/', '', env('USERDB_NAME', env('USERDB_DATABASE', env('DB_DATABASE', 'userdb'))));
    $pbacTable = "`" . $userDb . "`.`{{PBAC_TABLE}}`";

    $jsonPerms = json_encode($permissions, JSON_UNESCAPED_UNICODE);

    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE {$pbacTable} SET access_level = :al, permissions = :perms WHERE id_number = :id");
    $update->execute([':al' => $accessLevel, ':perms' => $jsonPerms, ':id' => $idNumber]);

    if ($update->rowCount() === 0) {
        $insert = $pdo->prepare("INSERT INTO {$pbacTable} (id_number, access_level, permissions) VALUES (:id, :al, :perms)");
        $insert->execute([':id' => $idNumber, ':al' => $accessLevel, ':perms' => $jsonPerms]);
    }

    $pdo->commit();

    $current = auth_get_user();
    if ($current && isset($current['id_number']) && (string) $current['id_number'] === $idNumber) {
        $_SESSION['user_access_level'] = $accessLevel;
        $_SESSION['user_permissions'] = $permissions;
    }

    echo json_encode([
        'success' => true,
        'id_number' => $idNumber,
        'access_level' => $accessLevel,
        'permissions' => $permissions,
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error', 'message' => $e->getMessage()]);
    exit;
}
PHP
);
}

function pbac_accesslevel_fetch_api_template(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/accesslevel/accesslevel-fetch-controller.php';
PHP;
}

function pbac_accesslevel_update_api_template(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/accesslevel/accesslevel-update-controller.php';
PHP;
}

function pbac_readme_template(string $projectName, string $pbacTable): string
{
        return str_replace(
                ['{{PROJECT_NAME}}', '{{PBAC_TABLE}}'],
                [$projectName, $pbacTable],
                <<<'MD'
# PBAC Guide

Project: {{PROJECT_NAME}}
PBAC Table: {{PBAC_TABLE}}

## What Is PBAC?

PBAC means Permission Based Access Control.

- Access Level = parent menu visibility
- Permissions = submenu or action visibility

This scaffold gives your project a menu to permission flow so users only see and access pages they are allowed to use.

## What This Scaffold Adds

- `src/config/auth.php`
    - `has_permission()`
    - `has_any_permission()`
    - `has_menu_access()`
- `src/config/pbac-session.php`
    - Loads PBAC data from DB into session after login
- `src/templates/sidebar.php`
    - Menu and submenu visibility checks
- `src/pages/maintenance/accesslevel/accesslevel.php`
- `src/pages/maintenance/accesslevel/accesslevel.css`
- `src/controllers/accesslevel/accesslevel-fetch-controller.php`
- `src/controllers/accesslevel/accesslevel-update-controller.php`
- `public/api/accesslevel-fetch.php`
- `public/api/accesslevel-update.php`
- `tools/generate_access_map.php`
- `PBAC-README.md` (this guide)

## Core Behavior

1. User logs in.
2. PBAC session loader reads `{{PBAC_TABLE}}` for that user.
3. Session receives:
     - `user_access_level`
     - `user_permissions`
4. Sidebar checks menu and submenu permissions before rendering links.
5. Access Level page can update user access level and permissions.

## Generate or Refresh Access Map

After adding or changing sidebar menu/submenu items, regenerate access map.

Option A (CLI command):

```bat
ml gen
```

Option B (manual script):

```bat
php tools/generate_access_map.php
```

Generated file:

- `src/assets/js/accesslevel-map.json`

## How To Add A New Menu and Submenu

1. Edit `src/templates/sidebar.php`.
2. Add parent menu guard with `has_menu_access('Menu Name')`.
3. Add submenu guards with `has_permission('MenuName SubmenuName')`.
4. Save file.
5. Run `ml gen`.
6. Confirm `src/assets/js/accesslevel-map.json` is updated.

Example pattern:

```php
<?php if (has_menu_access('Maintenance')): ?>
<?php if (has_any_permission(['Maintenance Access Level'])): ?>
<li class="sidebar__nav-item has-submenu">
    <button type="button" class="sidebar__nav-link" aria-expanded="false">
        <span class="sidebar__nav-label">Maintenance</span>
    </button>
    <ul class="sidebar__submenu">
        <?php if (has_permission('Maintenance Access Level')): ?>
            <li class="sidebar__submenu-item">
                <a href="/src/pages/maintenance/accesslevel/accesslevel.php" class="sidebar__submenu-link">Access Level</a>
            </li>
        <?php endif; ?>
    </ul>
</li>
<?php endif; ?>
<?php endif; ?>
```

## Notes

- Keep permission keys consistent between sidebar and saved permissions.
- Use Access Level page to assign permissions to users.
- Regenerate access map whenever menu structure changes.
- If map generation is unavailable, convert or reconvert project to PBAC:

```bat
ml create --pbac <project_name>
```
MD
        );
}

function applyPbacScaffold(string $projectArg, bool $dryRun = false): array
{
    $report = [];

    $projectName = pbac_sanitize_name($projectArg);
    if ($projectName === '') {
        $projectName = pbac_sanitize_name(basename(getcwd() ?: ''));
    }

    if ($projectName === '') {
        return [
            'ok' => false,
            'message' => 'Unable to determine project name.',
            'report' => $report,
        ];
    }

    $projectRoot = pbac_resolve_project_root($projectArg !== '' ? $projectArg : $projectName);
    if ($projectRoot === null) {
        return [
            'ok' => false,
            'message' => 'Could not find a generated project root. Run this inside the project directory or pass the project path/name.',
            'report' => $report,
        ];
    }

    $pbacTable = $projectName . '_pbac';

    $dirs = [
        'src/pages/maintenance/accesslevel',
        'src/controllers/accesslevel',
        'tools',
        'public/api',
    ];

    foreach ($dirs as $dir) {
        if (!pbac_ensure_dir(pbac_join($projectRoot, $dir), $dryRun, $report)) {
            return [
                'ok' => false,
                'message' => 'Failed to create required directories.',
                'report' => $report,
            ];
        }
    }

    $files = [
        'PBAC-README.md' => pbac_readme_template($projectName, $pbacTable),
        'src/config/auth.php' => pbac_auth_template(),
        'src/config/pbac-session.php' => pbac_session_template($pbacTable),
        'src/templates/sidebar.php' => pbac_sidebar_template(),
        'src/pages/maintenance/accesslevel/accesslevel.php' => pbac_accesslevel_page_template(),
        'src/pages/maintenance/accesslevel/accesslevel.css' => pbac_accesslevel_css_template(),
        'src/controllers/accesslevel/accesslevel-fetch-controller.php' => pbac_accesslevel_fetch_controller_template($pbacTable),
        'src/controllers/accesslevel/accesslevel-update-controller.php' => pbac_accesslevel_update_controller_template($pbacTable),
        'public/api/accesslevel-fetch.php' => pbac_accesslevel_fetch_api_template(),
        'public/api/accesslevel-update.php' => pbac_accesslevel_update_api_template(),
        'tools/generate_access_map.php' => pbac_generate_access_map_template(),
    ];

    foreach ($files as $relative => $content) {
        if (!pbac_write_file(pbac_join($projectRoot, $relative), $content, $dryRun, $report)) {
            return [
                'ok' => false,
                'message' => 'Failed to write scaffold files.',
                'report' => $report,
            ];
        }
    }

    $loginPath = pbac_join($projectRoot, 'src/config/login-handler.php');
    if (!pbac_patch_login_handler($loginPath, $pbacTable, $dryRun, $report)) {
        return [
            'ok' => false,
            'message' => 'Failed to patch login-handler for PBAC session loading.',
            'report' => $report,
        ];
    }

    return [
        'ok' => true,
        'message' => 'PBAC scaffold applied for project ' . $projectName,
        'project_root' => $projectRoot,
        'pbac_table' => $pbacTable,
        'report' => $report,
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $argv = $_SERVER['argv'] ?? [];
    $args = array_slice($argv, 1);

    $dryRun = false;
    $projectArg = '';

    foreach ($args as $arg) {
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        if ($projectArg === '') {
            $projectArg = $arg;
        }
    }

    $result = applyPbacScaffold($projectArg, $dryRun);

    foreach ($result['report'] ?? [] as $line) {
        pbac_cli_out($line);
    }

    if (!($result['ok'] ?? false)) {
        pbac_cli_err('PBAC scaffold failed: ' . ($result['message'] ?? 'Unknown error'));
        exit(2);
    }

    pbac_cli_out('PBAC scaffold done.');
    pbac_cli_out('Project root: ' . ($result['project_root'] ?? ''));
    pbac_cli_out('PBAC table: ' . ($result['pbac_table'] ?? ''));
    pbac_cli_out('Tip: run "ml gen" or "php tools/generate_access_map.php" inside the project.');
    exit(0);
}
