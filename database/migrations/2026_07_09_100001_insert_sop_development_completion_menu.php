<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu + permission SOP Development Completion.
 * Harus selaras dengan AppLayout.vue: code sop_development_completion, route /sop-development-completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
            VALUES (
                'SOP Development Completion',
                'sop_development_completion',
                184,
                '/sop-development-completion',
                'fa-solid fa-file-circle-check',
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

        $menuId = DB::table('erp_menu')->where('code', 'sop_development_completion')->value('id');
        if (! $menuId) {
            return;
        }

        foreach ([
            ['view', 'sop_development_completion_view'],
            ['create', 'sop_development_completion_create'],
        ] as [$action, $code]) {
            DB::table('erp_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'action' => $action],
                ['code' => $code, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Role dengan akses purchase requisition ops otomatis dapat view
        DB::statement("
            INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
            SELECT rp.role_id, p_new.id
            FROM `erp_role_permission` rp
            INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
                AND p_old.`code` IN ('purchase_requisition_ops_view', 'purchase_requisition_ops')
            INNER JOIN `erp_permission` p_new ON p_new.`code` = 'sop_development_completion_view'
        ");
    }

    public function down(): void
    {
        $menuId = DB::table('erp_menu')->where('code', 'sop_development_completion')->value('id');
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
