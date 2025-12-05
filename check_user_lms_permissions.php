<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Get current user (you can change this to check specific user)
    $user = DB::table('users')->first();
    
    if (!$user) {
        echo "❌ No user found\n";
        exit;
    }
    
    echo "👤 Checking permissions for user: {$user->nama_lengkap} (ID: {$user->id})\n\n";
    
    // Get user's allowed menus (same logic as HandleInertiaRequests)
    $allowedMenus = DB::table('users as u')
        ->join('erp_user_role as ur', 'ur.user_id', '=', 'u.id')
        ->join('erp_role as r', 'ur.role_id', '=', 'r.id')
        ->join('erp_role_permission as rp', 'rp.role_id', '=', 'r.id')
        ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
        ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
        ->where('u.id', $user->id)
        ->where('p.action', 'view')
        ->distinct()
        ->pluck('m.code')
        ->toArray();
    
    echo "📋 Allowed menus for user:\n";
    if (count($allowedMenus) > 0) {
        foreach ($allowedMenus as $menu) {
            echo "  - {$menu}\n";
        }
    } else {
        echo "  ❌ No menus allowed\n";
    }
    
    // Check specifically for LMS menus
    echo "\n📚 LMS menus in allowedMenus:\n";
    $lmsMenus = array_filter($allowedMenus, function($menu) {
        return strpos($menu, 'lms') === 0;
    });
    
    if (count($lmsMenus) > 0) {
        foreach ($lmsMenus as $menu) {
            echo "  ✅ {$menu}\n";
        }
    } else {
        echo "  ❌ No LMS menus found in allowedMenus\n";
    }
    
    // Check if main LMS menu exists
    echo "\n🔍 Checking main LMS menu:\n";
    if (in_array('lms', $allowedMenus)) {
        echo "  ✅ Main LMS menu (lms) found\n";
    } else {
        echo "  ❌ Main LMS menu (lms) not found\n";
    }
    
    // Check specific LMS sub-menus
    $lmsSubMenus = ['lms-dashboard', 'lms-categories', 'lms-courses', 'lms-lessons', 'lms-enrollments', 'lms-quizzes', 'lms-assignments', 'lms-certificates', 'lms-discussions', 'lms-reports'];
    
    echo "\n🔍 Checking LMS sub-menus:\n";
    foreach ($lmsSubMenus as $subMenu) {
        if (in_array($subMenu, $allowedMenus)) {
            echo "  ✅ {$subMenu}\n";
        } else {
            echo "  ❌ {$subMenu}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 