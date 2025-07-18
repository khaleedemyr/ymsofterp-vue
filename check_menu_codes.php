<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking menu codes in database...\n\n";
    
    // Get all menus
    $menus = DB::table('erp_menu')->get();
    
    echo "📋 All menu codes:\n";
    foreach ($menus as $menu) {
        echo "  - {$menu->code} (ID: {$menu->id}, Name: {$menu->name})\n";
    }
    
    // Check LMS menus specifically
    echo "\n📚 LMS menu codes:\n";
    $lmsMenus = $menus->filter(function($menu) {
        return strpos($menu->code, 'lms') === 0;
    });
    
    if ($lmsMenus->count() > 0) {
        foreach ($lmsMenus as $menu) {
            echo "  - {$menu->code} (ID: {$menu->id}, Name: {$menu->name})\n";
        }
    } else {
        echo "  ❌ No LMS menus found\n";
    }
    
    // Check if menu codes match frontend expectations
    echo "\n🔍 Checking if menu codes match frontend expectations:\n";
    $frontendCodes = ['dashboard', 'categories', 'maintenance_order', 'lms-dashboard', 'lms-categories'];
    
    foreach ($frontendCodes as $code) {
        $matches = $menus->filter(function($menu) use ($code) {
            return $menu->code === $code;
        });
        
        if ($matches->count() > 0) {
            echo "  ✅ Found: {$code}\n";
        } else {
            echo "  ❌ Not found: {$code}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 