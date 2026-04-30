<?php
/**
 * script/sidebar-add-menu.php
 *
 * Adds a sidebar menu and its submenus, optionally generating scaffold files.
 * Uses NVIDIA NIM AI to suggest proper icon, title, and subtitle metadata
 * for bp_section_header_html() — AI does NOT generate any PHP code.
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

// ── Helpers ───────────────────────────────────────────────────────────────────

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
    $line = ask($prompt . ' (Y/N):');
    return strtoupper(substr($line, 0, 1)) === 'Y';
}

function slug(string $text): string
{
    return strtolower(preg_replace('/\s+/', '-', trim($text)) ?? '');
}

function noSpaceSlug(string $text): string
{
    return strtolower(preg_replace('/\s+/', '', trim($text)) ?? '');
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

// ── NVIDIA NIM — API key management ──────────────────────────────────────────

function getNimConfigPath(): string
{
    return 'C:\\ML CLI\\Tools\\mlcli-config.json';
}

function getApiKey(): string
{
    $configPath = getNimConfigPath();

    if (!is_dir(dirname($configPath))) {
        @mkdir(dirname($configPath), 0777, true);
    }

    if (file_exists($configPath)) {
        $config = json_decode((string) file_get_contents($configPath), true);
        if (!empty($config['nvidia_api_key'])) {
            return (string) $config['nvidia_api_key'];
        }
    }

    return promptAndSaveApiKey($configPath);
}

function promptAndSaveApiKey(string $configPath): string
{
    out('');
    out('No NVIDIA NIM API KEY Detected');
    out('Get your API Key on https://build.nvidia.com/');
    $apiKey = ask('API KEY:');

    // Read existing config and merge, so all other fields are preserved
    $existing = is_file($configPath)
        ? json_decode((string) file_get_contents($configPath), true) ?? []
        : [];
    $existing['nvidia_api_key'] = $apiKey;

    file_put_contents($configPath, json_encode($existing, JSON_PRETTY_PRINT));

    return $apiKey;
}

// ── Valid Material Icons list ─────────────────────────────────────────────────

const VALID_MATERIAL_ICONS = [
    'home', 'dashboard', 'menu', 'settings', 'person', 'people', 'manage_accounts',
    'account_circle', 'badge', 'category', 'inventory', 'storage', 'backup',
    'cloud_upload', 'cloud_download', 'upload', 'download', 'file_upload',
    'description', 'assignment', 'fact_check', 'task', 'checklist', 'rule',
    'build', 'construction', 'engineering', 'handyman', 'cleaning_services',
    'terminal', 'code', 'dns', 'api', 'integration_instructions',
    'security', 'lock', 'lock_open', 'shield', 'verified_user', 'vpn_key',
    'visibility', 'visibility_off', 'preview', 'monitor_heart', 'sensors',
    'analytics', 'bar_chart', 'pie_chart', 'show_chart', 'timeline',
    'campaign', 'notifications', 'notifications_active', 'mail', 'sms',
    'chat', 'comment', 'forum', 'message', 'send', 'inbox',
    'schedule', 'calendar_month', 'event', 'today', 'date_range',
    'business', 'store', 'point_of_sale', 'receipt_long', 'payments',
    'attach_money', 'price_check', 'currency_exchange', 'account_balance',
    'trending_up', 'trending_down', 'insights', 'monitoring', 'speed',
    'support_agent', 'help', 'help_center', 'contact_support', 'language',
    'translate', 'globe', 'public', 'location_on', 'my_location',
    'map', 'explore', 'pin_drop', 'travel_explore', 'restaurant',
    'local_shipping', 'flight', 'train', 'directions_car', 'two_wheeler',
    'medical_services', 'health_and_safety', 'vaccines', 'healing', 'psychology',
    'school', 'library_books', 'auto_stories', 'history_edu', 'science',
    'computer', 'devices', 'laptop', 'smartphone', 'tablet', 'tv',
    'video_library', 'photo_library', 'music_note', 'audiotrack', 'mic',
    'movie', 'live_tv', 'videocam', 'camera_alt', 'image',
    'edit', 'edit_note', 'draw', 'brush', 'format_paint',
    'print', 'print_disabled', 'content_copy', 'content_cut', 'content_paste',
    'delete', 'delete_forever', 'restore_from_trash', 'archive', 'unarchive',
    'mark_email_unread', 'mark_email_read', 'filter_list', 'search', 'find_in_page',
    'sort', 'swap_vert', 'filter_alt', 'tune', 'settings_applications',
    'app_settings_alt', 'admin_panel_settings', 'experiment',
    'extension', 'widgets', 'add_box', 'add_circle', 'remove_circle',
    'refresh', 'sync', 'loop', 'autorenew', 'update',
    'power', 'power_settings_new', 'restart_alt', 'download_for_offline',
    'upload_file', 'cloud_sync', 'save', 'save_alt',
    'file_present', 'folder', 'folder_open', 'drive_file_move',
    'share', 'ios_share', 'link', 'link_off', 'qr_code',
    'qr_code_2', 'fingerprint', 'id_card', 'badge_certified',
    'workspace_premium', 'verified', 'stars', 'star', 'star_border',
    'favorite', 'favorite_border', 'thumb_up', 'thumb_down', 'feedback',
    'flag', 'report', 'gavel', 'policy', 'privacy_tip',
    'warning', 'error', 'error_outline', 'info', 'lightbulb',
    'tips_and_updates', 'auto_fix', 'auto_awesome', 'palette', 'color_lens',
    'texture', 'style', 'gradient',
    'notifications_none', 'addon', 'pending', 'pending_actions', 'hourglass_empty',
    'timer', 'watch_later', 'wifi', 'wifi_off', 'signal_cellular_4_bar',
    'router', 'settings_ethernet', 'memory', 'sd_storage', 'data_usage',
    'battery_full', 'battery_charging_full',
    'dark_mode', 'light_mode', 'contrast', 'invert_colors', 'brightness_6',
    'pinch', 'zoom_in', 'zoom_out', 'fullscreen', 'fullscreen_exit',
    'cancel', 'close', 'check', 'check_circle', 'add_task',
    'radio_button_checked', 'radio_button_unchecked', 'toggle_on', 'toggle_off',
    'alt_route', 'commute', 'local_offer', 'sell',
    'shopping_cart', 'shopping_bag', 'redeem', 'card_giftcard', 'loyalty',
    'contact_page', 'perm_identity', 'supervisor_account', 'manage_search', 'app_shortcut',
    'webhook', 'hub', 'account_tree', 'rule_folder', 'commit',
    'call_merge', 'center_focus_strong', 'filter_center_focus', 'camera', 'videocam_off',
    'mic_off', 'volume_up', 'stop', 'play_arrow', 'pause',
    'skip_next', 'skip_previous', 'fast_forward', 'fast_rewind',
    'replay', 'shuffle', 'repeat', 'repeat_one', 'queue_music',
    'playlist_add', 'add_to_queue', 'remove_from_queue', 'queue', 'hevc',
];

// Map of keyword -> default icon for hallucinated values
const ICON_FALLBACK_MAP = [
    'food' => 'restaurant', 'pizza' => 'restaurant', 'meal' => 'restaurant', 'eat' => 'restaurant',
    'user' => 'person', 'profile' => 'account_circle', 'account' => 'manage_accounts',
    'setting' => 'settings', 'config' => 'settings_applications', 'preference' => 'tune',
    'report' => 'description', 'summary' => 'summarize', 'document' => 'description',
    'analytics' => 'analytics', 'chart' => 'bar_chart', 'graph' => 'show_chart',
    'money' => 'attach_money', 'payment' => 'payments', 'transaction' => 'receipt_long',
    'inventory' => 'inventory', 'stock' => 'inventory', 'product' => 'inventory',
    'employee' => 'badge', 'staff' => 'supervisor_account',
    'order' => 'point_of_sale', 'sales' => 'trending_up', 'purchase' => 'sell',
    'log' => 'fact_check', 'audit' => 'rule', 'history' => 'history_edu',
    'database' => 'storage', 'server' => 'dns', 'api' => 'api',
    'backup' => 'backup', 'restore' => 'restore_from_trash', 'export' => 'upload_file',
    'import' => 'download_for_offline', 'upload' => 'cloud_upload', 'download' => 'cloud_download',
    'security' => 'security', 'permission' => 'lock', 'access' => 'vpn_key',
    'notification' => 'notifications', 'alert' => 'notifications_active', 'bell' => 'notifications',
    'customer' => 'support_agent', 'client' => 'contact_page',
    'calendar' => 'calendar_month', 'schedule' => 'schedule', 'event' => 'event',
    'map' => 'map', 'location' => 'location_on', 'address' => 'pin_drop',
    'image' => 'image', 'photo' => 'photo_library', 'gallery' => 'photo_library',
    'video' => 'videocam', 'camera' => 'camera_alt', 'media' => 'video_library',
    'music' => 'music_note', 'audio' => 'audiotrack',
    'edit' => 'edit', 'note' => 'edit_note', 'write' => 'draw',
    'trash' => 'delete', 'delete' => 'delete_forever', 'remove' => 'remove_circle',
    'search' => 'search', 'find' => 'find_in_page', 'filter' => 'filter_list',
    'sort' => 'sort', 'arrange' => 'swap_vert',
    'mail' => 'mail', 'email' => 'mail', 'inbox' => 'inbox', 'message' => 'message',
    'chat' => 'chat', 'comment' => 'comment', 'feedback' => 'feedback',
    'approve' => 'check_circle', 'verify' => 'verified', 'done' => 'check',
    'reject' => 'cancel', 'block' => 'block', 'deny' => 'close',
    'add' => 'add_box', 'create' => 'add_circle', 'new' => 'add_task',
    'help' => 'help', 'question' => 'help_center', 'faq' => 'contact_support',
    'print' => 'print', 'copy' => 'content_copy', 'paste' => 'content_paste',
    'refresh' => 'refresh', 'reload' => 'sync', 'update' => 'update',
    'home' => 'home', 'dashboard' => 'dashboard', 'menu' => 'menu',
    'maintenance' => 'build', 'repair' => 'construction', 'tools' => 'handyman',
];

/**
 * Clamps every icon in AI metadata to a valid Material Icon.
 * Unknown names are matched by keyword against ICON_FALLBACK_MAP, then 'question_mark'.
 */
function validateAndFixIcons(array $aiData): array
{
    $menuIcon = $aiData['menu']['icon'] ?? '';
    $menuName = $aiData['menu']['name'] ?? '';

    // Validate menu icon
    if (!in_array($menuIcon, VALID_MATERIAL_ICONS, true)) {
        $aiData['menu']['icon'] = iconFallback($menuIcon, $menuName);
    }

    // Validate submenu icons
    foreach ($aiData['submenus'] as &$sub) {
        $subIcon = $sub['icon'] ?? '';
        $subName = $sub['name'] ?? '';
        if (!in_array($subIcon, VALID_MATERIAL_ICONS, true)) {
            $sub['icon'] = iconFallback($subIcon, $subName);
        }
    }
    unset($sub);

    return $aiData;
}

function iconFallback(string $rawIcon, string $relatedName): string
{
    $raw = strtolower(preg_replace('/[-_\s]+/', '', $rawIcon));
    $rel = strtolower(preg_replace('/[-_\s]+/', '', $relatedName));

    // Direct match from fallback map by scanning both the icon name and the related name
    foreach (ICON_FALLBACK_MAP as $keyword => $icon) {
        if (str_contains($raw, $keyword) || str_contains($rel, $keyword)) {
            return $icon;
        }
    }

    // Last resort
    return 'question_mark';
}

// ── NVIDIA NIM — AI call ──────────────────────────────────────────────────────

/**
 * Calls NVIDIA NIM and returns structured metadata for the menu.
 * The AI only returns naming/icon/subtitle data — never PHP code.
 *
 * @param  string  $apiKey
 * @param  string  $menuName      Raw menu name from user
 * @param  string[] $submenuNames Raw submenu names from user
 * @return array{menu:array,submenus:array}|array{error:string}
 */
function callNvidiaNim(string $apiKey, string $menuName, array $submenuNames): array
{
    $url = 'https://integrate.api.nvidia.com/v1/chat/completions';

    $submenuList = implode(', ', $submenuNames);

    // Curated list of real Google Material Icons (snake_case, no spaces, no variants)
    // Full list: https://fonts.google.com/icons
    $validIcons = implode(', ', [
        'home', 'dashboard', 'menu', 'settings', 'person', 'people', 'manage_accounts',
        'account_circle', 'badge', 'category', 'inventory', 'storage', 'backup',
        'cloud_upload', 'cloud_download', 'upload', 'download', 'file_upload',
        'description', 'assignment', 'fact_check', 'task', 'checklist', 'rule',
        'build', 'construction', 'engineering', 'handyman', 'cleaning_services',
        'engineering', 'terminal', 'code', 'dns', 'api', 'integration_instructions',
        'security', 'lock', 'lock_open', 'shield', 'verified_user', 'vpn_key',
        'visibility', 'visibility_off', 'preview', 'monitor_heart', 'sensors',
        'analytics', 'bar_chart', 'pie_chart', 'show_chart', 'timeline',
        'campaign', 'notifications', 'notifications_active', 'mail', 'sms',
        'chat', 'comment', 'forum', 'message', 'send', 'inbox',
        'schedule', 'calendar_month', 'event', 'today', 'date_range',
        'business', 'store', 'point_of_sale', 'receipt_long', 'payments',
        'attach_money', 'price_check', 'currency_exchange', 'account_balance',
        'trending_up', 'trending_down', 'insights', 'monitoring', 'speed',
        'support_agent', 'help', 'help_center', 'contact_support', 'language',
        'translate', 'globe', 'public', 'location_on', 'my_location',
        'map', 'explore', 'pin_drop', 'travel_explore', 'restaurant',
        'local_shipping', 'flight', 'train', 'directions_car', 'two_wheeler',
        'medical_services', 'health_and_safety', 'vaccines', 'healing', 'psychology',
        'school', 'library_books', 'auto_stories', 'history_edu', 'science',
        'computer', 'devices', 'laptop', 'smartphone', 'tablet', 'tv',
        'video_library', 'photo_library', 'music_note', 'audiotrack', 'mic',
        'movie', 'live_tv', 'videocam', 'camera_alt', 'image',
        'edit', 'edit_note', 'draw', 'brush', 'format_paint',
        'print', 'print_disabled', 'content_copy', 'content_cut', 'content_paste',
        'delete', 'delete_forever', 'restore_from_trash', 'archive', 'unarchive',
        'mark_email_unread', 'mark_email_read', 'filter_list', 'search', 'find_in_page',
        'sort', 'swap_vert', 'filter_alt', 'tune', 'settings_applications',
        'app_settings_alt', 'admin_panel_settings', 'tune', 'experiment',
        'extension', 'widgets', 'add_box', 'add_circle', 'remove_circle',
        'refresh', 'sync', 'loop', 'autorenew', 'update',
        'power', 'power_settings_new', 'restart_alt', 'download_for_offline',
        'upload_file', 'cloud_sync', 'backup', 'save', 'save_alt',
        'file_present', 'folder', 'folder_open', 'drive_file_move',
        'share', 'ios_share', 'link', 'link_off', 'qr_code',
        'qr_code_2', 'barcode_reader', 'fingerprint', 'id_card', 'badge_certified',
        'workspace_premium', 'verified', 'stars', 'star', 'star_border',
        'favorite', 'favorite_border', 'thumb_up', 'thumb_down', 'feedback',
        'flag', 'report', 'gavel', 'policy', 'privacy_tip',
        'warning', 'error', 'error_outline', 'info', 'lightbulb',
        'tips_and_updates', 'auto_fix', 'auto_awesome', 'psychology', 'translate',
        'palette', 'color_lens', 'texture', 'style', 'gradient',
        'notifications_none', 'addon', 'pending', 'pending_actions', 'hourglass_empty',
        'timer', 'watch_later', 'schedule', 'flash_on', 'bolt',
        'wifi', 'wifi_off', 'signal_cellular_4_bar', 'router', 'settings_ethernet',
        'memory', 'sd_storage', 'data_usage', 'battery_full', 'battery_charging_full',
        'dark_mode', 'light_mode', 'contrast', 'invert_colors', 'brightness_6',
        'pinch', 'zoom_in', 'zoom_out', 'fullscreen', 'fullscreen_exit',
        'cancel', 'close', 'check', 'check_circle', 'add_task',
        'radio_button_checked', 'radio_button_unchecked', 'toggle_on', 'toggle_off',
        'switch_access_shortcut', 'alt_route', 'commute', 'local_offer', 'sell',
        'shopping_cart', 'shopping_bag', 'redeem', 'card_giftcard', 'loyalty',
        'contact_page', 'perm_identity', 'supervisor_account', 'manage_search', 'app_shortcut',
        'webhook', 'hub', 'account_tree', 'rule_folder', 'commit',
        'call_merge', 'center_focus_strong', 'filter_center_focus', 'camera', 'videocam_off',
        'mic_off', 'volume_off', 'stop', 'play_arrow', 'pause',
        'skip_next', 'skip_previous', 'skip_previous', 'fast_forward', 'fast_rewind',
        'replay', 'shuffle', 'repeat', 'repeat_one', 'queue_music',
        'playlist_add', 'add_to_queue', 'remove_from_queue', 'queue', 'hevc',
        'subscript', 'superscript', 'format_bold', 'format_italic', 'format_underlined',
        'format_list_bulleted', 'format_list_numbered', 'format_align_left', 'format_align_center',
        'format_align_right', 'strikethrough_s', 'clear', 'summarize', 'short_text',
    ]);

    $systemPrompt = 'You are a UI naming assistant for a PHP web application sidebar. '
        . 'Given a menu name and submenus, return ONLY valid JSON — no explanations, '
        . 'no markdown, no backticks, no extra text.';

    $userPrompt = <<<PROMPT
Available Material Icon names (use EXACTLY these, snake_case, no spaces):
{$validIcons}

Menu: {$menuName}
Submenus: {$submenuList}

Return ONLY this JSON. Icon name MUST be from the list above exactly.

{
  "menu": {
    "name": "Title Case menu name",
    "icon": "snake_case_material_icon"
  },
  "submenus": [
    {
      "name": "Title Case submenu name",
      "icon": "snake_case_material_icon",
      "title": "Short page title",
      "subtitle": "One sentence describing what this page does"
    }
  ]
}

Example — if input was Menu: Maintenance, Submenus: Account Management, Access Level:
{
  "menu": {
    "name": "Maintenance",
    "icon": "build"
  },
  "submenus": [
    {
      "name": "Account Management",
      "icon": "manage_accounts",
      "title": "Account Management",
      "subtitle": "Manage user accounts and statuses"
    },
    {
      "name": "Access Level",
      "icon": "security",
      "title": "Access Level",
      "subtitle": "Configure role-based access and permissions"
    }
  ]
}

Now do the same for: Menu: {$menuName}, Submenus: {$submenuList}
PROMPT;

    $payload = [
        'model'       => 'nvidia/llama-3.1-nemotron-nano-8b-v1',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => 0,
        'top_p'       => 0.9,
        'max_tokens'  => 512,
        'stream'      => false,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Host: integrate.api.nvidia.com',
        'User-Agent: MLHuillier-CLI/1.0',
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

    // Retry up to 2 times on timeout (NIM free tier can be slow to cold-start)
    $response = null;
    $lastCurlErr = 0;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $attempt === 1 ? 120 : 60);
        $response = curl_exec($ch);
        $lastCurlErr = curl_errno($ch);

        if ($lastCurlErr === 0) {
            break; // success
        }
        if ($lastCurlErr !== 28) {
            break; // real error, no point retrying
        }
        if ($attempt < 3) {
            $sleepMs = $attempt * 3000;
            usleep($sleepMs * 1000); // 3s, then 6s backoff
        }
    }

    if ($lastCurlErr !== 0) {
        curl_close($ch);
        return ['error' => 'API_CONNECTION_FAILED', 'curl_err' => $lastCurlErr];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401 || $httpCode === 403) {
        return ['error' => 'INVALID_API_KEY'];
    }

    $decoded = json_decode((string) $response, true);

    if (!isset($decoded['choices'][0]['message']['content'])) {
        return ['error' => 'INVALID_RESPONSE'];
    }

    $raw = trim((string) $decoded['choices'][0]['message']['content']);

    // Strip markdown code fences if AI wrapped the JSON in ```json ... ```
    $raw = (string) preg_replace('/^```(?:json)?\s*/i', '', $raw);
    $raw = (string) preg_replace('/\s*```\s*$/i', '', $raw);
    $raw = trim($raw);

    // Extract the outermost JSON object (from first { to last })
    $start = strpos($raw, '{');
    $end   = strrpos($raw, '}');
    if ($start === false || $end === false || $end <= $start) {
        return ['error' => 'INVALID_JSON_FROM_AI', 'raw' => $raw];
    }
    $raw = substr($raw, $start, $end - $start + 1);

    $aiData = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'INVALID_JSON_FROM_AI', 'raw' => $raw];
    }

    // Validate required fields
    if (!isset($aiData['menu']['name'], $aiData['menu']['icon'], $aiData['submenus'])
        || !is_array($aiData['submenus'])
        || count($aiData['submenus']) === 0
    ) {
        return ['error' => 'MISSING_FIELDS', 'raw' => $raw];
    }

    // Clamp all icon names to real Material Icons (safety net for AI hallucinations)
    $aiData = validateAndFixIcons($aiData);

    return $aiData;
}

// ── AI metadata fallback ──────────────────────────────────────────────────────

/**
 * If AI call fails or is skipped, build a safe default metadata structure
 * so the rest of the script can always proceed.
 *
 * @param  string   $menuName
 * @param  string[] $submenuNames
 * @return array
 */
function buildFallbackMetadata(string $menuName, array $submenuNames): array
{
    $submenus = [];
    foreach ($submenuNames as $name) {
        $submenus[] = [
            'name'     => $name,
            'icon'     => 'question_mark',
            'title'    => $name,
            'subtitle' => 'Edit this description later.',
        ];
    }
    return [
        'menu'     => ['name' => $menuName, 'icon' => 'menu'],
        'submenus' => $submenus,
    ];
}

// ── Sidebar block generator ───────────────────────────────────────────────────

/**
 * Generates the sidebar PHP/HTML block using AI metadata.
 * The AI never touches this — the CLI builds it entirely.
 *
 * @param  string   $menuName      Title Case menu name (from AI or user)
 * @param  string   $menuIcon      Material icon name (from AI)
 * @param  array[]  $submenus      Each: ['name'=>..., 'slug'=>..., 'permission'=>..., 'path'=>...]
 * @return string
 */
function generateSidebarBlock(string $menuName, string $menuIcon, array $submenus): string
{
    $permissionList = implode(', ', array_map(
        fn($s) => "'{$s['permission']}'",
        $submenus
    ));

    $submenuLines = '';
    foreach ($submenus as $s) {
        $submenuLines .= <<<PHP

            <?php if (has_permission('{$s['permission']}')): ?>
            <li class="sidebar__submenu-item"><a href="<?= htmlspecialchars((\$appBaseUrl !== '' ? \$appBaseUrl : '') . '{$s['path']}', ENT_QUOTES, 'UTF-8'); ?>" class="sidebar__submenu-link"><span class="sidebar__submenu-label">{$s['name']}</span></a></li>
            <?php endif; ?>
PHP;
    }

    return <<<PHP

        <?php if (has_menu_access('{$menuName}')): ?>
        <?php if (has_any_permission([{$permissionList}])): ?>
        <li class="sidebar__nav-item has-submenu">
          <button type="button" class="sidebar__nav-link" aria-expanded="false">
            <span class="material-icons sidebar__nav-icon" aria-hidden="true">{$menuIcon}</span>
            <span class="sidebar__nav-label">{$menuName}</span>
            <span class="material-icons sidebar__nav-chev" aria-hidden="true">expand_more</span>
          </button>
          <ul class="sidebar__submenu">{$submenuLines}
          </ul>
        </li>
        <?php endif; ?>
        <?php endif; ?>
PHP;
}

/**
 * Injects the sidebar block into sidebar.php just before </ul></nav>.
 */
function injectIntoSidebar(string $sidebarPath, string $block): bool
{
    if (!is_file($sidebarPath)) {
        return false;
    }

    $content = (string) file_get_contents($sidebarPath);

    $updated = preg_replace(
        '/(<\/ul>\s*<\/nav>)/i',
        $block . "\n      $1",
        $content,
        1
    );

    if ($updated === null || $updated === $content) {
        return false;
    }

    return file_put_contents($sidebarPath, $updated) !== false;
}

// ── File writers ──────────────────────────────────────────────────────────────

function writePhpFile(
    string $path,
    string $menuSlug,
    string $menuTitle,
    string $submenuSlug,
    string $submenuTitle,
    string $headerIcon,
    string $headerPageTitle,
    string $headerSubtitle
): void {
    $submenuLabel    = kebabToTitle($submenuSlug);
    $headerIconEsc   = addslashes($headerIcon);
    $headerTitleEsc  = addslashes($headerPageTitle);
    $headerSubEsc    = addslashes($headerSubtitle);

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
      <?php bp_section_header_html('{$headerIconEsc}', '{$headerTitleEsc}', '{$headerSubEsc}'); ?>

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

// ── Main ──────────────────────────────────────────────────────────────────────

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

$menuSlug  = slug($menuName);
$menuTitle = trim($menuName);

// Step 2 — submenus
$rawSubmenus = ask('Enter Submenu(s) for ' . $menuTitle . ' (comma-separated, e.g. Settings,Extensions,Passwords,Details):');
if ($rawSubmenus === '') {
    err('At least one submenu is required.');
    exit(1);
}

$submenuNames = array_values(array_filter(
    array_map('trim', explode(',', $rawSubmenus))
));
if (count($submenuNames) === 0) {
    err('No valid submenus provided.');
    exit(1);
}

// Step 3 — call NVIDIA NIM AI for metadata (icon, title, subtitle)
out('');
out('Generating metadata via NVIDIA NIM AI...');

$apiKey = getApiKey();
$aiData = callNvidiaNim($apiKey, $menuTitle, $submenuNames);

if (isset($aiData['error'])) {
    if ($aiData['error'] === 'INVALID_API_KEY') {
        err('Error: API Key is INVALID');
        exit(4);
    }
    // Show exactly what went wrong so it's not silent
    out('Warning: AI unavailable — error: ' . $aiData['error']);
    if (!empty($aiData['raw'])) {
        out('AI raw response was:');
        out('  ' . $aiData['raw']);
    }
    out('Falling back to default metadata.');
    $aiData = buildFallbackMetadata($menuTitle, $submenuNames);
}

// Use AI-corrected menu name if it came back clean, otherwise keep what the user typed
$finalMenuName = !empty($aiData['menu']['name']) ? (string) $aiData['menu']['name'] : $menuTitle;
$finalMenuIcon = !empty($aiData['menu']['icon']) ? (string) $aiData['menu']['icon'] : 'menu';

// Step 4 — build normalised submenu list
// Match AI submenus back to user-provided names by position
$normSubmenus = [];
foreach ($submenuNames as $i => $rawName) {
    $aiSub      = $aiData['submenus'][$i] ?? [];
    $finalName  = !empty($aiSub['name'])     ? (string) $aiSub['name']     : $rawName;
    $finalIcon  = !empty($aiSub['icon'])     ? (string) $aiSub['icon']     : 'question_mark';
    $finalTitle = !empty($aiSub['title'])    ? (string) $aiSub['title']    : $finalName;
    $finalSub   = !empty($aiSub['subtitle']) ? (string) $aiSub['subtitle'] : 'Edit this description later.';

    $submenuSlug = slug($finalName);
    $noSpaceMenu = noSpaceSlug($finalMenuName);
    $noSpaceSub  = noSpaceSlug($finalName);

    $normSubmenus[] = [
        'name'       => $finalName,
        'slug'       => $submenuSlug,
        'permission' => $finalMenuName . ' ' . $finalName,
        'path'       => "/src/pages/{$noSpaceMenu}/{$noSpaceSub}/{$noSpaceSub}.php",
        'icon'       => $finalIcon,
        'title'      => $finalTitle,
        'subtitle'   => $finalSub,
    ];
}

// Step 5 — display creation report
out('');
out("{$finalMenuName} has been Created");
foreach ($normSubmenus as $sm) {
    out("  -> {$sm['name']} has been added");
}
out('');

// Step 6 — inject into sidebar.php
$sidebarPath  = $projectRoot
    . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'templates'
    . DIRECTORY_SEPARATOR . 'sidebar.php';

$sidebarBlock = generateSidebarBlock($finalMenuName, $finalMenuIcon, $normSubmenus);

if (injectIntoSidebar($sidebarPath, $sidebarBlock)) {
    out('Sidebar updated successfully.');
} else {
    out('Warning: Could not auto-inject into sidebar.php — please add the block manually.');
    out('');
    out('--- SIDEBAR BLOCK ---');
    out($sidebarBlock);
    out('--- END BLOCK ---');
}

out('');

// Step 7 — template generation prompt
if (!confirm("Do you want me to create the necessary template of the created {$finalMenuName} and Submenu(s)?")) {
    out('Template creation cancelled.');
    exit(0);
}

// Step 8 — generate scaffold files
$menuDir = $projectRoot
    . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'pages'
    . DIRECTORY_SEPARATOR . noSpaceSlug($finalMenuName);

if (!is_dir($menuDir)) {
    if (@mkdir($menuDir, 0755, true)) {
        out('Creating src/pages/' . noSpaceSlug($finalMenuName) . ' ... OK');
    } else {
        err('Failed to create directory: src/pages/' . noSpaceSlug($finalMenuName));
        exit(3);
    }
} else {
    out('Creating src/pages/' . noSpaceSlug($finalMenuName) . ' ... SKIPPED (already exists)');
}

foreach ($normSubmenus as $sm) {
    $submenuDir = $menuDir . DIRECTORY_SEPARATOR . $sm['slug'];

    if (!is_dir($submenuDir)) {
        if (!@mkdir($submenuDir, 0755, true)) {
            err("  Failed to create directory: src/pages/" . noSpaceSlug($finalMenuName) . "/{$sm['slug']}");
            continue;
        }
    }

    writePhpFile(
        $submenuDir . DIRECTORY_SEPARATOR . $sm['slug'] . '.php',
        noSpaceSlug($finalMenuName),
        $finalMenuName,
        $sm['slug'],
        $sm['name'],
        $sm['icon'],
        $sm['title'],
        $sm['subtitle']
    );

    writeCssFile(
        $submenuDir . DIRECTORY_SEPARATOR . $sm['slug'] . '.css',
        $sm['slug']
    );
}

out('');
out('Done. Added to your sidebar:');
out("  -> {$finalMenuName}  [{$finalMenuIcon}]");
foreach ($normSubmenus as $sm) {
    out("      -> {$sm['name']}");
    out("         header: bp_section_header_html('{$sm['icon']}', '{$sm['title']}', '{$sm['subtitle']}')");
}
out('');
exit(0);