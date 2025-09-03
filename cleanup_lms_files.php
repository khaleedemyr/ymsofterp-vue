<?php

/**
 * LMS File Cleanup Script
 * 
 * This script cleans up invalid file references in the LMS system.
 * Run this script from the command line or web browser to fix file issues.
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\LmsController;

echo "=== LMS File Cleanup Script ===\n";
echo "Starting file cleanup process...\n\n";

try {
    // Create controller instance
    $controller = new LmsController();
    
    // Run cleanup
    $result = $controller->cleanupInvalidFiles();
    
    if ($result['success']) {
        echo "✅ Cleanup completed successfully!\n";
        echo "📊 Materials processed: {$result['materials_processed']}\n";
        echo "🧹 Materials cleaned: {$result['materials_cleaned']}\n";
        echo "💬 Message: {$result['message']}\n";
    } else {
        echo "❌ Cleanup failed!\n";
        echo "🚨 Error: {$result['error']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Script execution failed!\n";
    echo "🚨 Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}

echo "\n=== Script completed ===\n";
