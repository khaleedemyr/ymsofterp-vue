<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing CRM menus and permissions
        $this->clearExistingCrmData();

        // Insert CRM parent menu
        $crmParentId = DB::table('erp_menu')->insertGetId([
            'name' => 'CRM',
            'code' => 'crm',
            'parent_id' => null,
            'route' => '#',
            'icon' => 'fa-solid fa-handshake',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert CRM sub-menus
        $crmMenus = [
            [
                'name' => 'Data Member',
                'code' => 'crm_members',
                'parent_id' => $crmParentId,
                'route' => '/members',
                'icon' => 'fa-solid fa-users',
            ],
            [
                'name' => 'Dashboard CRM',
                'code' => 'crm_dashboard',
                'parent_id' => $crmParentId,
                'route' => '/crm/dashboard',
                'icon' => 'fa-solid fa-chart-line',
            ],
            [
                'name' => 'Customer Analytics',
                'code' => 'crm_analytics',
                'parent_id' => $crmParentId,
                'route' => '/crm/analytics',
                'icon' => 'fa-solid fa-chart-pie',
            ],
            [
                'name' => 'Member Reports',
                'code' => 'crm_reports',
                'parent_id' => $crmParentId,
                'route' => '/crm/member-reports',
                'icon' => 'fa-solid fa-file-lines',
            ],
            [
                'name' => 'Point Management',
                'code' => 'crm_point_management',
                'parent_id' => $crmParentId,
                'route' => '/crm/point-management',
                'icon' => 'fa-solid fa-coins',
            ],
        ];

        $menuIds = [];
        foreach ($crmMenus as $menu) {
            $menuIds[$menu['code']] = DB::table('erp_menu')->insertGetId([
                'name' => $menu['name'],
                'code' => $menu['code'],
                'parent_id' => $menu['parent_id'],
                'route' => $menu['route'],
                'icon' => $menu['icon'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insert permissions
        $permissions = [
            // Data Member permissions
            [
                'menu_id' => $menuIds['crm_members'],
                'action' => 'view',
                'code' => 'crm_members_view',
            ],
            [
                'menu_id' => $menuIds['crm_members'],
                'action' => 'create',
                'code' => 'crm_members_create',
            ],
            [
                'menu_id' => $menuIds['crm_members'],
                'action' => 'update',
                'code' => 'crm_members_update',
            ],
            [
                'menu_id' => $menuIds['crm_members'],
                'action' => 'delete',
                'code' => 'crm_members_delete',
            ],

            // Dashboard CRM permissions
            [
                'menu_id' => $menuIds['crm_dashboard'],
                'action' => 'view',
                'code' => 'crm_dashboard_view',
            ],

            // Customer Analytics permissions
            [
                'menu_id' => $menuIds['crm_analytics'],
                'action' => 'view',
                'code' => 'crm_analytics_view',
            ],

            // Member Reports permissions
            [
                'menu_id' => $menuIds['crm_reports'],
                'action' => 'view',
                'code' => 'crm_reports_view',
            ],
            [
                'menu_id' => $menuIds['crm_reports'],
                'action' => 'create',
                'code' => 'crm_reports_create',
            ],

            // Point Management permissions
            [
                'menu_id' => $menuIds['crm_point_management'],
                'action' => 'view',
                'code' => 'crm_point_management_view',
            ],
            [
                'menu_id' => $menuIds['crm_point_management'],
                'action' => 'create',
                'code' => 'crm_point_management_create',
            ],
            [
                'menu_id' => $menuIds['crm_point_management'],
                'action' => 'delete',
                'code' => 'crm_point_management_delete',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('erp_permission')->insert([
                'menu_id' => $permission['menu_id'],
                'action' => $permission['action'],
                'code' => $permission['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('CRM menus and permissions seeded successfully!');
        
        // Display summary
        $this->displaySummary();
    }

    /**
     * Clear existing CRM data
     */
    private function clearExistingCrmData(): void
    {
        // Get CRM menu IDs
        $crmMenuIds = DB::table('erp_menu')
            ->where('code', 'like', 'crm%')
            ->pluck('id')
            ->toArray();

        if (!empty($crmMenuIds)) {
            // Delete permissions first
            DB::table('erp_permission')
                ->whereIn('menu_id', $crmMenuIds)
                ->delete();

            // Delete menus
            DB::table('erp_menu')
                ->whereIn('id', $crmMenuIds)
                ->delete();

            $this->command->info('Cleared existing CRM data.');
        }
    }

    /**
     * Display summary of inserted data
     */
    private function displaySummary(): void
    {
        $menus = DB::table('erp_menu')
            ->where('code', 'like', 'crm%')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        $permissions = DB::table('erp_permission')
            ->join('erp_menu', 'erp_permission.menu_id', '=', 'erp_menu.id')
            ->where('erp_menu.code', 'like', 'crm%')
            ->select('erp_menu.name as menu_name', 'erp_permission.action', 'erp_permission.code')
            ->orderBy('erp_menu.name')
            ->orderBy('erp_permission.action')
            ->get();

        $this->command->info("\n=== CRM Menu Summary ===");
        $this->command->info("Total menus inserted: " . $menus->count());
        $this->command->info("Total permissions inserted: " . $permissions->count());

        $this->command->info("\n=== Menus ===");
        foreach ($menus as $menu) {
            $parent = $menu->parent_id ? ' (Child)' : ' (Parent)';
            $this->command->info("- {$menu->name} ({$menu->code}){$parent}");
        }

        $this->command->info("\n=== Permissions ===");
        foreach ($permissions as $permission) {
            $this->command->info("- {$permission->menu_name}: {$permission->action} ({$permission->code})");
        }
    }
} 