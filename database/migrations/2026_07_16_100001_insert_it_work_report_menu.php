<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu + permission IT Work Report.
 * Selaras AppLayout.vue: code it_work_report, route /it-work-reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
            VALUES (
                'IT Work Report',
                'it_work_report',
                184,
                '/it-work-reports',
                'fa-solid fa-laptop-medical',
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

        $menuId = DB::table('erp_menu')->where('code', 'it_work_report')->value('id');
        if (! $menuId) {
            return;
        }

        foreach ([
            ['view', 'it_work_report_view'],
            ['create', 'it_work_report_create'],
            ['update', 'it_work_report_update'],
            ['delete', 'it_work_report_delete'],
        ] as [$action, $code]) {
            DB::table('erp_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'action' => $action],
                ['code' => $code, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Role dengan akses ticketing otomatis dapat view + create
        DB::statement("
            INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
            SELECT rp.role_id, p_new.id
            FROM `erp_role_permission` rp
            INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
                AND p_old.`code` IN ('tickets_view', 'tickets')
            INNER JOIN `erp_permission` p_new ON p_new.`code` IN (
                'it_work_report_view',
                'it_work_report_create',
                'it_work_report_update'
            )
        ");
    }

    public function down(): void
    {
        $menuId = DB::table('erp_menu')->where('code', 'it_work_report')->value('id');
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
