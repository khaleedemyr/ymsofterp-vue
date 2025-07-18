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
    
    echo "👤 Checking role assignment for user: {$user->nama_lengkap} (ID: {$user->id})\n\n";
    
    // Check if user has any roles assigned
    $userRoles = DB::table('erp_user_role')
        ->join('erp_role', 'erp_user_role.role_id', '=', 'erp_role.id')
        ->where('erp_user_role.user_id', $user->id)
        ->get();
    
    echo "🎭 User roles:\n";
    if ($userRoles->count() > 0) {
        foreach ($userRoles as $role) {
            echo "  - {$role->name} (ID: {$role->id})\n";
        }
    } else {
        echo "  ❌ No roles assigned to user\n";
        echo "  💡 You need to assign a role to this user in erp_user_role table\n";
        exit;
    }
    
    // Check if roles have any permissions
    echo "\n🔐 Role permissions:\n";
    foreach ($userRoles as $role) {
        echo "  Role: {$role->name}\n";
        
        $rolePermissions = DB::table('erp_role_permission')
            ->join('erp_permission', 'erp_role_permission.permission_id', '=', 'erp_permission.id')
            ->join('erp_menu', 'erp_permission.menu_id', '=', 'erp_menu.id')
            ->where('erp_role_permission.role_id', $role->id)
            ->where('erp_permission.action', 'view')
            ->get();
        
        if ($rolePermissions->count() > 0) {
            foreach ($rolePermissions as $perm) {
                echo "    - {$perm->code} ({$perm->action})\n";
            }
        } else {
            echo "    ❌ No permissions found for this role\n";
        }
        
        // Check specifically for LMS permissions
        $lmsPermissions = $rolePermissions->filter(function($perm) {
            return strpos($perm->code, 'lms') === 0;
        });
        
        if ($lmsPermissions->count() > 0) {
            echo "    📚 LMS permissions:\n";
            foreach ($lmsPermissions as $perm) {
                echo "      ✅ {$perm->code} ({$perm->action})\n";
            }
        } else {
            echo "    📚 No LMS permissions found for this role\n";
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 