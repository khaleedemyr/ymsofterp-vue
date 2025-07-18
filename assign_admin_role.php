<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔧 Assigning Administrator role to user...\n\n";
    
    // Get user
    $user = DB::table('users')->where('id', 1)->first();
    if (!$user) {
        echo "❌ User with ID 1 not found\n";
        exit;
    }
    
    echo "👤 User: {$user->nama_lengkap} (ID: {$user->id})\n";
    
    // Get Administrator role
    $adminRole = DB::table('erp_role')->where('name', 'Administrator')->first();
    if (!$adminRole) {
        echo "❌ Administrator role not found\n";
        exit;
    }
    
    echo "🎭 Role: {$adminRole->name} (ID: {$adminRole->id})\n";
    
    // Check if user already has this role
    $existingRole = DB::table('erp_user_role')
        ->where('user_id', $user->id)
        ->where('role_id', $adminRole->id)
        ->first();
    
    if ($existingRole) {
        echo "✅ User already has Administrator role\n";
    } else {
        // Assign role to user
        DB::table('erp_user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $adminRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✅ Successfully assigned Administrator role to user\n";
    }
    
    // Verify the assignment
    echo "\n🔍 Verifying assignment:\n";
    $userRoles = DB::table('erp_user_role')
        ->join('erp_role', 'erp_user_role.role_id', '=', 'erp_role.id')
        ->where('erp_user_role.user_id', $user->id)
        ->get();
    
    if ($userRoles->count() > 0) {
        foreach ($userRoles as $role) {
            echo "  ✅ {$role->name} (ID: {$role->id})\n";
        }
    } else {
        echo "  ❌ No roles found\n";
    }
    
    echo "\n🎉 Role assignment complete! Now login again to see the LMS menu.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 