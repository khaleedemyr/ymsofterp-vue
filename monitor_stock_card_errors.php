<?php
/**
 * Monitor Script untuk Stock Card Errors
 * 
 * Script ini digunakan untuk monitoring error pada laporan kartu stok
 * dan memberikan informasi debugging yang berguna.
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Stock Card Error Monitor ===\n\n";

// Function untuk format bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Function untuk format time
function formatTime($seconds) {
    if ($seconds < 60) {
        return round($seconds, 2) . ' detik';
    } elseif ($seconds < 3600) {
        return round($seconds / 60, 2) . ' menit';
    } else {
        return round($seconds / 3600, 2) . ' jam';
    }
}

// 1. Check Recent Errors
echo "1. Checking Recent Stock Card Errors...\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $stockCardErrors = [];
        
        // Cari error terkait stock card
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -1000); // Ambil 1000 baris terakhir
        
        foreach ($recentLines as $line) {
            if (strpos($line, 'Stock Card') !== false || 
                strpos($line, 'stockCard') !== false ||
                strpos($line, 'inventory.stock-card') !== false) {
                $stockCardErrors[] = $line;
            }
        }
        
        if (count($stockCardErrors) > 0) {
            echo "⚠️  Ditemukan " . count($stockCardErrors) . " error terkait Stock Card:\n";
            foreach (array_slice($stockCardErrors, -5) as $error) { // Tampilkan 5 error terakhir
                echo "   " . $error . "\n";
            }
        } else {
            echo "✅ Tidak ada error terkait Stock Card dalam log terakhir\n";
        }
    } else {
        echo "❌ Log file tidak ditemukan\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Check Database Performance
echo "2. Checking Database Performance...\n";
try {
    // Test query performance
    $startTime = microtime(true);
    
    $slowQueries = DB::select("
        SELECT 
            query_time,
            lock_time,
            rows_sent,
            rows_examined,
            sql_text
        FROM mysql.slow_log 
        WHERE sql_text LIKE '%food_inventory_cards%'
        ORDER BY query_time DESC 
        LIMIT 5
    ");
    
    $endTime = microtime(true);
    
    if (count($slowQueries) > 0) {
        echo "⚠️  Ditemukan " . count($slowQueries) . " slow query terkait food_inventory_cards:\n";
        foreach ($slowQueries as $query) {
            echo "   - Query Time: " . $query->query_time . "s\n";
            echo "   - Rows Examined: " . number_format($query->rows_examined) . "\n";
            echo "   - SQL: " . substr($query->sql_text, 0, 100) . "...\n\n";
        }
    } else {
        echo "✅ Tidak ada slow query terkait food_inventory_cards\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Check Memory Usage
echo "3. Checking Memory Usage...\n";
try {
    $memoryUsage = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    echo "   - Current Memory: " . formatBytes($memoryUsage) . "\n";
    echo "   - Peak Memory: " . formatBytes($peakMemory) . "\n";
    echo "   - Memory Limit: " . $memoryLimit . "\n";
    
    // Convert memory limit to bytes
    $limitBytes = convertToBytes($memoryLimit);
    $usagePercent = ($memoryUsage / $limitBytes) * 100;
    
    if ($usagePercent > 80) {
        echo "⚠️  WARNING: Memory usage tinggi (" . round($usagePercent, 2) . "%)\n";
    } else {
        echo "✅ Memory usage normal (" . round($usagePercent, 2) . "%)\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Check Database Connection
echo "4. Checking Database Connection...\n";
try {
    $connection = DB::connection()->getPdo();
    $databaseName = DB::connection()->getDatabaseName();
    
    // Check connection status
    $status = DB::select("SELECT 1 as test")[0];
    
    if ($status->test == 1) {
        echo "✅ Database connection aktif\n";
        echo "   - Database: " . $databaseName . "\n";
        
        // Check connection timeout
        $timeout = DB::select("SHOW VARIABLES LIKE 'wait_timeout'")[0];
        echo "   - Wait Timeout: " . $timeout->Value . " detik\n";
        
        // Check max connections
        $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'")[0];
        echo "   - Max Connections: " . $maxConnections->Value . "\n";
        
        // Check current connections
        $currentConnections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0];
        echo "   - Current Connections: " . $currentConnections->Value . "\n";
        
    } else {
        echo "❌ Database connection bermasalah\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: Database connection gagal - " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Check Table Sizes
echo "5. Checking Table Sizes...\n";
try {
    $tables = [
        'food_inventory_cards',
        'food_inventory_items',
        'items',
        'warehouses'
    ];
    
    foreach ($tables as $table) {
        try {
            $size = DB::select("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB',
                    table_rows
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}'
            ");
            
            if (count($size) > 0) {
                $tableInfo = $size[0];
                echo "   - {$table}: " . $tableInfo->Size_MB . " MB (" . number_format($tableInfo->table_rows) . " rows)\n";
                
                if ($tableInfo->Size_MB > 100) {
                    echo "     ⚠️  WARNING: Table size besar, pertimbangkan archiving\n";
                }
            } else {
                echo "   - {$table}: Table tidak ditemukan\n";
            }
        } catch (Exception $e) {
            echo "   - {$table}: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Check Index Usage
echo "6. Checking Index Usage...\n";
try {
    $indexes = DB::select("
        SELECT 
            TABLE_NAME,
            INDEX_NAME,
            COLUMN_NAME,
            CARDINALITY
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'food_inventory_cards'
        ORDER BY CARDINALITY DESC
    ");
    
    if (count($indexes) > 0) {
        echo "✅ Indexes pada food_inventory_cards:\n";
        foreach ($indexes as $index) {
            echo "   - {$index->INDEX_NAME} ({$index->COLUMN_NAME}): " . number_format($index->CARDINALITY) . " unique values\n";
        }
    } else {
        echo "⚠️  WARNING: Tidak ada index pada food_inventory_cards\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// 7. Check PHP Configuration
echo "7. Checking PHP Configuration...\n";
$phpSettings = [
    'memory_limit',
    'max_execution_time',
    'max_input_vars',
    'upload_max_filesize',
    'post_max_size',
    'max_input_time'
];

foreach ($phpSettings as $setting) {
    $value = ini_get($setting);
    echo "   - {$setting}: " . $value . "\n";
    
    // Check if setting is optimal
    if ($setting === 'memory_limit' && $value !== '-1') {
        $bytes = convertToBytes($value);
        if ($bytes < 256 * 1024 * 1024) { // 256MB
            echo "     ⚠️  WARNING: Memory limit terlalu kecil\n";
        }
    }
    
    if ($setting === 'max_execution_time' && $value < 300) {
        echo "     ⚠️  WARNING: Max execution time terlalu kecil\n";
    }
}

echo "\n";

// 8. Generate Recommendations
echo "8. Recommendations...\n";
$recommendations = [];

// Check if we need to add recommendations based on findings
if (isset($usagePercent) && $usagePercent > 80) {
    $recommendations[] = "Tingkatkan memory_limit di PHP configuration";
}

if (isset($slowQueries) && count($slowQueries) > 0) {
    $recommendations[] = "Optimasi query atau tambahkan index database";
}

if (isset($stockCardErrors) && count($stockCardErrors) > 0) {
    $recommendations[] = "Periksa error log dan perbaiki kode yang bermasalah";
}

if (count($recommendations) > 0) {
    echo "⚠️  Rekomendasi perbaikan:\n";
    foreach ($recommendations as $i => $rec) {
        echo "   " . ($i + 1) . ". " . $rec . "\n";
    }
} else {
    echo "✅ Tidak ada rekomendasi khusus, sistem berjalan normal\n";
}

echo "\n=== Monitor Selesai ===\n";

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
