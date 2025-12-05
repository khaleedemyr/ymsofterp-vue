<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking permission codes without -view suffix...\n\n";
    
    // Get all permissions
    $permissions = DB::table('erp_permission')->get();
    
    // Check for permissions without -view suffix
    $simplePermissions = $permissions->filter(function($perm) {
        return !str_ends_with($perm->code, '-view') && $perm->action === 'view';
    });
    
    echo "📋 Permissions without -view suffix (view action only):\n";
    if ($simplePermissions->count() > 0) {
        foreach ($simplePermissions as $perm) {
            echo "  - {$perm->code} ({$perm->action})\n";
        }
    } else {
        echo "  ❌ No permissions found without -view suffix\n";
    }
    
    // Check for LMS permissions specifically
    echo "\n📚 LMS permissions (all actions):\n";
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
    
    // Check if there are any permissions that match the frontend format
    echo "\n🔍 Checking if any permissions match frontend format:\n";
    $frontendFormats = ['dashboard', 'categories', 'maintenance_order', 'lms-dashboard', 'lms-categories'];
    
    foreach ($frontendFormats as $format) {
        $matches = $permissions->filter(function($perm) use ($format) {
            return $perm->code === $format;
        });
        
        if ($matches->count() > 0) {
            echo "  ✅ Found: {$format}\n";
        } else {
            echo "  ❌ Not found: {$format}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 