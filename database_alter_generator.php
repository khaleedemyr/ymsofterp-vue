<?php

/**
 * Script untuk generate query ALTER TABLE otomatis berdasarkan hasil perbandingan
 * 
 * Cara penggunaan:
 * 1. Jalankan query perbandingan dari database_compare.sql
 * 2. Copy hasil query "Kolom di DB1 tapi tidak di DB2" ke variabel $missingColumns di bawah
 * 3. Edit konfigurasi database
 * 4. Jalankan: php database_alter_generator.php
 * 5. Hasil akan disimpan ke database_alter_generated.sql
 */

// ============================================
// KONFIGURASI
// ============================================
$db1_name = 'database1'; // Nama database source (DB1)
$db2_name = 'database2'; // Nama database target (DB2)

// Konfigurasi koneksi database
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
];

// ============================================
// DATA KOLOM YANG HILANG (dari hasil query perbandingan)
// ============================================
// Copy hasil query "Kolom di DB1 tapi tidak di DB2" ke sini
$missingColumns = [
    ['TABLE_NAME' => 'investor_outlet', 'COLUMN_NAME' => 'id', 'COLUMN_TYPE' => 'int', 'ORDINAL_POSITION' => 1],
    ['TABLE_NAME' => 'investor_outlet', 'COLUMN_NAME' => 'investor_id', 'COLUMN_TYPE' => 'int', 'ORDINAL_POSITION' => 2],
    ['TABLE_NAME' => 'investor_outlet', 'COLUMN_NAME' => 'outlet_id', 'COLUMN_TYPE' => 'int', 'ORDINAL_POSITION' => 3],
    ['TABLE_NAME' => 'investor_outlet', 'COLUMN_NAME' => 'created_at', 'COLUMN_TYPE' => 'timestamp', 'ORDINAL_POSITION' => 4],
    ['TABLE_NAME' => 'investor_outlet', 'COLUMN_NAME' => 'updated_at', 'COLUMN_TYPE' => 'timestamp', 'ORDINAL_POSITION' => 5],
    ['TABLE_NAME' => 'order_items', 'COLUMN_NAME' => 'b1g1_promo_id', 'COLUMN_TYPE' => 'int', 'ORDINAL_POSITION' => 10],
    ['TABLE_NAME' => 'order_items', 'COLUMN_NAME' => 'b1g1_status', 'COLUMN_TYPE' => 'varchar(20)', 'ORDINAL_POSITION' => 11],
    ['TABLE_NAME' => 'orders', 'COLUMN_NAME' => 'voucher_info', 'COLUMN_TYPE' => 'text', 'ORDINAL_POSITION' => 38],
    ['TABLE_NAME' => 'orders', 'COLUMN_NAME' => 'inactive_promo_items', 'COLUMN_TYPE' => 'text', 'ORDINAL_POSITION' => 39],
    ['TABLE_NAME' => 'orders', 'COLUMN_NAME' => 'promo_discount_info', 'COLUMN_TYPE' => 'text', 'ORDINAL_POSITION' => 40],
    ['TABLE_NAME' => 'promos', 'COLUMN_NAME' => 'all_tiers', 'COLUMN_TYPE' => 'tinyint(1)', 'ORDINAL_POSITION' => 9],
    ['TABLE_NAME' => 'promos', 'COLUMN_NAME' => 'tiers', 'COLUMN_TYPE' => 'json', 'ORDINAL_POSITION' => 10],
    ['TABLE_NAME' => 'promos', 'COLUMN_NAME' => 'days', 'COLUMN_TYPE' => 'json', 'ORDINAL_POSITION' => 15],
];

// ============================================
// FUNGSI
// ============================================

function connectDB($config) {
    try {
        $dsn = "mysql:host={$config['host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage() . "\n");
    }
}

function getColumnBefore($pdo, $database, $table, $ordinalPosition) {
    if ($ordinalPosition <= 1) {
        return null; // Kolom pertama, tidak ada AFTER
    }
    
    $stmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '$database' 
        AND TABLE_NAME = '$table'
        AND ORDINAL_POSITION = " . ($ordinalPosition - 1) . "
        LIMIT 1
    ");
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['COLUMN_NAME'] : null;
}

function getColumnInfo($pdo, $database, $table, $column) {
    $stmt = $pdo->query("
        SELECT 
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_KEY,
            EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '$database' 
        AND TABLE_NAME = '$table'
        AND COLUMN_NAME = '$column'
        LIMIT 1
    ");
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function parseColumnType($type) {
    // Parse tipe kolom untuk menentukan NULL, DEFAULT, dll
    $nullable = true;
    $default = 'NULL';
    $autoIncrement = false;
    
    if (stripos($type, 'NOT NULL') !== false) {
        $nullable = false;
    }
    
    if (stripos($type, 'AUTO_INCREMENT') !== false) {
        $autoIncrement = true;
    }
    
    // Extract base type
    $baseType = preg_replace('/\s+(NOT\s+NULL|AUTO_INCREMENT|DEFAULT.*)/i', '', $type);
    
    return [
        'base_type' => trim($baseType),
        'nullable' => $nullable,
        'default' => $default,
        'auto_increment' => $autoIncrement,
    ];
}

// ============================================
// MAIN PROCESS
// ============================================

$pdo = connectDB($db_config);

$output = "-- ============================================\n";
$output .= "-- QUERY ALTER TABLE GENERATED AUTOMATICALLY\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Source DB: $db1_name\n";
$output .= "-- Target DB: $db2_name\n";
$output .= "-- ============================================\n\n";
$output .= "USE $db2_name;\n\n";

// Group by table
$tables = [];
foreach ($missingColumns as $col) {
    $table = $col['TABLE_NAME'];
    if (!isset($tables[$table])) {
        $tables[$table] = [];
    }
    $tables[$table][] = $col;
}

// Generate ALTER statements
foreach ($tables as $table => $columns) {
    $output .= "-- ============================================\n";
    $output .= "-- Table: $table\n";
    $output .= "-- ============================================\n\n";
    
    // Check if table exists in DB2
    $stmt = $pdo->query("
        SELECT COUNT(*) as cnt 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '$db2_name' 
        AND TABLE_NAME = '$table'
    ");
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if (!$tableExists) {
        // Table doesn't exist, need to get full structure from DB1
        $output .= "-- WARNING: Table '$table' tidak ada di $db2_name\n";
        $output .= "-- Perlu membuat tabel lengkap dari $db1_name\n";
        $output .= "-- Gunakan: SHOW CREATE TABLE $db1_name.$table;\n\n";
    }
    
    // Sort by ordinal position
    usort($columns, function($a, $b) {
        return $a['ORDINAL_POSITION'] <=> $b['ORDINAL_POSITION'];
    });
    
    foreach ($columns as $col) {
        $colName = $col['COLUMN_NAME'];
        $colType = $col['COLUMN_TYPE'];
        $ordinal = $col['ORDINAL_POSITION'];
        
        // Get column info from DB1 for more details
        $colInfo = getColumnInfo($pdo, $db1_name, $table, $colName);
        
        // Get column before this one
        $beforeCol = getColumnBefore($pdo, $db2_name, $table, $ordinal);
        
        // Build ALTER statement
        $alter = "ALTER TABLE `$table` ADD COLUMN `$colName` $colType";
        
        // Add NULL/NOT NULL
        if ($colInfo) {
            if ($colInfo['IS_NULLABLE'] == 'NO') {
                $alter .= " NOT NULL";
            } else {
                $alter .= " NULL";
            }
            
            // Add DEFAULT
            if ($colInfo['COLUMN_DEFAULT'] !== null) {
                if (strtoupper($colInfo['COLUMN_DEFAULT']) == 'CURRENT_TIMESTAMP') {
                    $alter .= " DEFAULT CURRENT_TIMESTAMP";
                } elseif (is_numeric($colInfo['COLUMN_DEFAULT'])) {
                    $alter .= " DEFAULT " . $colInfo['COLUMN_DEFAULT'];
                } else {
                    $alter .= " DEFAULT '" . addslashes($colInfo['COLUMN_DEFAULT']) . "'";
                }
            } elseif ($colInfo['IS_NULLABLE'] == 'YES') {
                $alter .= " DEFAULT NULL";
            }
            
            // Add AUTO_INCREMENT
            if (stripos($colInfo['EXTRA'], 'auto_increment') !== false) {
                $alter .= " AUTO_INCREMENT";
            }
        }
        
        // Add AFTER clause
        if ($beforeCol) {
            $alter .= " AFTER `$beforeCol`";
        } elseif ($ordinal == 1) {
            $alter .= " FIRST";
        }
        
        $alter .= ";\n";
        
        $output .= $alter;
    }
    
    $output .= "\n";
}

// Save to file
file_put_contents('database_alter_generated.sql', $output);

echo "Query ALTER TABLE berhasil di-generate!\n";
echo "File disimpan ke: database_alter_generated.sql\n";
echo "\n";
echo "Jumlah tabel: " . count($tables) . "\n";
echo "Total kolom: " . count($missingColumns) . "\n";
echo "\n";
echo "PENTING: Review query sebelum menjalankan!\n";
echo "Pastikan untuk:\n";
echo "1. Backup database terlebih dahulu\n";
echo "2. Test di development/staging\n";
echo "3. Sesuaikan AFTER clause jika perlu\n";

