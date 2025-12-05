<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking permission codes in database...\n\n";
    
    // Get all permissions
    $permissions = DB::table('erp_permission')->get();
    
    echo "📋 All permissions:\n";
    foreach ($permissions as $perm) {
        echo "  - {$perm->code} ({$perm->action})\n";
    }
    
    // Check LMS permissions specifically
    echo "\n📚 LMS permissions:\n";
    $lmsPermissions = $permissions->filter(function($perm) {
        return strpos($perm->code, 'lms-') === 0;
    });
    
    if ($lmsPermissions->count() > 0) {
        foreach ($lmsPermissions as $perm) {
            echo "  - {$perm->code} ({$perm->action})\n";
        }
    } else {
        echo "  ❌ No LMS permissions found\n";
    }
    
    // Check other menu permissions for comparison
    echo "\n🔍 Other menu permissions (for comparison):\n";
    $otherPermissions = $permissions->filter(function($perm) {
        return strpos($perm->code, 'lms-') !== 0 && !strpos($perm->code, '-view');
    })->take(10);
    
    foreach ($otherPermissions as $perm) {
        echo "  - {$perm->code} ({$perm->action})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 