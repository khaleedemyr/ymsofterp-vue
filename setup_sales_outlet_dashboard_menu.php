<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🚀 Setting up Sales Outlet Dashboard Menu...\n\n";
    
    // Check if menu already exists
    $existingMenu = DB::table('erp_menu')->where('code', 'sales_outlet_dashboard')->first();
    
    if ($existingMenu) {
        echo "⚠️  Menu 'Sales Outlet Dashboard' already exists with ID: {$existingMenu->id}\n";
        echo "Skipping menu insertion...\n\n";
        
        $menuId = $existingMenu->id;
    } else {
        // Insert menu
        echo "📝 Inserting Sales Outlet Dashboard menu...\n";
        
        $menuId = DB::table('erp_menu')->insertGetId([
            'name' => 'Sales Outlet Dashboard',
            'code' => 'sales_outlet_dashboard',
            'parent_id' => 1,
            'route' => '/sales-outlet-dashboard',
            'icon' => 'fa-solid fa-chart-line',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        echo "✅ Menu inserted successfully with ID: {$menuId}\n\n";
    }
    
    // Check existing permissions
    $existingPermissions = DB::table('erp_permission')
        ->where('menu_id', $menuId)
        ->pluck('code')
        ->toArray();
    
    echo "📋 Existing permissions: " . implode(', ', $existingPermissions) . "\n\n";
    
    // Define permissions to insert
    $permissions = [
        ['action' => 'view', 'code' => 'sales_outlet_dashboard_view'],
        ['action' => 'create', 'code' => 'sales_outlet_dashboard_create'],
        ['action' => 'update', 'code' => 'sales_outlet_dashboard_update'],
        ['action' => 'delete', 'code' => 'sales_outlet_dashboard_delete'],
        ['action' => 'view', 'code' => 'sales_outlet_dashboard_export'] // Export permission
    ];
    
    $insertedCount = 0;
    $skippedCount = 0;
    
    foreach ($permissions as $permission) {
        if (in_array($permission['code'], $existingPermissions)) {
            echo "⚠️  Permission '{$permission['code']}' already exists, skipping...\n";
            $skippedCount++;
        } else {
            DB::table('erp_permission')->insert([
                'menu_id' => $menuId,
                'action' => $permission['action'],
                'code' => $permission['code'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            echo "✅ Permission '{$permission['code']}' inserted successfully\n";
            $insertedCount++;
        }
    }
    
    echo "\n📊 Summary:\n";
    echo "   - Menu ID: {$menuId}\n";
    echo "   - Permissions inserted: {$insertedCount}\n";
    echo "   - Permissions skipped: {$skippedCount}\n";
    echo "   - Total permissions: " . count($permissions) . "\n\n";
    
    // Verify the setup
    echo "🔍 Verifying setup...\n";
    
    $menu = DB::table('erp_menu')->where('id', $menuId)->first();
    $permissions = DB::table('erp_permission')->where('menu_id', $menuId)->get();
    
    echo "✅ Menu: {$menu->name} (Code: {$menu->code})\n";
    echo "✅ Route: {$menu->route}\n";
    echo "✅ Icon: {$menu->icon}\n";
    echo "✅ Parent ID: {$menu->parent_id}\n";
    echo "✅ Permissions count: {$permissions->count()}\n\n";
    
    echo "🎉 Sales Outlet Dashboard menu setup completed successfully!\n";
    echo "\n📝 Next steps:\n";
    echo "   1. Assign permissions to appropriate user roles\n";
    echo "   2. Test the dashboard access\n";
    echo "   3. Configure user permissions as needed\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
