<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Log Cleanup ===\n\n";

try {
    $logFile = storage_path('logs/laravel.log');
    
    if (!file_exists($logFile)) {
        echo "Log file not found: {$logFile}\n";
        exit(1);
    }
    
    // Get current file size
    $currentSize = filesize($logFile);
    echo "Current log file size: " . number_format($currentSize / 1024 / 1024, 2) . " MB\n";
    
    // Read the log file
    $logContent = file_get_contents($logFile);
    
    // Count DEBUG INERTIA USER entries
    $debugCount = substr_count($logContent, 'DEBUG INERTIA USER');
    echo "Found {$debugCount} 'DEBUG INERTIA USER' entries\n";
    
    if ($debugCount > 0) {
        // Remove all DEBUG INERTIA USER entries
        $lines = explode("\n", $logContent);
        $filteredLines = [];
        $skipNext = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'DEBUG INERTIA USER') !== false) {
                // Skip this line and the next few lines (JSON data)
                $skipNext = true;
                continue;
            }
            
            if ($skipNext) {
                // Skip lines that are part of the JSON data
                if (strpos($line, ']') !== false && strpos($line, 'local.INFO:') === false) {
                    $skipNext = false;
                }
                continue;
            }
            
            $filteredLines[] = $line;
        }
        
        // Write the cleaned log back
        $cleanedContent = implode("\n", $filteredLines);
        file_put_contents($logFile, $cleanedContent);
        
        // Get new file size
        $newSize = filesize($logFile);
        $savedSpace = $currentSize - $newSize;
        
        echo "✓ Cleaned log file\n";
        echo "New log file size: " . number_format($newSize / 1024 / 1024, 2) . " MB\n";
        echo "Space saved: " . number_format($savedSpace / 1024 / 1024, 2) . " MB\n";
        echo "Removed {$debugCount} debug entries\n";
        
    } else {
        echo "No DEBUG INERTIA USER entries found\n";
    }
    
    // Check for other debug patterns
    echo "\n=== Checking for other debug patterns ===\n";
    $otherPatterns = [
        'DEBUG',
        'dd(',
        'dump(',
        'var_dump(',
        'print_r(',
        'Log::debug',
        'Log::info.*DEBUG'
    ];
    
    foreach ($otherPatterns as $pattern) {
        $count = substr_count($logContent, $pattern);
        if ($count > 0) {
            echo "Found {$count} entries with pattern: {$pattern}\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Debug log cleanup completed.\n";
    echo "The 'DEBUG INERTIA USER' log has been removed from the middleware.\n";
    echo "Future requests will not generate these debug logs.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
