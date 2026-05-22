<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu + permission Broadcast WhatsApp (CRM parent_id 138).
 * Harus selaras dengan AppLayout.vue: code wa_broadcast, route /crm/wa-broadcast.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
            VALUES (
                'Broadcast WhatsApp',
                'wa_broadcast',
                138,
                '/crm/wa-broadcast',
                'fa-brands fa-whatsapp',
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`),
                `parent_id` = VALUES(`parent_id`),
                `route` = VALUES(`route`),
                `icon` = VALUES(`icon`),
                `updated_at` = NOW()
        ");

        $menuId = DB::table('erp_menu')->where('code', 'wa_broadcast')->value('id');
        if (! $menuId) {
            return;
        }

        foreach ([
            ['view', 'wa_broadcast_view'],
            ['create', 'wa_broadcast_send'],
        ] as [$action, $code]) {
            DB::table('erp_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'action' => $action],
                ['code' => $code, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Data lama: permission view ter-insert dengan code = 'wa_broadcast' (salah)
        DB::table('erp_permission')
            ->where('menu_id', $menuId)
            ->where('action', 'view')
            ->where('code', 'wa_broadcast')
            ->update(['code' => 'wa_broadcast_view', 'updated_at' => now()]);

        // Role dengan inbox omnichannel / IG comments otomatis dapat view (sidebar)
        DB::statement("
            INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
            SELECT rp.role_id, p_new.id
            FROM `erp_role_permission` rp
            INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
                AND p_old.`code` IN ('omnichannel_inbox_view', 'instagram_comments_view')
            INNER JOIN `erp_permission` p_new ON p_new.`code` = 'wa_broadcast_view'
        ");
    }

    public function down(): void
    {
        $menuId = DB::table('erp_menu')->where('code', 'wa_broadcast')->value('id');
        if ($menuId) {
            $permIds = DB::table('erp_permission')->where('menu_id', $menuId)->pluck('id');
            if ($permIds->isNotEmpty()) {
                DB::table('erp_role_permission')->whereIn('permission_id', $permIds)->delete();
                DB::table('erp_permission')->whereIn('id', $permIds)->delete();
            }
            DB::table('erp_menu')->where('id', $menuId)->delete();
        }
    }
};
