<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Check if LMS menu exists
    $lmsMenu = DB::table('erp_menu')->where('code', 'lms')->first();
    
    if ($lmsMenu) {
        echo "✅ LMS main menu found (ID: {$lmsMenu->id})\n";
        
        // Check sub-menus
        $subMenus = DB::table('erp_menu')->where('parent_id', $lmsMenu->id)->get();
        echo "📋 Found " . $subMenus->count() . " sub-menus:\n";
        
        foreach ($subMenus as $menu) {
            echo "  - {$menu->name} (code: {$menu->code})\n";
        }
        
        // Check permissions
        $permissions = DB::table('erp_permission')
            ->join('erp_menu', 'erp_permission.menu_id', '=', 'erp_menu.id')
            ->where('erp_menu.parent_id', $lmsMenu->id)
            ->get();
            
        echo "🔐 Found " . $permissions->count() . " permissions:\n";
        
        foreach ($permissions as $perm) {
            echo "  - {$perm->code} ({$perm->action})\n";
        }
        
    } else {
        echo "❌ LMS main menu not found\n";
        echo "💡 You need to run the SQL script: database/sql/insert_lms_menu.sql\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 