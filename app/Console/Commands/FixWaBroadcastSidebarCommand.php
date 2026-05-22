<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWaBroadcastSidebarCommand extends Command
{
    protected $signature = 'wa-broadcast:fix-sidebar {user_id? : Cek allowedMenus untuk user ini setelah perbaikan}';

    protected $description = 'Perbaiki menu/permission/role Broadcast WA agar muncul di sidebar';

    public function handle(): int
    {
        $this->info('Memperbaiki menu Broadcast WhatsApp...');

        DB::statement("
            INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at)
            VALUES ('Broadcast WhatsApp', 'wa_broadcast', 138, '/crm/wa-broadcast', 'fa-brands fa-whatsapp', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name), parent_id = VALUES(parent_id), route = VALUES(route),
                icon = VALUES(icon), updated_at = NOW()
        ");

        $menuId = DB::table('erp_menu')->where('code', 'wa_broadcast')->value('id');
        if (! $menuId) {
            $this->error('erp_menu wa_broadcast tidak ditemukan.');

            return self::FAILURE;
        }

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

        $viewPerms = DB::table('erp_permission')
            ->where('menu_id', $menuId)
            ->where('action', 'view')
            ->orderBy('id')
            ->get();
        $keep = $viewPerms->first();
        foreach ($viewPerms->skip(1) as $dup) {
            DB::table('erp_role_permission')->where('permission_id', $dup->id)->delete();
            DB::table('erp_permission')->where('id', $dup->id)->delete();
            $this->warn("Hapus duplikat permission id={$dup->id}");
        }

        $viewPermId = $keep?->id ?? DB::table('erp_permission')
            ->where('menu_id', $menuId)
            ->where('code', 'wa_broadcast_view')
            ->value('id');

        if ($viewPermId) {
            DB::statement("
                INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
                SELECT rp.role_id, ?
                FROM erp_role_permission rp
                INNER JOIN erp_permission p_old ON p_old.id = rp.permission_id
                    AND p_old.code IN ('omnichannel_inbox_view', 'instagram_comments_view')
            ", [$viewPermId]);
        }

        $this->info('Database OK. Jalankan: npm run build && deploy public/build');
        $this->info('Lalu logout/login ERP.');

        $userId = $this->argument('user_id');
        if ($userId) {
            $allowed = DB::table('users as u')
                ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
                ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
                ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
                ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
                ->where('u.id', $userId)
                ->where('p.action', 'view')
                ->distinct()
                ->pluck('m.code');

            $has = $allowed->contains('wa_broadcast');
            $this->line("user_id={$userId} wa_broadcast in allowedMenus: ".($has ? 'YES' : 'NO'));
            if (! $has) {
                $this->warn('Role user belum punya wa_broadcast_view — centang View di Role Management untuk menu Broadcast WhatsApp.');
            }
        }

        return self::SUCCESS;
    }
}
