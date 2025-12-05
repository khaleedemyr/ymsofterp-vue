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
    
    // Get user roles using erp_user_role table
    $userRoles = DB::table('erp_user_role')
        ->join('erp_role', 'erp_user_role.role_id', '=', 'erp_role.id')
        ->where('erp_user_role.user_id', $user->id)
        ->get();
        
    echo "🎭 User Roles:\n";
    if ($userRoles->count() > 0) {
        foreach ($userRoles as $role) {
            echo "  - {$role->name} (ID: {$role->id})\n";
        }
    } else {
        echo "  ❌ No roles assigned\n";
    }
    
    echo "\n🔐 User Permissions:\n";
    
    // Get user permissions through roles using erp_role_permission table
    $permissions = DB::table('erp_role_permission')
        ->join('erp_permission', 'erp_role_permission.permission_id', '=', 'erp_permission.id')
        ->join('erp_user_role', 'erp_role_permission.role_id', '=', 'erp_user_role.role_id')
        ->where('erp_user_role.user_id', $user->id)
        ->get();
        
    if ($permissions->count() > 0) {
        foreach ($permissions as $perm) {
            echo "  - {$perm->code} ({$perm->action})\n";
        }
    } else {
        echo "  ❌ No permissions found\n";
    }
    
    // Check specifically for LMS permissions
    echo "\n📚 LMS Permissions:\n";
    $lmsPermissions = $permissions->filter(function($perm) {
        return strpos($perm->code, 'lms-') === 0;
    });
    
    if ($lmsPermissions->count() > 0) {
        foreach ($lmsPermissions as $perm) {
            echo "  ✅ {$perm->code} ({$perm->action})\n";
        }
    } else {
        echo "  ❌ No LMS permissions found\n";
        echo "  💡 You need to assign LMS permissions to user roles\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 