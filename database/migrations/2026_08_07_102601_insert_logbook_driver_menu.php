<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu + permission Logbook Driver.
 * Selaras AppLayout.vue: code logbook_driver, route /logbook-drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
            VALUES (
                'Logbook Driver',
                'logbook_driver',
                6,
                '/logbook-drivers',
                'fa-solid fa-truck',
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

        $menuId = DB::table('erp_menu')->where('code', 'logbook_driver')->value('id');
        if (! $menuId) {
            return;
        }

        foreach ([
            ['view', 'logbook_driver_view'],
            ['create', 'logbook_driver_create'],
            ['update', 'logbook_driver_update'],
            ['delete', 'logbook_driver_delete'],
        ] as [$action, $code]) {
            DB::table('erp_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'action' => $action],
                ['code' => $code, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Role dengan akses Delivery Order otomatis dapat view/create/update
        DB::statement("
            INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
            SELECT rp.role_id, p_new.id
            FROM `erp_role_permission` rp
            INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
                AND p_old.`code` IN ('delivery_order', 'delivery_orders', 'delivery_order_view')
            INNER JOIN `erp_permission` p_new ON p_new.`code` IN (
                'logbook_driver_view',
                'logbook_driver_create',
                'logbook_driver_update'
            )
        ");
    }

    public function down(): void
    {
        $menuId = DB::table('erp_menu')->where('code', 'logbook_driver')->value('id');
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
