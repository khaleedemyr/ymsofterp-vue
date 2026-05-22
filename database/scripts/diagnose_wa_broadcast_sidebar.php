<?php

/**
 * Diagnosa sidebar menu WA Broadcast vs menu CRM lain yang sudah jalan.
 * Jalankan: php database/scripts/diagnose_wa_broadcast_sidebar.php [user_id]
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = isset($argv[1]) ? (int) $argv[1] : null;

$compareCodes = [
    'wa_broadcast',
    'instagram_comments',
    'omnichannel_inbox',
    'omnichannel_teams',
];

echo "\n=== DIAGNOSE WA BROADCAST SIDEBAR ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n\n";

// -------------------------------------------------------------------------
// 1) erp_menu — bandingkan dengan menu CRM lain
// -------------------------------------------------------------------------
echo "--- 1) erp_menu (target vs referensi) ---\n";
$menus = DB::table('erp_menu')
    ->whereIn('code', $compareCodes)
    ->orderBy('code')
    ->get(['id', 'code', 'name', 'parent_id', 'route', 'icon']);

if ($menus->isEmpty()) {
    echo "TIDAK ADA menu dengan code: ".implode(', ', $compareCodes)."\n";
    echo "=> Jalankan insert_wa_broadcast_menu.sql atau migration 2026_05_22_120001\n\n";
} else {
    foreach ($menus as $m) {
        $flag = $m->code === 'wa_broadcast' ? ' << TARGET' : '';
        echo sprintf(
            "  id=%s code=%s parent_id=%s route=%s name=%s%s\n",
            $m->id,
            $m->code,
            $m->parent_id ?? 'NULL',
            $m->route ?? '-',
            $m->name,
            $flag
        );
    }
}

$waMenu = $menus->firstWhere('code', 'wa_broadcast');
if (! $waMenu) {
    echo "\n[FATAL] erp_menu.code = 'wa_broadcast' TIDAK ADA di database.\n";
} else {
    echo "\nAppLayout.vue mengharapkan: code=wa_broadcast, route=/crm/wa-broadcast\n";
    $routeOk = $waMenu->route === '/crm/wa-broadcast';
    echo 'Route match: '.($routeOk ? 'OK' : "SALAH (DB: {$waMenu->route})")."\n";
}

// Duplikat / typo code
$dupes = DB::table('erp_menu')
    ->where('code', 'like', '%broadcast%')
    ->orWhere('code', 'like', '%wa%broadcast%')
    ->get(['id', 'code', 'name', 'route']);
if ($dupes->count() > 1 || ($dupes->isNotEmpty() && ! $waMenu)) {
    echo "\nMenu terkait broadcast/wa di DB:\n";
    foreach ($dupes as $d) {
        echo "  id={$d->id} code={$d->code} route={$d->route}\n";
    }
}

// -------------------------------------------------------------------------
// 2) erp_permission — harus ada action=view untuk sidebar
// -------------------------------------------------------------------------
echo "\n--- 2) erp_permission ---\n";
$perms = DB::table('erp_permission as p')
    ->leftJoin('erp_menu as m', 'm.id', '=', 'p.menu_id')
    ->where(function ($q) use ($compareCodes) {
        $q->whereIn('m.code', $compareCodes)
            ->orWhereIn('p.code', [
                'wa_broadcast_view',
                'wa_broadcast_send',
                'instagram_comments_view',
                'omnichannel_inbox_view',
            ]);
    })
    ->orderBy('m.code')
    ->orderBy('p.action')
    ->get(['p.id', 'p.menu_id', 'p.action', 'p.code', 'm.code as menu_code']);

foreach ($compareCodes as $code) {
    $rows = $perms->where('menu_code', $code);
    echo "\n  Menu [{$code}]:\n";
    if ($rows->isEmpty()) {
        echo "    (tidak ada permission — Role UI centang view/create TIDAK akan tersimpan)\n";
        continue;
    }
    foreach ($rows as $p) {
        echo "    perm_id={$p->id} action={$p->action} code={$p->code} menu_id={$p->menu_id}\n";
    }
    $hasView = $rows->contains(fn ($p) => $p->action === 'view');
    echo '    Sidebar butuh action=view: '.($hasView ? 'ADA' : 'TIDAK ADA')."\n";
}

$waViewPerm = DB::table('erp_permission')->where('code', 'wa_broadcast_view')->first();
$waViewPermWrong = $waMenu
    ? DB::table('erp_permission')->where('menu_id', $waMenu->id)->where('action', 'view')->first()
    : null;

if ($waViewPermWrong && $waViewPermWrong->code === 'wa_broadcast') {
    echo "\n[ERROR] Permission VIEW salah: code='wa_broadcast' (harus 'wa_broadcast_view' seperti instagram_comments_view)\n";
    echo "  => Role sync SQL gagal cari wa_broadcast_view. Jalankan --fix\n";
}
if ($waViewPerm && $waMenu && (int) $waViewPerm->menu_id !== (int) $waMenu->id) {
    echo "\n[ERROR] wa_broadcast_view.menu_id={$waViewPerm->menu_id} != menu.id={$waMenu->id}\n";
}

$viewPermId = $waViewPerm?->id ?? ($waViewPermWrong && $waViewPermWrong->action === 'view' ? $waViewPermWrong->id : null);

// -------------------------------------------------------------------------
// 3) Role yang punya wa_broadcast_view
// -------------------------------------------------------------------------
echo "\n--- 3) erp_role_permission (role dengan permission view wa_broadcast) ---\n";
if ($viewPermId) {
    $roles = DB::table('erp_role_permission as rp')
        ->join('erp_role as r', 'r.id', '=', 'rp.role_id')
        ->where('rp.permission_id', $viewPermId)
        ->get(['r.id', 'r.name']);
    if ($roles->isEmpty()) {
        echo "  TIDAK ADA role yang punya wa_broadcast_view.\n";
        echo "  => Jalankan bagian INSERT erp_role_permission di insert_wa_broadcast_menu.sql\n";
    } else {
        foreach ($roles as $r) {
            echo "  role_id={$r->id} name={$r->name}\n";
        }
    }
} else {
    echo "  wa_broadcast_view belum ada di erp_permission.\n";
}

// -------------------------------------------------------------------------
// 4) User sample — allowedMenus simulation (sama HandleInertiaRequests)
// -------------------------------------------------------------------------
echo "\n--- 4) Simulasi allowedMenus (query = HandleInertiaRequests.php) ---\n";

$sampleUsers = DB::table('users as u')
    ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
    ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
    ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
    ->where('p.code', 'omnichannel_inbox_view')
    ->distinct()
    ->limit(5)
    ->pluck('u.id');

if ($userId) {
    $sampleUsers = collect([$userId]);
}

if ($sampleUsers->isEmpty()) {
    echo "  Tidak ada user dengan omnichannel_inbox_view. Coba: php diagnose_wa_broadcast_sidebar.php <user_id>\n";
} else {
    foreach ($sampleUsers as $uid) {
        $allowedMenus = DB::table('users as u')
            ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
            ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('u.id', $uid)
            ->where('p.action', 'view')
            ->distinct()
            ->pluck('m.code')
            ->toArray();

        $crmInAllowed = array_values(array_intersect($compareCodes, $allowedMenus));
        $hasWa = in_array('wa_broadcast', $allowedMenus, true);

        $email = DB::table('users')->where('id', $uid)->value('email');

        echo "\n  user_id={$uid} email={$email}\n";
        echo '    CRM menus in allowedMenus: '.implode(', ', $crmInAllowed ?: ['(none)'])."\n";
        echo '    wa_broadcast in allowedMenus: '.($hasWa ? 'YES' : 'NO')."\n";

        if (! $hasWa) {
            $roleIds = DB::table('erp_user_role')->where('user_id', $uid)->pluck('role_id');
            echo '    role_ids: '.implode(', ', $roleIds->all())."\n";
            foreach ($roleIds as $rid) {
                $hasPerm = DB::table('erp_role_permission as rp')
                    ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                    ->where('rp.role_id', $rid)
                    ->where('p.code', 'wa_broadcast_view')
                    ->exists();
                echo "      role {$rid} has wa_broadcast_view: ".($hasPerm ? 'yes' : 'no')."\n";
            }
        }
    }
}

// -------------------------------------------------------------------------
// 5) AppLayout hardcoded check
// -------------------------------------------------------------------------
echo "\n--- 5) AppLayout.vue hardcoded entry ---\n";
$layoutPath = $root.'/resources/js/Layouts/AppLayout.vue';
$layout = file_get_contents($layoutPath);
$checks = [
    "code: 'wa_broadcast'" => str_contains($layout, "code: 'wa_broadcast'"),
    "route: '/crm/wa-broadcast'" => str_contains($layout, "route: '/crm/wa-broadcast'"),
    "instagram_comments" => str_contains($layout, "code: 'instagram_comments'"),
];
foreach ($checks as $label => $ok) {
    echo '  '.$label.': '.($ok ? 'OK' : 'MISSING')."\n";
}

// Build assets
$buildHasWa = false;
$buildDir = $root.'/public/build/assets';
if (is_dir($buildDir)) {
    foreach (glob($buildDir.'/AppLayout*.js') ?: [] as $f) {
        if (str_contains((string) file_get_contents($f), 'wa_broadcast')) {
            $buildHasWa = true;
            echo '  public/build AppLayout contains wa_broadcast: OK ('.basename($f).")\n";
            break;
        }
    }
}
if (! $buildHasWa) {
    echo "  public/build AppLayout: wa_broadcast NOT FOUND — jalankan npm run build\n";
}

// -------------------------------------------------------------------------
// 6) Rekomendasi auto-fix (opsional)
// -------------------------------------------------------------------------
echo "\n--- 6) Auto-fix ---\n";
$fix = in_array('--fix', $argv ?? [], true);
if (! $fix) {
    echo "  Tambahkan flag --fix untuk insert menu/permission/role sync otomatis.\n";
    echo "  Contoh: php database/scripts/diagnose_wa_broadcast_sidebar.php 1 --fix\n\n";
    exit(0);
}

echo "  Menjalankan perbaikan...\n";

DB::statement("
    INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at)
    VALUES ('Broadcast WhatsApp', 'wa_broadcast', 138, '/crm/wa-broadcast', 'fa-brands fa-whatsapp', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name), parent_id = VALUES(parent_id), route = VALUES(route),
        icon = VALUES(icon), updated_at = NOW()
");

$menuId = DB::table('erp_menu')->where('code', 'wa_broadcast')->value('id');
foreach ([['view', 'wa_broadcast_view'], ['create', 'wa_broadcast_send']] as [$action, $code]) {
    DB::table('erp_permission')->updateOrInsert(
        ['menu_id' => $menuId, 'action' => $action],
        ['code' => $code, 'updated_at' => now(), 'created_at' => now()]
    );
}

DB::table('erp_permission')
    ->where('menu_id', $menuId)
    ->where('action', 'view')
    ->where('code', 'wa_broadcast')
    ->update(['code' => 'wa_broadcast_view', 'updated_at' => now()]);

// Hapus duplikat view (jika ada 2 baris action=view untuk menu yang sama)
$viewPerms = DB::table('erp_permission')
    ->where('menu_id', $menuId)
    ->where('action', 'view')
    ->orderByRaw("CASE WHEN code = 'wa_broadcast_view' THEN 0 ELSE 1 END")
    ->orderBy('id')
    ->get();
if ($viewPerms->count() > 1) {
    $keep = $viewPerms->first();
    foreach ($viewPerms->skip(1) as $dup) {
        DB::table('erp_role_permission')->where('permission_id', $dup->id)->delete();
        DB::table('erp_permission')->where('id', $dup->id)->delete();
        echo "  Hapus duplikat permission id={$dup->id} code={$dup->code}\n";
    }
}

DB::statement("
    INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
    SELECT rp.role_id, p_new.id
    FROM erp_role_permission rp
    INNER JOIN erp_permission p_old ON p_old.id = rp.permission_id
        AND p_old.code IN ('omnichannel_inbox_view', 'instagram_comments_view')
    INNER JOIN erp_permission p_new ON p_new.code = 'wa_broadcast_view'
");

echo "  Selesai. Logout/login lalu cek sidebar.\n\n";
