<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RetailNonFoodPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cari menu_id untuk Retail Non Food (atau buat baru jika belum ada)
        $menuId = DB::table('erp_menu')->where('name', 'Retail Non Food')->value('id');
        
        if (!$menuId) {
            $menuId = DB::table('erp_menu')->insertGetId([
                'name' => 'Retail Non Food',
                'route' => '/retail-non-food',
                'icon' => 'fa-solid fa-shopping-bag',
                'parent_id' => null,
                'order' => 10,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insert permissions
        $permissions = [
            [
                'menu_id' => $menuId,
                'action' => 'view',
                'code' => 'view-retail-non-food',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'create',
                'code' => 'create-retail-non-food',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'edit',
                'code' => 'edit-retail-non-food',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'delete',
                'code' => 'delete-retail-non-food',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('erp_permission')->updateOrInsert(
                ['code' => $permission['code']],
                $permission
            );
        }

        // Assign permissions to admin role (role_id = 1)
        $adminRoleId = 1;
        $permissionIds = DB::table('erp_permission')
            ->whereIn('code', ['view-retail-non-food', 'create-retail-non-food', 'edit-retail-non-food', 'delete-retail-non-food'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('erp_role_permission')->updateOrInsert(
                [
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
} 