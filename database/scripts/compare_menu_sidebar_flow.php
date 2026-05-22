<?php

/**
 * Bandingkan alur sidebar: menu yang MUNCUL vs wa_broadcast
 * php database/scripts/compare_menu_sidebar_flow.php [user_id]
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = isset($argv[1]) ? (int) $argv[1] : 26;

$working = ['instagram_comments', 'omnichannel_inbox'];
$broken = 'wa_broadcast';

echo "\n========== COMPARE SIDEBAR FLOW ==========\n";
echo "User ID: {$userId}\n\n";

// --- A) erp_menu full row diff ---
echo "=== A) erp_menu (semua kolom) ===\n";
$cols = DB::select('SHOW COLUMNS FROM erp_menu');
$colNames = array_map(fn ($c) => $c->Field, $cols);
echo 'Columns: '.implode(', ', $colNames)."\n\n";

foreach ([...$working, $broken] as $code) {
    $row = DB::table('erp_menu')->where('code', $code)->first();
    echo "--- {$code} ---\n";
    if (! $row) {
        echo "  MISSING\n\n";
        continue;
    }
    foreach ((array) $row as $k => $v) {
        echo "  {$k} = ".(is_null($v) ? 'NULL' : $v)."\n";
    }
    echo "\n";
}

// --- B) erp_permission ---
echo "=== B) erp_permission (per menu) ===\n";
$permCols = array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM erp_permission'));
echo 'Columns: '.implode(', ', $permCols)."\n\n";

foreach ([...$working, $broken] as $code) {
    $menuId = DB::table('erp_menu')->where('code', $code)->value('id');
    echo "--- {$code} (menu_id={$menuId}) ---\n";
    $perms = DB::table('erp_permission')->where('menu_id', $menuId)->orderBy('action')->get();
    foreach ($perms as $p) {
        echo "  id={$p->id} action={$p->action} code={$p->code}\n";
    }
    $view = $perms->firstWhere('action', 'view');
    $expected = $code.'_view';
    if ($view && $view->code !== $expected && ! in_array($code, ['omnichannel_inbox'], true)) {
        echo "  !! convention: expected code '{$expected}', got '{$view->code}'\n";
    }
    if ($code === 'omnichannel_inbox' && $view) {
        echo "  (inbox view code: {$view->code})\n";
    }
    echo "\n";
}

// --- C) AppLayout.vue hardcoded ---
echo "=== C) AppLayout.vue entries ===\n";
$layout = file_get_contents($root.'/resources/js/Layouts/AppLayout.vue');
foreach ([...$working, $broken] as $code) {
    $hasCode = (bool) preg_match("/code:\s*['\"]".preg_quote($code, '/')."['\"]/", $layout);
    preg_match("/code:\s*['\"]".preg_quote($code, '/')."['\"][^}]+route:\s*['\"]([^'\"]+)['\"]/s", $layout, $m);
    $route = $m[1] ?? '?';
    echo "  {$code}: in AppLayout=".($hasCode ? 'YES' : 'NO')." route={$route}\n";
}

// --- D) HandleInertia allowedMenus simulation ---
echo "\n=== D) allowedMenus untuk user {$userId} ===\n";
$allowed = DB::table('users as u')
    ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
    ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
    ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
    ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
    ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
    ->where('u.id', $userId)
    ->where('p.action', 'view')
    ->select('m.code', 'm.id as menu_id', 'p.id as perm_id', 'p.code as perm_code', 'r.id as role_id', 'r.name as role_name')
    ->distinct()
    ->get();

foreach ([...$working, $broken] as $code) {
    $rows = $allowed->where('code', $code);
    echo "  {$code}: ".($rows->isNotEmpty() ? 'IN allowedMenus' : 'NOT IN allowedMenus')."\n";
    foreach ($rows as $r) {
        echo "    role={$r->role_name} perm_code={$r->perm_code} perm_id={$r->perm_id}\n";
    }
}

// --- E) Role UI mapping (menu_id-action) ---
echo "\n=== E) Role Management checkbox key (menu_id-action) ===\n";
foreach ([...$working, $broken] as $code) {
    $menuId = DB::table('erp_menu')->where('code', $code)->value('id');
    $viewPerm = DB::table('erp_permission')->where('menu_id', $menuId)->where('action', 'view')->first();
    echo "  {$code}: checkbox value = {$menuId}-view => ";
    echo ($viewPerm ? "perm_id={$viewPerm->id} code={$viewPerm->code}" : 'NO VIEW PERM')."\n";
}

// --- F) User roles ---
echo "\n=== F) erp_user_role untuk user ===\n";
$roles = DB::table('erp_user_role as ur')
    ->join('erp_role as r', 'r.id', '=', 'ur.role_id')
    ->where('ur.user_id', $userId)
    ->get(['r.id', 'r.name']);
foreach ($roles as $r) {
    echo "  role_id={$r->id} {$r->name}\n";
    foreach ([...$working, $broken] as $code) {
        $menuId = DB::table('erp_menu')->where('code', $code)->value('id');
        $has = DB::table('erp_role_permission as rp')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $r->id)
            ->where('p.menu_id', $menuId)
            ->where('p.action', 'view')
            ->exists();
        echo "    {$code} view on role: ".($has ? 'YES' : 'no')."\n";
    }
}

// --- G) users.id_role legacy? ---
echo "\n=== G) users table role columns ===\n";
$userCols = array_map(fn ($c) => $c->Field, DB::select('SHOW COLUMNS FROM users'));
$roleCols = array_filter($userCols, fn ($c) => stripos($c, 'role') !== false);
echo '  role-related columns: '.implode(', ', $roleCols)."\n";
$user = DB::table('users')->where('id', $userId)->first();
foreach ($roleCols as $c) {
    echo "  {$c} = ".($user->$c ?? 'NULL')."\n";
}

// --- H) Sidebar i18n keys ---
echo "\n=== H) sidebar lang keys ===\n";
foreach (['sidebar-id.js', 'sidebar-en.js'] as $f) {
    $js = file_get_contents($root.'/resources/js/lang/'.$f);
    foreach ([...$working, $broken] as $code) {
        $has = str_contains($js, '"'.$code.'"');
        echo "  {$f} [{$code}]: ".($has ? 'OK' : 'MISSING')."\n";
    }
}

// --- I) Build asset ---
echo "\n=== I) public/build AppLayout ===\n";
foreach (glob($root.'/public/build/assets/AppLayout*.js') ?: [] as $f) {
    foreach ([...$working, $broken] as $code) {
        $in = str_contains(file_get_contents($f), $code);
        if (! $in && $code === $broken) {
            echo '  '.basename($f)." MISSING {$code}\n";
        }
    }
    $hasWa = str_contains(file_get_contents($f), 'wa_broadcast');
    $hasIg = str_contains(file_get_contents($f), 'instagram_comments');
    echo '  '.basename($f).": ig=".($hasIg ? 'Y' : 'n')." wa=".($hasWa ? 'Y' : 'n')."\n";
}

// --- J) Filter logic simulation ---
echo "\n=== J) Vue filter simulation ===\n";
$allowedCodes = $allowed->pluck('code')->unique()->values()->all();
$menus = [
    ['code' => 'omnichannel_inbox'],
    ['code' => 'wa_broadcast'],
    ['code' => 'instagram_comments'],
];
foreach ($menus as $m) {
    $show = ! $m['code'] || in_array($m['code'], $allowedCodes, true);
    echo "  {$m['code']}: ".($show ? 'SHOW' : 'HIDE')."\n";
}

echo "\n=== K) Perbedaan khusus wa_broadcast vs instagram_comments ===\n";
$igMenu = DB::table('erp_menu')->where('code', 'instagram_comments')->first();
$waMenu = DB::table('erp_menu')->where('code', 'wa_broadcast')->first();
if ($igMenu && $waMenu) {
    foreach ($colNames as $col) {
        $a = $igMenu->$col ?? null;
        $b = $waMenu->$col ?? null;
        if ((string) $a !== (string) $b) {
            echo "  menu.{$col}: ig=".json_encode($a)." | wa=".json_encode($b)."\n";
        }
    }
}

echo "\nDone.\n";
