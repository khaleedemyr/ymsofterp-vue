<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking table structure...\n\n";
    
    // Check available tables
    $tables = DB::select('SHOW TABLES');
    echo "📋 Available tables:\n";
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if (strpos($tableName, 'role') !== false || strpos($tableName, 'permission') !== false || strpos($tableName, 'menu') !== false) {
            echo "  - {$tableName}\n";
        }
    }
    
    echo "\n🔐 Checking permission tables:\n";
    
    // Check if role_permissions table exists
    $rolePermissionsExists = DB::select("SHOW TABLES LIKE 'role_permissions'");
    if (empty($rolePermissionsExists)) {
        echo "  ❌ role_permissions table not found\n";
        
        // Check for alternative table names
        $alternativeTables = ['user_permissions', 'role_has_permissions', 'permission_role'];
        foreach ($alternativeTables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (!empty($exists)) {
                echo "  ✅ Found alternative table: {$table}\n";
            }
        }
    } else {
        echo "  ✅ role_permissions table exists\n";
    }
    
    // Check user_roles table
    $userRolesExists = DB::select("SHOW TABLES LIKE 'user_roles'");
    if (empty($userRolesExists)) {
        echo "  ❌ user_roles table not found\n";
        
        // Check for alternative table names
        $alternativeTables = ['role_user', 'user_has_roles'];
        foreach ($alternativeTables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (!empty($exists)) {
                echo "  ✅ Found alternative table: {$table}\n";
            }
        }
    } else {
        echo "  ✅ user_roles table exists\n";
    }
    
    // Check erp_permission table
    $erpPermissionExists = DB::select("SHOW TABLES LIKE 'erp_permission'");
    if (empty($erpPermissionExists)) {
        echo "  ❌ erp_permission table not found\n";
    } else {
        echo "  ✅ erp_permission table exists\n";
    }
    
    // Check erp_menu table
    $erpMenuExists = DB::select("SHOW TABLES LIKE 'erp_menu'");
    if (empty($erpMenuExists)) {
        echo "  ❌ erp_menu table not found\n";
    } else {
        echo "  ✅ erp_menu table exists\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 