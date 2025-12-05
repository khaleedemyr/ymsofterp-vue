<?php
/**
 * Test Script untuk Stock Card Performance
 * 
 * Script ini digunakan untuk testing performa laporan kartu stok
 * dan monitoring memory usage serta execution time.
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Stock Card Performance Test ===\n\n";

// Test 1: Memory dan Execution Time
echo "1. Testing Memory dan Execution Time...\n";
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

try {
    // Simulasi query stock card
    $query = DB::table('food_inventory_cards as c')
        ->join('food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
        ->join('items as i', 'fi.item_id', '=', 'i.id')
        ->join('warehouses as w', 'c.warehouse_id', '=', 'w.id')
        ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
        ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
        ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
        ->select(
            'c.id',
            'c.date',
            'i.id as item_id',
            'i.name as item_name',
            'w.name as warehouse_name',
            'c.in_qty_small',
            'c.out_qty_small',
            'c.saldo_qty_small'
        )
        ->where('i.id', 1) // Test dengan item ID 1
        ->whereDate('c.date', '>=', '2024-01-01')
        ->whereDate('c.date', '<=', '2024-12-31')
        ->orderBy('c.date')
        ->limit(1000); // Limit untuk testing
    
    $data = $query->get();
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $executionTime = $endTime - $startTime;
    $memoryUsed = $endMemory - $startMemory;
    
    echo "✅ Query berhasil dieksekusi\n";
    echo "   - Execution Time: " . round($executionTime, 2) . " detik\n";
    echo "   - Memory Used: " . round($memoryUsed / 1024 / 1024, 2) . " MB\n";
    echo "   - Records Found: " . $data->count() . "\n";
    
    if ($executionTime > 30) {
        echo "⚠️  WARNING: Execution time terlalu lama (>30 detik)\n";
    }
    
    if ($memoryUsed > 100 * 1024 * 1024) { // 100MB
        echo "⚠️  WARNING: Memory usage terlalu tinggi (>100MB)\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Database Connection
echo "2. Testing Database Connection...\n";
try {
    $connection = DB::connection()->getPdo();
    echo "✅ Database connection berhasil\n";
    echo "   - Database: " . DB::connection()->getDatabaseName() . "\n";
    echo "   - Driver: " . DB::connection()->getDriverName() . "\n";
} catch (Exception $e) {
    echo "❌ ERROR: Database connection gagal - " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Table Existence
echo "3. Testing Table Existence...\n";
$requiredTables = [
    'food_inventory_cards',
    'food_inventory_items',
    'items',
    'warehouses',
    'units'
];

foreach ($requiredTables as $table) {
    try {
        $exists = DB::select("SHOW TABLES LIKE '{$table}'");
        if (count($exists) > 0) {
            echo "✅ Table {$table} exists\n";
        } else {
            echo "❌ Table {$table} tidak ditemukan\n";
        }
    } catch (Exception $e) {
        echo "❌ ERROR checking table {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 4: Data Count
echo "4. Testing Data Count...\n";
try {
    $cardCount = DB::table('food_inventory_cards')->count();
    $itemCount = DB::table('items')->count();
    $warehouseCount = DB::table('warehouses')->count();
    
    echo "✅ Data count:\n";
    echo "   - Food Inventory Cards: " . number_format($cardCount) . "\n";
    echo "   - Items: " . number_format($itemCount) . "\n";
    echo "   - Warehouses: " . number_format($warehouseCount) . "\n";
    
    if ($cardCount > 100000) {
        echo "⚠️  WARNING: Data kartu stok sangat besar, pertimbangkan pagination\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: PHP Configuration
echo "5. Testing PHP Configuration...\n";
echo "   - Memory Limit: " . ini_get('memory_limit') . "\n";
echo "   - Max Execution Time: " . ini_get('max_execution_time') . " detik\n";
echo "   - Max Input Vars: " . ini_get('max_input_vars') . "\n";
echo "   - Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   - Post Max Size: " . ini_get('post_max_size') . "\n";

// Check if configuration is optimal
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');

if ($memoryLimit && $memoryLimit !== '-1') {
    $memoryBytes = $this->convertToBytes($memoryLimit);
    if ($memoryBytes < 256 * 1024 * 1024) { // 256MB
        echo "⚠️  WARNING: Memory limit terlalu kecil untuk query yang berat\n";
    }
}

if ($maxExecutionTime && $maxExecutionTime < 300) {
    echo "⚠️  WARNING: Max execution time terlalu kecil untuk query yang berat\n";
}

echo "\n";

// Test 6: Index Check
echo "6. Testing Database Indexes...\n";
try {
    $indexes = DB::select("SHOW INDEX FROM food_inventory_cards");
    $indexColumns = array_unique(array_column($indexes, 'Column_name'));
    
    $requiredIndexes = ['date', 'inventory_item_id', 'warehouse_id'];
    foreach ($requiredIndexes as $index) {
        if (in_array($index, $indexColumns)) {
            echo "✅ Index pada kolom {$index} tersedia\n";
        } else {
            echo "⚠️  WARNING: Index pada kolom {$index} tidak ditemukan\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Selesai ===\n";

/**
 * Convert memory limit string to bytes
 */
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}
