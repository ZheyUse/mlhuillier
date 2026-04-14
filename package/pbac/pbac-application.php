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
<?php /* Access Level Management component (sample-parity implementation) */ ?>
<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
$appBaseUrl = rtrim((string) $appBaseUrl, '/');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/pages/maintenance/accesslevel/accesslevel.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<?php
require_once __DIR__ . '/../../../config/auth.php';
auth_start();
if (!function_exists('has_permission') || !has_permission('Maintenance Access Level')) {
    require_once __DIR__ . '/../no-access/no-access-level-display.php';
    return;
}

require __DIR__ . '/../../../templates/header_ui.php';
bp_section_header_html('security', 'Access Level Management', 'Manage user permissions and access levels');
?>
<?php require __DIR__ . '/../../../modals/accesslevel-modal/accesslevel-modal.php'; ?>

<section class="access-level-management" id="accesslevel-root">
    <div class="alm-controls">
        <div class="alm-left">
            <div class="alm-search">
                <label for="al-search-input">Search</label>
                <input id="al-search-input" placeholder="Search by name, ID or username..." autocomplete="off">
                <button class="btn" id="al-search-btn"><span class="material-icons">search</span></button>
            </div>
        </div>

        <div class="alm-right">
            <div class="alm-actions">
                <button class="btn btn-secondary" id="al-reset-all-btn"><span class="material-icons">restart_alt</span> Reset All</button>
            </div>
        </div>
    </div>

    <div class="alm-table-wrap">
        <div class="table-scroll">
            <table class="alm-table" id="al-table" aria-label="Access level table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Number</th>
                        <th>Username</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Access Level</th>
                    </tr>
                </thead>
                <tbody id="al-tbody">
                    <tr><td colspan="7" class="placeholder">Loading accounts...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
  window.initAccessLevelManagement = function(){
    var root = document.getElementById('accesslevel-root');
    if (!root) return;
    if (root.dataset.inited === '1') return;
    root.dataset.inited = '1';

    var tbody = document.getElementById('al-tbody');
    var allRows = [];
    var apiBase = '<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8'); ?>';

    function esc(v){ return String(v == null ? '' : v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function renderRows(rows){
      if (!tbody) return;
      if (!rows || rows.length === 0){
        tbody.innerHTML = '<tr><td colspan="7" class="placeholder">No accounts found.</td></tr>';
        return;
      }
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
                '<td>' + esc(r.access_level) + '</td>' +
                '</tr>';
      });
      tbody.innerHTML = html;
    }

    function parsePermissions(raw){
      if (raw === null || raw === undefined) return [];
      if (Array.isArray(raw)) return raw.map(function(x){ return String(x).trim(); }).filter(Boolean);
      var s = String(raw).trim();
      if (!s) return [];
      if (s.charAt(0) === '[' || s.charAt(0) === '{') {
        try {
          var dec = JSON.parse(s);
          if (Array.isArray(dec)) return dec.map(function(x){ return String(x).trim(); }).filter(Boolean);
        } catch (e) {}
      }
      return s.split(/[;,|]/).map(function(x){ return String(x).trim(); }).filter(Boolean);
    }

    function applyFilters(){
      var q = (document.getElementById('al-search-input') || {value:''}).value.trim().toLowerCase();
      var filtered = allRows.filter(function(r){
        if (!q) return true;
        var hay = (r.id_number+' '+r.username+' '+r.firstname+' '+r.middlename+' '+r.lastname+' '+r.access_level).toLowerCase();
        return hay.indexOf(q) !== -1;
      });
      renderRows(filtered);
    }

    function debounce(fn, wait){ var t; return function(){ clearTimeout(t); var args = arguments; t = setTimeout(function(){ fn.apply(null, args); }, wait); }; }

    function loadAccounts(){
      fetch(apiBase + '/public/api/account-load.php', { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.ok && Array.isArray(json.rows)) {
            allRows = json.rows;
            renderRows(allRows);
            var si = document.getElementById('al-search-input');
            var sb = document.getElementById('al-search-btn');
            if (si) si.addEventListener('input', debounce(applyFilters, 200));
            if (sb) sb.addEventListener('click', applyFilters);
            try {
              if (si && String(si.value || '').trim() !== '') {
                applyFilters();
              }
            } catch (e) {}
          } else {
            tbody.innerHTML = '<tr><td colspan="7" class="placeholder">Unable to load accounts.</td></tr>';
          }
        })
        .catch(function(){
          tbody.innerHTML = '<tr><td colspan="7" class="placeholder">Unable to load accounts.</td></tr>';
        });
    }

    var resetAllBtn = document.getElementById('al-reset-all-btn');
    if (resetAllBtn) {
      resetAllBtn.addEventListener('click', function(){
        var si = document.getElementById('al-search-input');
        if (si) si.value = '';
        renderRows(allRows);
      });
    }

    if (tbody && !root.dataset.modalInit) {
      tbody.addEventListener('click', function(e){
        var tr = e.target.closest && e.target.closest('tr');
        if (!tr || !tbody.contains(tr)) return;
        var id = tr.dataset.id;
        if (!id) return;
        var row = allRows.find(function(r){ return String(r.id_number || r.id || r.no || '') === id; });
        if (!row) return;
        if (window.showAccessLevelModal) {
          var uname = row.username || ((row.firstname || '') + ' ' + (row.lastname || '')).trim() || 'Unknown';
          var al = (row.access_level === null || row.access_level === undefined || String(row.access_level).trim() === '') ? '0' : row.access_level;
          var activePerms = parsePermissions(row.active_permissions);
          window.showAccessLevelModal({
            id_number: row.id_number || row.id || row.no || '',
            username: uname,
            access_level: al,
            active_permissions: activePerms,
            permissions: activePerms,
            active_permission: activePerms.length ? activePerms.join(', ') : '-'
          });
        }
      });
      root.dataset.modalInit = '1';
    }

    window.refreshAccessLevelManagement = function(){ loadAccounts(); };

    loadAccounts();
  };
</script>
PHP;
}

function pbac_accesslevel_css_template(): string
{
        return <<<'CSS'
/* Minimal styles for Access Level Management component */
.access-level-management{padding:16px;background:#fff;border-radius:6px}
.alm-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.alm-search{display:flex;align-items:center;gap:8px}
.alm-search label{font-size:13px;color:var(--muted)}
.alm-search input{padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;min-width:220px}
.alm-search .btn{padding:6px 8px}
.alm-actions .btn{padding:6px 10px}
.table-scroll{overflow:auto;border:1px solid #e5e7eb;border-radius:6px}
.alm-table{width:100%;border-collapse:collapse}
.alm-table th,.alm-table td{padding:8px 10px;text-align:left;border-bottom:1px solid #f3f4f6}
.alm-table thead th{background:#f9fafb;font-weight:600}
.placeholder{color:var(--muted);text-align:center;padding:20px}
.alm-table tr.selected{background:#eef2ff}

/* Row hover and pointer for interactive rows */
.alm-table tbody tr[data-id]{cursor:pointer;transition:background-color .12s ease}
.alm-table tbody tr[data-id]:hover{background:#f8fafc}

/* Component-scoped button styles (do not override global .btn) */
.access-level-management .btn{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #d1d5db;background:#ffffff;color:#111827;border-radius:6px;cursor:pointer}
.access-level-management .btn .material-icons{font-size:18px;line-height:1;transition:color .12s ease,transform .12s ease}
.access-level-management .btn{transition:background-color .12s ease,transform .06s ease,color .12s ease,box-shadow .12s ease}
.access-level-management .btn:active{transform:translateY(1px)}

/* Red accent for Reset All */
.access-level-management .btn.btn-secondary{background:var(--accent);border-color:var(--accent-dark);color:#ffffff}
.access-level-management .btn.btn-secondary:hover{background:var(--accent-dark)}
.access-level-management .btn.btn-secondary:active{transform:translateY(1px)}

/* Search button hover/icon effect */
.alm-search .btn{height:34px;background:transparent;border-radius:6px;border:1px solid transparent;padding:6px 8px}
.alm-search .btn .material-icons{color:var(--muted);transition:color .12s ease,transform .12s ease}
.alm-search .btn:hover{background:#f3f4f6;border-color:#e5e7eb}
.alm-search .btn:hover .material-icons{color:var(--accent);transform:translateY(-1px)}
.alm-actions .btn{height:34px}
CSS;
}

function pbac_no_access_level_display_template(): string
{
        return <<<'PHP'
<?php /* No access level display component */ ?>
<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
$appBaseUrl = rtrim((string) $appBaseUrl, '/');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/pages/maintenance/no-access/no-access-level-display.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<section class="no-access-card" id="no-access-card-root">
    <div class="card" role="status" aria-live="polite">
        <span class="material-icons icon" aria-hidden="true">lock</span>
        <h3>You do not have any Access Level present</h3>
        <p>Contact your administrator to request access.</p>
    </div>
</section>
PHP;
}

function pbac_no_access_level_display_css_template(): string
{
        return <<<'CSS'
.no-access-card { display:flex; justify-content:center; padding:28px; }
.no-access-card .card { background:#ffffff; border-radius:10px; box-shadow:0 6px 18px rgba(18,38,63,0.08); padding:28px; max-width:820px; width:100%; border:1px solid #eef2f6; text-align:center; }
.no-access-card .card .icon { font-size:36px; color:#e04b4b; display:block; margin-bottom:8px; }
.no-access-card .card h3 { margin:0 0 8px; font-size:20px; color:#203040; }
.no-access-card .card p { margin:0; color:#546075; }
CSS;
}

function pbac_accesslevel_modal_template(): string
{
        return <<<'PHP'
<?php /* Access Level Modal: sample-parity implementation */ ?>
<?php
$appBaseUrl = isset($appBaseUrl) ? rtrim((string) $appBaseUrl, '/') : '';
if ($appBaseUrl === '') {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $appBaseUrl = preg_replace('#/src/.*$#', '', $scriptName);
        $appBaseUrl = rtrim((string) $appBaseUrl, '/');
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/modals/accesslevel-modal/accesslevel-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>
<link rel="stylesheet" href="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/modals/accesslevel-modal/accesslevel-update-success-modal.css', ENT_QUOTES, 'UTF-8'); ?>" data-component-css>

<div id="accesslevel-modal" class="alm-modal hidden" role="dialog" aria-modal="true" aria-labelledby="alm-title">
    <div class="alm-overlay" data-close></div>
    <div class="alm-dialog">
        <button class="alm-close" aria-label="Close" data-close>&times;</button>
        <div class="alm-inner">
            <aside class="alm-preview-sidebar" aria-label="Sidebar preview">
                <div class="alm-preview-top">
                    <img src="<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : '') . '/src/assets/images/logo1.png', ENT_QUOTES, 'UTF-8'); ?>" class="alm-preview-logo" alt="Logo">
                    <div class="alm-preview-brand">
                        <div class="alm-preview-label">General Ledger Extraction</div>
                        <div class="alm-preview-sub">GLE SYSTEM</div>
                    </div>
                </div>

                <nav class="alm-preview-menu">
                    <ul id="alm-preview-menu-list"></ul>
                </nav>
            </aside>

            <section class="alm-editor" aria-labelledby="alm-title">
                <h2 id="alm-title">Manage Access Level</h2>
                <div class="alm-editor-body">
                    <form id="alm-form" onsubmit="return false;">
                        <div class="field-row">
                            <label>Username:</label>
                            <div class="field-value" id="alm-username">-</div>
                        </div>

                        <div class="field-row">
                            <label>Access Level:</label>
                            <div class="field-value" id="alm-access-level" data-value="0">0</div>
                        </div>

                        <div class="field-row">
                            <label>Active Permission:</label>
                            <div class="field-value" id="alm-active-permission">-</div>
                        </div>

                        <div class="field-row perms">
                            <label>Selected Permissions:</label>
                            <div class="perms-list" id="alm-selected-perms">
                                <div id="alm-selected-perms-cards" class="alm-perm-cards" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="field-row map-row">
                            <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                                <label style="margin:0;">Access Level Map:</label>
                                <div id="alm-map-root-toolbar" style="margin-left:16px;">
                                    <label class="alm-map-selectall-label" style="display:inline-flex;align-items:center;gap:8px;">
                                        <input type="checkbox" id="alm-map-select-all"> Select all
                                    </label>
                                </div>
                            </div>
                            <div class="field-value" id="alm-map-root">
                                <div id="alm-map" class="alm-map-card">
                                    <div id="alm-map-content" class="alm-map-content">
                                        <div class="alm-map-empty">Loading map preview...</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </section>
        </div>
        <div class="alm-footer">
            <div class="alm-footer-inner">
                <button class="btn btn-primary" id="alm-save">Save</button>
                <button class="btn" id="alm-cancel" data-close>Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/accesslevel-update-success-modal.php'; ?>

<script>
    (function(){
        var modal = document.getElementById('accesslevel-modal');
        if (!modal) return;

        var almBase = '<?= htmlspecialchars(($appBaseUrl !== '' ? $appBaseUrl : ''), ENT_QUOTES, 'UTF-8'); ?>';

        function normalizeUserPerms(input){
            if (!input) return [];
            if (Array.isArray(input)) return input.map(function(x){ return String(x).trim(); }).filter(Boolean);
            var s = String(input).trim();
            if (!s) return [];
            if (s.charAt(0) === '[' || s.charAt(0) === '{') {
                try {
                    var dec = JSON.parse(s);
                    if (Array.isArray(dec)) return dec.map(function(x){ return String(x).trim(); }).filter(Boolean);
                } catch (e) {}
            }
            return s.split(/[;,|]/).map(function(x){ return String(x).trim(); }).filter(Boolean);
        }

        window.showAccessLevelModal = function(opts){
            opts = opts || {};
            modal.classList.remove('hidden');
            modal.dataset.idNumber = opts.id_number || opts.id || opts.idNumber || '';
            var mapReady = window.almLoadAccessMap ? window.almLoadAccessMap() : Promise.resolve();
            var username = opts.username || opts.user || 'Unknown';
            document.getElementById('alm-username').textContent = username;

            mapReady.then(function(){
                return fetch(almBase + '/public/api/accesslevel-fetch.php', { credentials: 'same-origin' });
            })
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if (!json || !json.ok) return;

                    var alDisplay = document.getElementById('alm-access-level');
                    if (alDisplay){
                        var userAl = opts.access_level || '';
                        if (!userAl) userAl = '0';
                        alDisplay.textContent = userAl;
                        alDisplay.dataset.value = userAl;
                    }

                    var permsWrap = document.getElementById('alm-selected-perms');
                    if (permsWrap){
                        var cards = document.getElementById('alm-selected-perms-cards');
                        if (!cards){
                            cards = document.createElement('div');
                            cards.id = 'alm-selected-perms-cards';
                            cards.className = 'alm-perm-cards';
                            permsWrap.innerHTML = '';
                            permsWrap.appendChild(cards);
                        }
                        cards.innerHTML = '';

                        var userPerms = opts.permissions || opts.active_permissions || null;
                        if (userPerms) {
                            userPerms = normalizeUserPerms(userPerms);
                            if (window.almMapSetSelectedPerms) window.almMapSetSelectedPerms(userPerms);
                        }
                    }

                    var ap = normalizeUserPerms(opts.active_permissions || opts.permissions || opts.active_permission || '');
                    (function renderActivePerms(list){
                        var out = document.getElementById('alm-active-permission');
                        if (!out) return;
                        out.innerHTML = '';
                        if (!list || list.length === 0){ out.textContent = '-'; return; }
                        list.forEach(function(p){
                            var c = document.createElement('div');
                            c.className = 'alm-perm-card active-perm';
                            c.dataset.perm = p;
                            c.style.backgroundColor = '#16a34a';
                            c.style.color = 'var(--surface)';
                            c.style.borderColor = '#15803d';
                            c.style.boxShadow = '0 6px 18px rgba(16,185,129,0.12)';
                            c.style.display = 'inline-flex';
                            c.style.alignItems = 'center';
                            c.style.gap = '8px';
                            c.style.padding = '6px 10px';
                            c.style.borderRadius = '14px';
                            var t = document.createElement('span');
                            t.className = 'alm-perm-text';
                            t.textContent = p;
                            c.appendChild(t);
                            out.appendChild(c);
                        });
                    })(ap);
                })
                .catch(function(){});
        };

        modal.querySelectorAll('[data-close]').forEach(function(el){ el.addEventListener('click', function(){ modal.classList.add('hidden'); }); });

        var menu = modal.querySelector('.alm-preview-menu');
        if (menu){
            menu.addEventListener('click', function(e){
                var toggle = e.target.closest && e.target.closest('.alm-menu-toggle');
                if (toggle){
                    var item = toggle.parentElement;
                    var expanded = item.classList.contains('expanded');
                    if (expanded){ item.classList.remove('expanded'); toggle.setAttribute('aria-expanded','false'); }
                    else { item.classList.add('expanded'); toggle.setAttribute('aria-expanded','true'); }
                }

                var sub = e.target.closest && e.target.closest('.alm-sub-item');
                if (sub){
                    modal.querySelectorAll('.alm-sub-item').forEach(function(s){ s.classList.remove('active'); });
                    sub.classList.add('active');
                }
            });
        }

        var save = document.getElementById('alm-save');
        if (save){
            save.addEventListener('click', function(){
                var username = document.getElementById('alm-username').textContent;
                var alEl = document.getElementById('alm-access-level');
                var access = (alEl && alEl.dataset && alEl.dataset.value) ? alEl.dataset.value : (alEl ? alEl.textContent : '0');
                var perms = Array.from(document.querySelectorAll('#alm-map .alm-map-sub.selected')).map(function(i){ return i.dataset.perm; });
                perms = perms.filter(function(v, idx, arr){ return !!v && arr.indexOf(v) === idx; });
                var idNumber = modal.dataset.idNumber || '';
                if (!idNumber){
                    modal.classList.add('hidden');
                    return;
                }

                fetch(almBase + '/public/api/accesslevel-update.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_number: idNumber, access_level: access, permissions: perms })
                }).then(function(r){ return r.json(); })
                .then(function(json){
                    if (json && json.success){
                        if (window.refreshAccessLevelManagement) try{ window.refreshAccessLevelManagement(); }catch(e){}
                        var successModal = document.getElementById('alm-success-modal');
                        if (successModal){
                            modal.classList.add('hidden');
                            var nameEl = document.getElementById('alm-success-username');
                            if (nameEl) nameEl.textContent = username;
                            successModal.classList.remove('hidden');
                            function closeSuccess(){
                                successModal.classList.add('hidden');
                                var ok = document.getElementById('alm-success-ok');
                                if (ok) ok.removeEventListener('click', closeSuccess);
                                successModal.querySelectorAll('[data-close-success]').forEach(function(el){ el.removeEventListener('click', closeSuccess); });
                            }
                            var okBtn = document.getElementById('alm-success-ok');
                            if (okBtn) okBtn.addEventListener('click', closeSuccess);
                            successModal.querySelectorAll('[data-close-success]').forEach(function(el){ el.addEventListener('click', closeSuccess); });
                        } else {
                            modal.classList.add('hidden');
                        }
                    } else {
                        alert('Failed to save: ' + (json && json.error ? json.error : 'server error'));
                    }
                }).catch(function(){
                    alert('Save request failed');
                });
            });
        }

        (function(){
            var mapRoot = document.getElementById('alm-map');
            if (!mapRoot) return;

            function slugify(input){
                return String(input || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '') || 'item';
            }

            function normalizeMapData(permissionCatalog){
                if (!Array.isArray(permissionCatalog) || permissionCatalog.length === 0) return [];
                return permissionCatalog.map(function(menu, index){
                    var menuKey = String(menu.key || menu.label || ('Menu ' + (index + 1)));
                    var menuLabel = String(menu.label || menu.key || ('Menu ' + (index + 1)));
                    var menuId = slugify(menuKey);
                    var subs = Array.isArray(menu.children) ? menu.children : [];
                    return {
                        id: menuId,
                        key: menuKey,
                        label: menuLabel,
                        icon: menu.icon || 'folder',
                        subs: subs.map(function(sub, subIndex){
                            var subKey = String(sub.key || sub.label || (menuLabel + ' Item ' + (subIndex + 1)));
                            var subLabel = String(sub.label || sub.key || subKey);
                            var subId = slugify(subKey);
                            return {
                                id: subId,
                                key: subKey,
                                label: subLabel,
                                perm: subKey,
                                target: sub.target || ''
                            };
                        })
                    };
                }).filter(function(menu){ return Array.isArray(menu.subs) && menu.subs.length > 0; });
            }

            var mapData = [];

            function renderPreviewMenu(){
                var list = document.getElementById('alm-preview-menu-list');
                if (!list) return;
                list.innerHTML = '';
                mapData.forEach(function(menu){
                    var li = document.createElement('li');
                    li.className = 'alm-menu-item has-submenu';
                    li.dataset.menu = menu.id;

                    var btn = document.createElement('button');
                    btn.className = 'alm-menu-toggle';
                    btn.setAttribute('aria-expanded', 'false');
                    btn.type = 'button';

                    var icon = document.createElement('span');
                    icon.className = 'material-icons';
                    icon.textContent = menu.icon || 'folder';
                    var label = document.createElement('span');
                    label.className = 'label';
                    label.textContent = menu.label;
                    var chev = document.createElement('span');
                    chev.className = 'chev material-icons';
                    chev.textContent = 'expand_more';

                    btn.appendChild(icon);
                    btn.appendChild(label);
                    btn.appendChild(chev);
                    li.appendChild(btn);

                    var subUl = document.createElement('ul');
                    subUl.className = 'alm-submenu';
                    menu.subs.forEach(function(sub){
                        var subLi = document.createElement('li');
                        subLi.className = 'alm-sub-item';
                        subLi.dataset.preview = sub.id;
                        subLi.dataset.menu = menu.id;
                        var dot = document.createElement('span'); dot.className = 'dot';
                        var subLabel = document.createElement('span'); subLabel.className = 'alm-sub-label'; subLabel.textContent = sub.label;
                        subLi.appendChild(dot);
                        subLi.appendChild(subLabel);
                        subUl.appendChild(subLi);
                    });

                    li.appendChild(subUl);
                    list.appendChild(li);
                });
            }

            function loadAccessMap(){
                var candidates = [
                    almBase + '/src/assets/js/accesslevel-map.json',
                    '/src/assets/js/accesslevel-map.json',
                    'src/assets/js/accesslevel-map.json'
                ];

                var tryFetch = function(paths){
                    if (!paths || paths.length === 0) return Promise.reject(new Error('no-path'));
                    var p = paths.shift();
                    return fetch(p + '?ts=' + Date.now(), { credentials: 'same-origin' })
                        .then(function(r){ if (!r.ok) throw new Error('not-found:' + p); return r.json(); })
                        .then(function(json){ return { json: json, path: p }; })
                        .catch(function(){ return tryFetch(paths); });
                };

                return tryFetch(candidates.slice()).then(function(result){
                    var json = result.json;
                    window._almAccessMapJson = json || null;
                    var normalized = normalizeMapData(json && json.permission_catalog ? json.permission_catalog : []);
                    mapData = normalized.length ? normalized : [];
                    renderPreviewMenu();
                    renderMap();
                    updatePreviewAndCode();
                    return mapData;
                }).catch(function(){
                    window._almAccessMapJson = null;
                    mapData = [];
                    renderPreviewMenu();
                    renderMap();
                    var empty = document.querySelector('#alm-map .alm-map-empty');
                    if (empty) empty.textContent = 'Access map not found - run tools/generate_access_map.php to create src/assets/js/accesslevel-map.json';
                    updatePreviewAndCode();
                    return mapData;
                });
            }

            window.almLoadAccessMap = loadAccessMap;

            function renderMap(){
                var contentRoot = mapRoot.querySelector('#alm-map-content') || mapRoot;
                contentRoot.innerHTML = '';
                mapData.forEach(function(menu){
                    var m = document.createElement('div'); m.className = 'alm-map-menu'; m.dataset.menu = menu.id;

                    var mh = document.createElement('div'); mh.className = 'alm-map-menu-head';
                    var iconSpan = document.createElement('span'); iconSpan.className = 'alm-map-icon material-icons'; iconSpan.textContent = menu.icon || 'folder';
                    var labelSpan = document.createElement('span'); labelSpan.className = 'alm-map-menu-label'; labelSpan.textContent = menu.label;
                    mh.appendChild(iconSpan);
                    mh.appendChild(labelSpan);
                    mh.addEventListener('click', function(){ m.classList.toggle('expanded'); });
                    m.appendChild(mh);

                    var list = document.createElement('div'); list.className = 'alm-map-sublist';
                    menu.subs.forEach(function(s){
                        var it = document.createElement('div'); it.className = 'alm-map-sub'; it.dataset.sub = s.id; it.dataset.menu = menu.id; it.dataset.perm = s.perm; it.dataset.permlabel = s.label;
                        var subIcon = document.createElement('span'); subIcon.className = 'alm-sub-icon material-icons'; subIcon.textContent = 'subdirectory_arrow_right';
                        var subLabel = document.createElement('span'); subLabel.className = 'alm-map-sub-label'; subLabel.textContent = s.label;
                        it.appendChild(subIcon);
                        it.appendChild(subLabel);
                        it.addEventListener('click', function(e){
                            e.stopPropagation();
                            it.classList.toggle('selected');
                            syncMenuSelection(menu.id);
                            applySubToPermissions(s.id, s.perm, it.classList.contains('selected'), s.label);
                            updatePreviewAndCode();
                        });
                        list.appendChild(it);
                    });
                    m.appendChild(list);
                    contentRoot.appendChild(m);
                });

                var selectAll = document.getElementById('alm-map-select-all');
                if (selectAll){
                    selectAll.removeEventListener('change', window._almSelectAllHandler || function(){});
                    window._almSelectAllHandler = function(e){
                        var checked = !!e.target.checked;
                        var allSubs = mapRoot.querySelectorAll('.alm-map-sub');
                        allSubs.forEach(function(el){ if (checked) el.classList.add('selected'); else el.classList.remove('selected'); });
                        mapData.forEach(function(menu){ syncMenuSelection(menu.id); });
                        var cards = document.getElementById('alm-selected-perms-cards'); if (cards) cards.innerHTML = '';
                        if (checked){
                            var permSet = {};
                            mapRoot.querySelectorAll('.alm-map-sub.selected').forEach(function(s){ var p = s.dataset.perm; if (p && !permSet[p]){ permSet[p] = true; createPermCard(p, s.dataset.permlabel || p); } });
                        }
                        updatePreviewAndCode();
                    };
                    selectAll.addEventListener('change', window._almSelectAllHandler);
                }
            }

            function updateSelectAllCheckbox(){
                var checkbox = document.getElementById('alm-map-select-all'); if (!checkbox) return;
                var subs = mapRoot.querySelectorAll('.alm-map-sub');
                if (!subs || subs.length === 0){ checkbox.checked = false; checkbox.indeterminate = false; return; }
                var selected = mapRoot.querySelectorAll('.alm-map-sub.selected').length;
                if (selected === 0){ checkbox.checked = false; checkbox.indeterminate = false; }
                else if (selected === subs.length){ checkbox.checked = true; checkbox.indeterminate = false; }
                else { checkbox.checked = false; checkbox.indeterminate = true; }
            }

            function syncMenuSelection(menuId){
                var menuEl = mapRoot.querySelector('.alm-map-menu[data-menu="' + menuId + '"]');
                if (!menuEl) return;
                var any = menuEl.querySelectorAll('.alm-map-sub.selected').length > 0;
                if (any) menuEl.classList.add('selected'); else menuEl.classList.remove('selected');
            }

            function applySubToPermissions(subId, permId, checked, permLabel){
                if (checked){ createPermCard(permId, permLabel || permId); }
                else {
                    var stillSelectedForCard = document.querySelectorAll('#alm-map .alm-map-sub.selected[data-perm="' + permId + '"]').length > 0;
                    if (!stillSelectedForCard) removePermCard(permId);
                }
            }

            function createPermCard(permId, label){
                var cards = document.getElementById('alm-selected-perms-cards');
                if (!cards) return;
                if (cards.querySelector('.alm-perm-card[data-perm="' + permId + '"]')) return;
                var c = document.createElement('div'); c.className = 'alm-perm-card'; c.dataset.perm = permId;
                var t = document.createElement('span'); t.className = 'alm-perm-text'; t.textContent = label || permId;
                var rm = document.createElement('button'); rm.className = 'alm-perm-remove'; rm.type = 'button'; rm.setAttribute('aria-label', 'Remove ' + label);
                rm.innerHTML = '&times;';
                rm.addEventListener('click', function(){
                    document.querySelectorAll('#alm-map .alm-map-sub[data-perm="' + permId + '"]').forEach(function(el){
                        el.classList.remove('selected');
                        syncMenuSelection(el.dataset.menu);
                    });
                    removePermCard(permId);
                    updatePreviewAndCode();
                });
                c.appendChild(t); c.appendChild(rm); cards.appendChild(c);
            }

            function removePermCard(permId){
                var cards = document.getElementById('alm-selected-perms-cards'); if (!cards) return;
                var ex = cards.querySelector('.alm-perm-card[data-perm="' + permId + '"]'); if (ex) ex.remove();
            }

            function updatePreviewAndCode(){
                var preview = modal.querySelector('.alm-preview-menu');
                if (preview){
                    var selected = mapRoot.querySelectorAll('.alm-map-sub.selected');
                    mapData.forEach(function(menu){
                        var m = preview.querySelector('.alm-menu-item[data-menu="' + menu.id + '"]');
                        if (!m) return;
                        var subs = m.querySelectorAll('.alm-sub-item');
                        subs.forEach(function(s){ s.style.display = 'none'; s.classList.remove('active'); });
                        selected.forEach(function(ss){
                            var sid = ss.dataset.sub;
                            var p = m.querySelector('.alm-sub-item[data-preview="' + sid + '"]') || m.querySelector('.alm-sub-item[data-target="' + sid + '"]');
                            if (p) { p.style.display = 'flex'; p.classList.add('active'); }
                        });
                        if (!Array.from(selected).some(function(s){ return s.dataset.menu === menu.id; })){
                            m.style.display = 'none';
                        } else {
                            m.style.display = '';
                        }
                    });
                }

                var selectedPermEls = mapRoot.querySelectorAll('.alm-map-sub.selected');
                var perms = Array.from(selectedPermEls).map(function(el){ return String(el.dataset.perm || '').trim(); }).filter(Boolean);

                if (!perms || perms.length === 0){
                    var alDisplayEmpty = document.getElementById('alm-access-level');
                    if (alDisplayEmpty){ alDisplayEmpty.textContent = '0'; alDisplayEmpty.dataset.value = '0'; }
                    return;
                }

                function arraysEqualAsSets(a, b){
                    if (!Array.isArray(a) || !Array.isArray(b)) return false;
                    if (a.length !== b.length) return false;
                    var sa = a.slice().map(String).sort();
                    var sb = b.slice().map(String).sort();
                    for (var i = 0; i < sa.length; i++){ if (sa[i] !== sb[i]) return false; }
                    return true;
                }

                var code = 0;
                if (window._almAccessMapJson && Array.isArray(window._almAccessMapJson.access_levels)){
                    var matches = [];
                    window._almAccessMapJson.access_levels.forEach(function(entry){
                        var entryPerms = Array.isArray(entry.permissions) ? entry.permissions.map(function(p){ return String(p).trim(); }) : [];
                        if (arraysEqualAsSets(perms, entryPerms)) matches.push(entry.access_level);
                    });
                    if (matches.length > 0){
                        matches.sort(function(a, b){ if (a === -1) return -1; if (b === -1) return 1; return a - b; });
                        code = matches[0];
                    } else {
                        var supers = [];
                        window._almAccessMapJson.access_levels.forEach(function(entry){
                            var entryPerms = Array.isArray(entry.permissions) ? entry.permissions.map(function(p){ return String(p).trim(); }) : [];
                            var allPresent = perms.every(function(p){ return entryPerms.indexOf(p) !== -1; });
                            if (allPresent) supers.push({ level: entry.access_level, count: entryPerms.length });
                        });
                        if (supers.length > 0){
                            var full = supers.find(function(s){ return s.level === -1; });
                            if (full) {
                                code = -1;
                            } else {
                                supers.sort(function(a, b){
                                    if (a.count !== b.count) return a.count - b.count;
                                    return a.level - b.level;
                                });
                                code = supers[0].level;
                            }
                        } else {
                            code = 0;
                        }
                    }
                }

                var alDisplay = document.getElementById('alm-access-level');
                if (alDisplay){ alDisplay.textContent = String(code); alDisplay.dataset.value = String(code); }
                try{ updateSelectAllCheckbox(); }catch(e){}
            }

            loadAccessMap();
            window.almMapSetSelectedPerms = function(userPerms){
                if (!userPerms) return;
                userPerms = normalizeUserPerms(userPerms);
                mapRoot.querySelectorAll('.alm-map-sub.selected').forEach(function(el){ el.classList.remove('selected'); });
                var cards = document.getElementById('alm-selected-perms-cards');
                if (cards) cards.innerHTML = '';
                mapData.forEach(function(menu){
                    menu.subs.forEach(function(s){
                        if (userPerms.indexOf(s.perm) !== -1){
                            var el = mapRoot.querySelector('.alm-map-sub[data-sub="' + s.id + '"]');
                            if (el) el.classList.add('selected');
                        }
                    });
                });
                mapData.forEach(function(menu){ syncMenuSelection(menu.id); });
                (userPerms || []).forEach(function(p){ createPermCard(p, p); });
                updatePreviewAndCode();
            };

            mapRoot.addEventListener('click', function(){ updatePreviewAndCode(); });
        })();

    })();
</script>
PHP;
}

function pbac_accesslevel_modal_css_template(): string
{
        return <<<'CSS'
/* Access Level Modal styles */
.alm-modal.hidden{display:none}
.alm-modal .alm-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000}
.alm-modal .alm-dialog{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(900px,96vw);max-width:96vw;height:76vh;background:var(--surface);z-index:1001;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden;display:flex;flex-direction:column;box-sizing:border-box}
.alm-close{position:absolute;right:12px;top:8px;border:0;background:transparent;font-size:22px;cursor:pointer}
.alm-inner{display:flex;height:100%;flex:1;overflow:auto}

/* Ensure sizing doesn't cause horizontal overflow */
.alm-dialog *, .alm-dialog *:before, .alm-dialog *:after { box-sizing: border-box; }
.alm-preview-sidebar{width:64px; /* mimic collapsed sidebar */
    border-right:1px solid:var(--stroke);padding:14px;background:var(--accent);color:var(--surface);overflow:hidden;transition:width .28s cubic-bezier(.2,.8,.2,1);display:flex;flex-direction:column}
.alm-preview-sidebar:hover{width:220px}
.alm-preview-top{display:flex;gap:12px;align-items:center;margin-bottom:10px}
.alm-preview-logo{width:36px;height:auto;display:block;filter:brightness(0) invert(1)}
.alm-preview-brand{display:flex;flex-direction:column;gap:2px}
.alm-preview-label{font-weight:700;margin-left:4px;opacity:0;transform:translateX(-8px);transition:opacity .18s ease, transform .18s ease;white-space:nowrap;color:var(--surface);font-size:13px;line-height:1.05}
.alm-preview-sub{margin-left:4px;opacity:0;transform:translateX(-8px);transition:opacity .18s ease, transform .18s ease;color:rgba(255,255,255,0.65);font-size:11px;font-weight:500;letter-spacing:0.6px;text-transform:uppercase}
.alm-preview-sidebar:hover .alm-preview-label, .alm-preview-sidebar:hover .alm-preview-sub{opacity:1;transform:translateX(0)}
.alm-preview-menu ul{list-style:none;margin:0;padding:4px 0}
.alm-menu-item{display:block;padding:2px 6px;cursor:default;margin:2px 0}
.alm-menu-item:hover>.alm-menu-toggle{background:var(--accent-hover)}
.alm-menu-toggle{display:flex;align-items:center;gap:8px;width:100%;background:transparent;border:0;padding:6px 8px;cursor:pointer;-webkit-appearance:none;appearance:none;color:inherit}
.alm-preview-sidebar button{background:transparent;border:0;padding:0;margin:0}
.alm-preview-sidebar button:focus{outline:none}
.alm-menu-item .material-icons{font-size:24px;color:var(--surface);vertical-align:middle}
.alm-preview-menu .label{opacity:0;transition:opacity .18s ease;white-space:nowrap;font-weight:500;color:var(--surface)}
.alm-preview-sidebar:hover .label{opacity:1}
.alm-submenu{list-style:none;margin:4px 0 0 0;padding:0;max-height:0;overflow:hidden;transition:max-height .22s ease, opacity .18s ease;opacity:0}
.alm-preview-sidebar:hover .alm-menu-item.expanded .alm-submenu{max-height:240px;opacity:1}
.alm-sub-item{display:flex;align-items:center;gap:8px;padding:6px 8px 6px 32px;border-radius:6px;color:var(--surface);cursor:pointer}
.alm-sub-item:hover{background:var(--accent-hover)}
.alm-sub-label{font-size:13px;font-weight:500;opacity:0;transition:opacity .18s ease}
.alm-preview-sidebar:hover .alm-sub-label{opacity:1}
.alm-sub-item.active{background:rgba(255,255,255,0.12)}
.alm-menu-item.expanded .chev{transform:none}
.alm-preview-sidebar:hover .alm-menu-item.expanded .chev{transform:rotate(180deg)}

.alm-editor{flex:1;padding:18px;overflow:auto;position:relative;padding-bottom:80px}
.alm-editor h2{margin-top:6px;margin-bottom:12px}
.alm-editor .field-row{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.alm-editor .field-row.perms{align-items:flex-start}
.alm-editor .field-row.map-row{flex-direction:column;align-items:flex-start}
.alm-editor label{width:150px;font-weight:600;color:var(--muted);display:flex;align-items:center}
.alm-editor .field-value{flex:1;padding:8px;border:1px solid:var(--stroke);border-radius:6px;background:var(--surface);display:flex;align-items:center;min-height:36px}
.alm-editor .field-row.map-row .field-value{width:100%;margin-top:8px;padding:10px;box-sizing:border-box}
.alm-editor select{padding:8px;border-radius:6px;border:1px solid #d1d5db}
.perms-list{display:flex;flex-direction:column;gap:6px}
.alm-actions-row{display:flex;gap:8px;margin-top:8px}

/* Footer for modal */
.alm-footer{flex:0 0 auto;border-top:1px solid var(--stroke);padding:12px 18px;background:transparent}
.alm-footer-inner{display:flex;justify-content:flex-end;gap:8px}
.alm-footer .btn{padding:8px 12px;border-radius:6px;border:1px solid var(--stroke);background:var(--surface);cursor:pointer}
.alm-footer .btn.btn-primary{background:var(--accent);color:var(--surface);border-color:var(--accent)}
.alm-actions-row .btn.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.map-note{color:var(--muted)}

/* Access level map card */
.alm-map-card{background:transparent;border-radius:6px;padding:6px;width:100%;display:block}
.alm-map-menu{border-radius:6px;margin-bottom:8px;overflow:visible;background:transparent;width:100%;}
.alm-map-menu.expanded .alm-map-sublist{max-height:400px}
.alm-map-menu.selected{box-shadow:inset 0 0 0 2px rgba(0,0,0,0.02)}

/* Menu header: show icon + label; border-only when inactive, filled when selected */
.alm-map-menu-head{display:flex;align-items:center;gap:10px;padding:8px 10px;cursor:pointer;font-weight:600;color:var(--accent);background:transparent;border:1px solid var(--accent);border-radius:6px}
.alm-map-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:rgba(0,0,0,0.03);color:var(--accent);font-size:18px}
.alm-map-menu-label{flex:1}
.alm-map-menu.selected > .alm-map-menu-head{background:var(--accent);color:var(--surface);border-color:var(--accent-dark)}

.alm-map-sublist{max-height:0;overflow:hidden;transition:max-height .18s ease;padding:6px 0}
.alm-map-sub{display:flex;align-items:center;gap:8px;padding:8px 12px;margin:6px 0;border-radius:6px;background:transparent;color:var(--accent);cursor:pointer;border:0;border-left:4px solid var(--accent);width:calc(100% - 22px);margin-left:18px;transition:box-shadow .18s ease, background .12s ease, transform .12s ease}
.alm-sub-icon{color:var(--muted);font-size:16px;width:18px;display:inline-flex;align-items:center;justify-content:center}
.alm-map-sub-label{flex:1}
.alm-map-sub{box-shadow:0 4px 14px rgba(16,24,40,0.03);} /* subtle dropdown shadow to indicate depth when unselected */
.alm-map-sub:hover{box-shadow:0 6px 18px rgba(16,24,40,0.06);transform:translateY(-1px);background:rgba(0,0,0,0.02)}
.alm-map-sub.selected{background:var(--accent);color:var(--surface);font-weight:600;border-left-color:var(--accent-dark);box-shadow:0 8px 22px rgba(0,0,0,0.08)}

/* submenu icon and label should be white when selected */
.alm-map-sub.selected .alm-sub-icon,
.alm-map-sub.selected .alm-map-sub-label,
.alm-map-sub.selected .material-icons{
    color: var(--surface) !important;
}

/* menu icon should be white when its menu is selected */
.alm-map-menu.selected .alm-map-icon,
.alm-map-menu.selected .alm-map-icon.material-icons{
    color: var(--surface) !important;
}

/* stronger framed look when parent menu is expanded to emphasize dropdown */
.alm-map-menu.expanded .alm-map-sub{background: rgba(255,255,255,0.02)}

/* ensure selected submenu keeps accent background even when parent menu is expanded */
.alm-map-menu.expanded .alm-map-sub.selected{
    background: var(--accent) !important;
    color: var(--surface) !important;
    font-weight: 600;
    border-left-color: var(--accent-dark) !important;
    box-shadow:0 8px 22px rgba(0,0,0,0.08);
}
.alm-map-menu.expanded .alm-map-sub.selected .alm-sub-icon,
.alm-map-menu.expanded .alm-map-sub.selected .alm-map-sub-label,
.alm-map-menu.expanded .alm-map-sub.selected .material-icons{
    color: var(--surface) !important;
}

/* small responsive tweaks */
@media (max-width:900px){
    .alm-inner{flex-direction:column}
    .alm-preview-sidebar{width:100%;border-right:0;border-bottom:1px solid #eef2f4}
    .alm-dialog{height:88vh;width:calc(100vw - 32px)}
    .alm-editor{padding-bottom:20px}
    .alm-actions-row{position:static;right:auto;bottom:auto;margin-top:12px}
}

/* Selected permission cards (small chips) */
.alm-perm-cards{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
.alm-perm-card{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:14px;background:var(--surface);border:1px solid var(--stroke);color:var(--muted);font-weight:600;font-size:13px}
.alm-perm-card .alm-perm-text{max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.alm-perm-card .alm-perm-remove{background:transparent;border:0;color:var(--muted);cursor:pointer;font-size:16px;line-height:1}
.alm-perm-card[data-perm].selected{background:var(--accent);color:var(--surface);border-color:var(--accent)}

/* Active permission styling: use a green 'success' chip */
.alm-perm-card.active-perm{background:#16a34a;color:var(--surface);border-color:#15803d}
.alm-perm-card.active-perm .alm-perm-remove{display:none}

/* More specific selectors to override other perm-card rules */
.alm-perm-card[data-perm].active-perm{background:#16a34a !important;color:var(--surface) !important;border-color:#15803d !important}
.alm-perm-card[data-perm].active-perm .alm-perm-remove{display:none !important}
CSS;
}

function pbac_accesslevel_success_modal_template(): string
{
        return <<<'PHP'
<?php /* Access Level update success modal */ ?>
<div id="alm-success-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="alm-success-title">
    <div class="modal-card" role="document">
        <div class="modal-top">
            <div class="modal-icon-bg success"><span class="material-icons modal-icon">check_circle</span></div>
            <div class="modal-title-wrap">
                <h3 id="alm-success-title">Access Level Updated</h3>
                <p class="modal-sub">Access Level and Permission for<br><strong id="alm-success-username">User</strong><br>has been updated successfully.</p>
            </div>
        </div>
        <div class="modal-actions">
            <button id="alm-success-ok" class="btn btn-primary" data-close-success><span class="material-icons">check</span> OK</button>
        </div>
    </div>
</div>
PHP;
}

function pbac_accesslevel_success_modal_css_template(): string
{
        return <<<'CSS'
/* Access Level success modal styles */
#alm-success-modal.modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.4);
    z-index: 1400;
}

#alm-success-modal.modal.hidden { display: none; }

#alm-success-modal .modal-card {
    background: var(--surface);
    width: 520px;
    border-radius: 8px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

#alm-success-modal .modal-top { display:flex; gap:12px; padding:20px; align-items:center; }

#alm-success-modal .modal-icon-bg {
    box-sizing: border-box;
    width:56px;
    height:56px;
    min-width:56px;
    min-height:56px;
    border-radius:50%;
    background: color-mix(in srgb, var(--accent) 15%, var(--surface));
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    overflow:hidden;
    padding:0;
}

#alm-success-modal .modal-icon-bg.success { background: color-mix(in srgb, #16a34a 20%, var(--surface)); }
#alm-success-modal .modal-icon { color: #15803d; font-size:28px; line-height:1; display:inline-block; }

#alm-success-modal .modal-title-wrap h3 { margin:0 0 6px 0; font-size:18px; }
#alm-success-modal .modal-sub { margin:0; color: var(--muted); font-size:14px; line-height:1.35; }

#alm-success-modal .modal-actions { display:flex; gap:8px; padding:16px; justify-content:flex-end; }

#alm-success-modal .btn {
    padding:8px 12px;
    border-radius:4px;
    border:1px solid transparent;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

#alm-success-modal .btn .material-icons { font-size:18px; line-height:1; }
#alm-success-modal .btn-primary { background: var(--accent); color:#fff; }
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

    $sql = "SELECT u.no, u.id_number, u.username, u.firstname, u.middlename, u.lastname,
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
- `src/pages/maintenance/no-access/no-access-level-display.php`
- `src/pages/maintenance/no-access/no-access-level-display.css`
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
        'src/pages/maintenance/no-access',
        'src/controllers/accesslevel',
        'src/modals/accesslevel-modal',
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
        'src/pages/maintenance/no-access/no-access-level-display.php' => pbac_no_access_level_display_template(),
        'src/pages/maintenance/no-access/no-access-level-display.css' => pbac_no_access_level_display_css_template(),
        'src/modals/accesslevel-modal/accesslevel-modal.php' => pbac_accesslevel_modal_template(),
        'src/modals/accesslevel-modal/accesslevel-modal.css' => pbac_accesslevel_modal_css_template(),
        'src/modals/accesslevel-modal/accesslevel-update-success-modal.php' => pbac_accesslevel_success_modal_template(),
        'src/modals/accesslevel-modal/accesslevel-update-success-modal.css' => pbac_accesslevel_success_modal_css_template(),
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
