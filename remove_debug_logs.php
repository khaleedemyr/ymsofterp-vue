<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Remove Debug Logs ===\n\n";

try {
    // List of files that contain debug logs
    $filesToClean = [
        'app/Http/Controllers/ReportController.php',
        'app/Http/Controllers/LmsController.php',
        'app/Http/Controllers/OutletFoodGoodReceiveController.php',
        'app/Http/Controllers/FoodGoodReceiveController.php',
        'app/Http/Controllers/ItemController.php',
        'app/Http/Controllers/ButcherProcessController.php',
        'app/Http/Controllers/FoodFloorOrderController.php',
        'app/Http/Controllers/GoodReceiveOutletSupplierController.php',
        'app/Http/Controllers/OutletPaymentController.php',
        'app/Http/Controllers/SubCategoryController.php',
        'app/Http/Controllers/OutletInternalUseWasteController.php',
        'app/Http/Controllers/FOScheduleController.php',
        'app/Models/OutletPayment.php'
    ];
    
    $totalRemoved = 0;
    
    foreach ($filesToClean as $file) {
        if (!file_exists($file)) {
            echo "File not found: {$file}\n";
            continue;
        }
        
        echo "Processing: {$file}\n";
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Remove debug log patterns
        $patterns = [
            '/\s*\\\\Log::info\(\s*[\'"]DEBUG[^\'"]*[\'"]\s*,[^;]*\);\s*/',
            '/\s*\\\\Log::debug\([^;]*\);\s*/',
            '/\s*Log::info\(\s*[\'"]DEBUG[^\'"]*[\'"]\s*,[^;]*\);\s*/',
            '/\s*Log::debug\([^;]*\);\s*/',
            '/\s*\\\\Log::info\(\s*[\'"]===.*DEBUG.*===[\'"]\s*\);\s*/',
            '/\s*\\\\Log::info\(\s*[\'"]DEBUG.*START[\'"]\s*\);\s*/',
            '/\s*\\\\Log::info\(\s*[\'"]DEBUG.*END[\'"]\s*\);\s*/',
        ];
        
        $removedCount = 0;
        foreach ($patterns as $pattern) {
            $matches = preg_match_all($pattern, $content);
            if ($matches > 0) {
                $content = preg_replace($pattern, '', $content);
                $removedCount += $matches;
            }
        }
        
        if ($removedCount > 0) {
            file_put_contents($file, $content);
            echo "  ✓ Removed {$removedCount} debug log statements\n";
            $totalRemoved += $removedCount;
        } else {
            echo "  - No debug logs found\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total debug log statements removed: {$totalRemoved}\n";
    echo "Files processed: " . count($filesToClean) . "\n";
    
    if ($totalRemoved > 0) {
        echo "\n✓ Debug logs have been removed from the codebase.\n";
        echo "This will prevent future debug logs from being generated.\n";
    } else {
        echo "\n- No debug logs found to remove.\n";
    }
    
    echo "\n=== Recommendations ===\n";
    echo "1. Run 'php cleanup_debug_logs.php' to clean existing log file\n";
    echo "2. Consider using environment-based logging:\n";
    echo "   - Use Log::debug() only in development\n";
    echo "   - Use Log::info() sparingly in production\n";
    echo "3. Set up log rotation to prevent large log files\n";
    echo "4. Use conditional logging:\n";
    echo "   if (config('app.debug')) {\n";
    echo "       Log::info('Debug message');\n";
    echo "   }\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
