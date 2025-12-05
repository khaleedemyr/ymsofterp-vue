<?php

/**
 * Script untuk membersihkan debug logs secara otomatis
 * Dijalankan via cron job untuk mencegah file log membesar
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "[" . date('Y-m-d H:i:s') . "] Starting debug log cleanup...\n";

try {
    $logFile = storage_path('logs/laravel.log');
    
    if (!file_exists($logFile)) {
        echo "[" . date('Y-m-d H:i:s') . "] Log file not found: {$logFile}\n";
        exit(0);
    }
    
    // Get current file size
    $currentSize = filesize($logFile);
    $currentSizeMB = round($currentSize / 1024 / 1024, 2);
    
    echo "[" . date('Y-m-d H:i:s') . "] Current log file size: {$currentSizeMB} MB\n";
    
    // Skip if file is too small (less than 10MB)
    if ($currentSize < 10 * 1024 * 1024) {
        echo "[" . date('Y-m-d H:i:s') . "] Log file is small ({$currentSizeMB} MB), skipping cleanup\n";
        exit(0);
    }
    
    // Read the log file
    $logContent = file_get_contents($logFile);
    
    // Count debug entries
    $debugPatterns = [
        'DEBUG INERTIA USER',
        'DEBUG FILTER',
        'DEBUG ORDERS COUNT',
        'DEBUG SAMPLE ORDERS',
        'DEBUG COMMFEE ROUNDING TOTALS',
        'DEBUG DISCOUNT CALCULATION',
        'DEBUG STORE OUTLET GR',
        'DEBUG INSERT HEADER',
        'DEBUG HEADER INSERTED',
        'DEBUG INSERT DETAIL',
        'DEBUG SKIP INVENTORY',
        'DEBUG ITEM MASTER',
        'DEBUG INVENTORY ITEM',
        'DEBUG KONVERSI QTY',
        'DEBUG STOCK UPDATED',
        'DEBUG KARTU STOK INSERTED',
        'DEBUG COST HISTORY INSERTED',
        'DEBUG STORE OUTLET GR SUCCESS',
        'DEBUG FLOOR ORDER STATUS UPDATED',
        'DEBUG SHOW GR',
        'DEBUG DO OUTLET',
        'DEBUG: Using fixed warehouse',
        'DEBUG: Using existing warehouse',
        'ITEM UPDATE DEBUG',
        'DEBUG $item->prices MAPPED',
        'DEBUG: masuk method',
        'DEBUG: hasil items',
        'DEBUG REQUEST region_id',
        'DEBUG MAC_PCS BACKEND',
        'DEBUG DETAIL',
        'DEBUG STOCK',
        'DEBUG WAREHOUSE OUTLET',
        'DEBUG: OutletPaymentController',
        'DEBUG: Cek outletPayment',
        'DEBUG: Cek total_amount',
        'DEBUG: Sebelum create',
        'DEBUG: Setelah create',
        'DEBUG ROUTE SUBCATEGORY',
        'DEBUG SUB CATEGORY ID',
        'DEBUG: OutletPayment creating',
        'DETAILS: Header not found',
        'DETAILS: Found',
        'DETAILS: Detail data',
        'DETAILS: inventory_item_id',
        'DETAILS: MAC for item_id',
        'DETAILS: mac_converted',
        'DETAILS: Final result',
        'GR: | Payment:',
        '=== SHOW COURSE DEBUG',
        'Material processing summary'
    ];
    
    $totalDebugEntries = 0;
    foreach ($debugPatterns as $pattern) {
        $count = substr_count($logContent, $pattern);
        $totalDebugEntries += $count;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Found {$totalDebugEntries} debug entries\n";
    
    if ($totalDebugEntries > 0) {
        // Remove debug entries
        $lines = explode("\n", $logContent);
        $filteredLines = [];
        $skipNext = false;
        $removedLines = 0;
        
        foreach ($lines as $line) {
            $isDebugLine = false;
            
            // Check if this line contains debug pattern
            foreach ($debugPatterns as $pattern) {
                if (strpos($line, $pattern) !== false) {
                    $isDebugLine = true;
                    break;
                }
            }
            
            if ($isDebugLine) {
                // Skip this line and the next few lines (JSON data)
                $skipNext = true;
                $removedLines++;
                continue;
            }
            
            if ($skipNext) {
                // Skip lines that are part of the JSON data
                if (strpos($line, ']') !== false && strpos($line, 'local.INFO:') === false) {
                    $skipNext = false;
                }
                $removedLines++;
                continue;
            }
            
            $filteredLines[] = $line;
        }
        
        // Write the cleaned log back
        $cleanedContent = implode("\n", $filteredLines);
        file_put_contents($logFile, $cleanedContent);
        
        // Get new file size
        $newSize = filesize($logFile);
        $newSizeMB = round($newSize / 1024 / 1024, 2);
        $savedSpace = $currentSize - $newSize;
        $savedSpaceMB = round($savedSpace / 1024 / 1024, 2);
        
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Cleanup completed\n";
        echo "[" . date('Y-m-d H:i:s') . "] Removed {$removedLines} lines\n";
        echo "[" . date('Y-m-d H:i:s') . "] New file size: {$newSizeMB} MB\n";
        echo "[" . date('Y-m-d H:i:s') . "] Space saved: {$savedSpaceMB} MB\n";
        
        // Log the cleanup action
        \Log::info('Debug log cleanup completed', [
            'original_size_mb' => $currentSizeMB,
            'new_size_mb' => $newSizeMB,
            'space_saved_mb' => $savedSpaceMB,
            'lines_removed' => $removedLines,
            'debug_entries_removed' => $totalDebugEntries
        ]);
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No debug entries found\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Debug log cleanup finished\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    \Log::error('Debug log cleanup failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
