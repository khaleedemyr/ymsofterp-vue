<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebProfileMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert Web Profile menu with parent_id = 8 (Sales & Marketing)
        $menuId = DB::table('erp_menu')->insertGetId([
            'name' => 'Web Profile',
            'code' => 'web_profile',
            'parent_id' => 8,
            'route' => '/web-profile',
            'icon' => 'fa-solid fa-globe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert permissions for Web Profile
        $permissions = [
            [
                'menu_id' => $menuId,
                'action' => 'view',
                'code' => 'web_profile_view',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'create',
                'code' => 'web_profile_create',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'update',
                'code' => 'web_profile_update',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $menuId,
                'action' => 'delete',
                'code' => 'web_profile_delete',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('erp_permission')->insert($permissions);

        $this->command->info('Web Profile menu and permissions seeded successfully!');
    }
}

