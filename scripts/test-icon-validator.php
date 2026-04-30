<?php
// Quick validator unit test

const VALID_MATERIAL_ICONS = [
    'home','dashboard','menu','settings','person','people','manage_accounts',
    'account_circle','badge','category','inventory','storage','backup',
    'cloud_upload','cloud_download','upload','download','file_upload',
    'description','assignment','fact_check','task','checklist','rule',
    'build','construction','engineering','handyman','cleaning_services',
    'terminal','code','dns','api','integration_instructions',
    'security','lock','lock_open','shield','verified_user','vpn_key',
    'visibility','visibility_off','preview','monitor_heart','sensors',
    'analytics','bar_chart','pie_chart','show_chart','timeline',
    'notifications','notifications_active','mail','sms','chat','comment',
    'forum','message','send','inbox','schedule','calendar_month','event',
    'today','date_range','business','store','point_of_sale','receipt_long',
    'payments','attach_money','price_check','currency_exchange','account_balance',
    'trending_up','trending_down','insights','monitoring','speed',
    'support_agent','help','help_center','contact_support','language',
    'translate','globe','public','location_on','my_location',
    'map','explore','pin_drop','travel_explore','restaurant',
    'local_shipping','flight','train','directions_car','two_wheeler',
    'medical_services','health_and_safety','vaccines','healing','psychology',
    'school','library_books','auto_stories','history_edu','science',
    'computer','devices','laptop','smartphone','tablet','tv',
    'video_library','photo_library','music_note','audiotrack','mic',
    'movie','live_tv','videocam','camera_alt','image',
    'edit','edit_note','draw','brush','format_paint',
    'print','print_disabled','content_copy','content_cut','content_paste',
    'delete','delete_forever','restore_from_trash','archive','unarchive',
    'filter_list','search','find_in_page','sort','swap_vert','filter_alt',
    'tune','settings_applications','app_settings_alt','admin_panel_settings',
    'refresh','sync','loop','autorenew','update',
    'power','power_settings_new','restart_alt','save','save_alt',
    'file_present','folder','folder_open','drive_file_move',
    'share','ios_share','link','link_off','qr_code','qr_code_2',
    'fingerprint','id_card','badge_certified',
    'workspace_premium','verified','stars','star','star_border',
    'favorite','favorite_border','thumb_up','thumb_down','feedback',
    'flag','report','gavel','policy','privacy_tip',
    'warning','error','error_outline','info','lightbulb',
    'tips_and_updates','auto_fix','auto_awesome','palette','color_lens',
    'texture','style','gradient','notifications_none','addon','pending',
    'pending_actions','hourglass_empty','timer','watch_later',
    'wifi','wifi_off','dark_mode','light_mode','cancel','close',
    'check','check_circle','add_task','alt_route','commute','local_offer',
    'sell','shopping_cart','shopping_bag','redeem','card_giftcard','loyalty',
    'contact_page','perm_identity','supervisor_account','manage_search',
    'camera','videocam_off','mic_off','volume_up','stop','play_arrow','pause',
    'skip_next','skip_previous','fast_forward','fast_rewind',
    'replay','shuffle','repeat','repeat_one','local_dining',
];

const ICON_FALLBACK_MAP = [
    'food'=>'restaurant','pizza'=>'restaurant','meal'=>'restaurant','eat'=>'restaurant',
    'user'=>'person','profile'=>'account_circle','account'=>'manage_accounts',
    'setting'=>'settings','config'=>'settings_applications','preference'=>'tune',
    'report'=>'description','summary'=>'summarize','document'=>'description',
    'analytics'=>'analytics','chart'=>'bar_chart','graph'=>'show_chart',
    'inventory'=>'inventory','stock'=>'inventory','product'=>'inventory',
    'backup'=>'backup','restore'=>'restore_from_trash','export'=>'upload_file',
    'import'=>'download_for_offline','upload'=>'cloud_upload','download'=>'cloud_download',
    'security'=>'security','permission'=>'lock','access'=>'vpn_key',
    'notification'=>'notifications','alert'=>'notifications_active','bell'=>'notifications',
    'customer'=>'support_agent','client'=>'contact_page',
    'image'=>'image','photo'=>'photo_library','gallery'=>'photo_library',
    'video'=>'videocam','media'=>'video_library','music'=>'music_note','audio'=>'audiotrack',
    'edit'=>'edit','write'=>'draw','trash'=>'delete','delete'=>'delete_forever',
    'search'=>'search','find'=>'find_in_page','filter'=>'filter_list',
    'approval'=>'check_circle','verify'=>'verified','done'=>'check',
    'add'=>'add_box','create'=>'add_circle','new'=>'add_task',
    'help'=>'help','question'=>'help_center','faq'=>'contact_support',
    'print'=>'print','copy'=>'content_copy','paste'=>'content_paste',
    'refresh'=>'refresh','reload'=>'sync','update'=>'update',
    'home'=>'home','dashboard'=>'dashboard','menu'=>'menu',
    'maintenance'=>'build','repair'=>'construction','tools'=>'handyman',
    'employee'=>'badge','staff'=>'supervisor_account',
    'order'=>'point_of_sale','sales'=>'trending_up','purchase'=>'sell',
    'log'=>'fact_check','audit'=>'rule','history'=>'history_edu',
    'database'=>'storage','server'=>'dns','api'=>'api',
    'map'=>'map','location'=>'location_on','address'=>'pin_drop',
    'calendar'=>'calendar_month','schedule'=>'schedule','event'=>'event',
    'mail'=>'mail','email'=>'mail','inbox'=>'inbox','message'=>'message',
    'chat'=>'chat','comment'=>'comment','feedback'=>'feedback',
    'reject'=>'cancel','block'=>'block','deny'=>'close',
    'sort'=>'sort','arrange'=>'swap_vert',
    'remove'=>'remove_circle','items'=>'category','category'=>'category',
    'cuisine'=>'restaurant','ingredient'=>'inventory',
    'history_icon'=>null,'profile_icon_that_does_not_exist'=>null,
];

function iconFallback(string $rawIcon, string $relatedName = ''): string
{
    $raw = strtolower(preg_replace('/[-_\s]+/', '', $rawIcon));
    $rel = strtolower(preg_replace('/[-_\s]+/', '', $relatedName));

    foreach (ICON_FALLBACK_MAP as $keyword => $icon) {
        if ($icon === null) {
            continue; // skip entries that should fall through to question_mark
        }
        if (str_contains($raw, $keyword) || str_contains($rel, $keyword)) {
            return $icon;
        }
    }
    return 'question_mark';
}

function validateAndFixIcons(array $aiData): array
{
    $menuIcon = $aiData['menu']['icon'] ?? '';
    $menuName = $aiData['menu']['name'] ?? '';
    if (!in_array($menuIcon, VALID_MATERIAL_ICONS, true)) {
        $aiData['menu']['icon'] = iconFallback($menuIcon, $menuName);
    }
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

echo "\n=== Icon Validator Tests ===\n\n";

$tests = [
    [
        'desc'  => "Pizza menu, AI returns 'pizza' icon",
        'input' => [
            'menu'     => ['name' => 'Pizza', 'icon' => 'pizza'],
            'submenus' => [['name' => 'Ingredients', 'icon' => 'restaurant']],
        ],
        'expect_menu'  => 'restaurant',
        'expect_subs'  => ['restaurant'],
    ],
    [
        'desc'  => "Good values (maintenance, build) — should stay same",
        'input' => [
            'menu'     => ['name' => 'Maintenance', 'icon' => 'build'],
            'submenus' => [['name' => 'Account Mgmt', 'icon' => 'manage_accounts']],
        ],
        'expect_menu'  => 'build',
        'expect_subs'  => ['manage_accounts'],
    ],
    [
        'desc'  => "Hallucinated profile_icon_that_does_not_exist",
        'input' => [
            'menu'     => ['name' => 'Settings', 'icon' => 'settings'],
            'submenus' => [['name' => 'Profile', 'icon' => 'profile_icon_that_does_not_exist']],
        ],
        'expect_menu'  => 'settings',
        'expect_subs'  => ['account_circle'],
    ],
    [
        'desc'  => "Food submenu with hallucinated 'food_items'",
        'input' => [
            'menu'     => ['name' => 'Food', 'icon' => 'local_dining'],
            'submenus' => [
                ['name' => 'Menu Items', 'icon' => 'food_items'],
                ['name' => 'Categories', 'icon' => 'cuisine_category'],
            ],
        ],
        'expect_menu'  => 'local_dining',
        'expect_subs'  => ['restaurant', 'category'],  // category is a real Material icon
    ],
    [
        'desc'  => "Completely fake icon with no related name hint",
        'input' => [
            'menu'     => ['name' => 'Mystery', 'icon' => 'xyz_totally_fake'],
            'submenus' => [['name' => 'Null Item', 'icon' => 'fake12345']],
        ],
        'expect_menu'  => 'question_mark',
        'expect_subs'  => ['question_mark'],
    ],
];

$allPass = true;
foreach ($tests as $i => $t) {
    $after = validateAndFixIcons($t['input']);
    $menuOk = $after['menu']['icon'] === $t['expect_menu'];
    $subsOk = true;
    foreach ($t['expect_subs'] as $j => $exp) {
        if (($after['submenus'][$j]['icon'] ?? '') !== $exp) {
            $subsOk = false;
        }
    }
    $pass = $menuOk && $subsOk;
    $mark = $pass ? '[PASS]' : '[FAIL]';
    if (!$pass) { $allPass = false; }
    echo "$mark Test {$i}: {$t['desc']}\n";
    echo "       menu:  '{$t['input']['menu']['icon']}' -> '{$after['menu']['icon']}'";
    echo $menuOk ? " (ok)\n" : " (EXPECTED '{$t['expect_menu']}')\n";
    foreach ($after['submenus'] as $j => $sm) {
        $exp = $t['expect_subs'][$j];
        $ok  = $sm['icon'] === $exp;
        echo "       sub[$j]: '{$t['input']['submenus'][$j]['icon']}' -> '{$sm['icon']}'";
        echo $ok ? " (ok)\n" : " (EXPECTED '$exp')\n";
    }
}
echo "\n" . ($allPass ? "All tests PASSED.\n" : "Some tests FAILED.\n");