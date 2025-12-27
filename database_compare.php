<?php

/**
 * Script PHP untuk membandingkan struktur 2 database MySQL/MariaDB
 * 
 * Cara penggunaan:
 * 1. Edit konfigurasi database di bawah ini
 * 2. Jalankan: php database_compare.php
 * 3. Hasil akan ditampilkan di console dan disimpan ke file database_compare_result.txt
 */

// ============================================
// KONFIGURASI DATABASE
// ============================================
$db1_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'database1', // Ganti dengan nama database pertama
];

$db2_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'database2', // Ganti dengan nama database kedua
];

// ============================================
// FUNGSI UTILITY
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

function getTables($pdo, $database) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$database' ORDER BY TABLE_NAME");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getColumns($pdo, $database, $table) {
    $stmt = $pdo->query("
        SELECT 
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_KEY,
            EXTRA,
            ORDINAL_POSITION
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = '$table'
        ORDER BY ORDINAL_POSITION
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getIndexes($pdo, $database, $table) {
    $stmt = $pdo->query("
        SELECT 
            INDEX_NAME,
            COLUMN_NAME,
            SEQ_IN_INDEX,
            NON_UNIQUE
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = '$table'
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getForeignKeys($pdo, $database, $table) {
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$database' 
        AND TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY CONSTRAINT_NAME
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function printSection($title) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "  $title\n";
    echo str_repeat("=", 80) . "\n\n";
}

function printTable($data, $headers = null) {
    if (empty($data)) {
        echo "  (Tidak ada data)\n\n";
        return;
    }
    
    if ($headers === null && !empty($data)) {
        $headers = array_keys($data[0]);
    }
    
    // Calculate column widths
    $widths = [];
    foreach ($headers as $header) {
        $widths[$header] = strlen($header);
    }
    foreach ($data as $row) {
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            $widths[$header] = max($widths[$header], strlen($value));
        }
    }
    
    // Print header
    echo "  " . str_pad("", array_sum($widths) + count($headers) * 3 - 1, "-") . "\n";
    echo "  |";
    foreach ($headers as $header) {
        echo " " . str_pad($header, $widths[$header]) . " |";
    }
    echo "\n";
    echo "  " . str_pad("", array_sum($widths) + count($headers) * 3 - 1, "-") . "\n";
    
    // Print rows
    foreach ($data as $row) {
        echo "  |";
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            echo " " . str_pad($value, $widths[$header]) . " |";
        }
        echo "\n";
    }
    echo "  " . str_pad("", array_sum($widths) + count($headers) * 3 - 1, "-") . "\n\n";
}

// ============================================
// MAIN PROCESS
// ============================================

$output = "";
ob_start();

printSection("PERBANDINGAN STRUKTUR DATABASE");
echo "Database 1: {$db1_config['database']}\n";
echo "Database 2: {$db2_config['database']}\n";

$pdo1 = connectDB($db1_config);
$pdo2 = connectDB($db2_config);

// Set database
$pdo1->exec("USE {$db1_config['database']}");
$pdo2->exec("USE {$db2_config['database']}");

// 1. Compare Tables
printSection("1. PERBANDINGAN TABEL");

$tables1 = getTables($pdo1, $db1_config['database']);
$tables2 = getTables($pdo2, $db2_config['database']);

$onlyIn1 = array_diff($tables1, $tables2);
$onlyIn2 = array_diff($tables2, $tables1);
$common = array_intersect($tables1, $tables2);

echo "Tabel yang hanya ada di {$db1_config['database']}: " . count($onlyIn1) . "\n";
if (!empty($onlyIn1)) {
    foreach ($onlyIn1 as $table) {
        echo "  - $table\n";
    }
}

echo "\nTabel yang hanya ada di {$db2_config['database']}: " . count($onlyIn2) . "\n";
if (!empty($onlyIn2)) {
    foreach ($onlyIn2 as $table) {
        echo "  - $table\n";
    }
}

echo "\nTabel yang ada di kedua database: " . count($common) . "\n";

// 2. Compare Columns
printSection("2. PERBANDINGAN KOLOM");

$columnDifferences = [];
foreach ($common as $table) {
    $cols1 = getColumns($pdo1, $db1_config['database'], $table);
    $cols2 = getColumns($pdo2, $db2_config['database'], $table);
    
    $cols1Map = [];
    foreach ($cols1 as $col) {
        $cols1Map[$col['COLUMN_NAME']] = $col;
    }
    
    $cols2Map = [];
    foreach ($cols2 as $col) {
        $cols2Map[$col['COLUMN_NAME']] = $col;
    }
    
    $onlyIn1Cols = array_diff_key($cols1Map, $cols2Map);
    $onlyIn2Cols = array_diff_key($cols2Map, $cols1Map);
    $commonCols = array_intersect_key($cols1Map, $cols2Map);
    
    $diffCols = [];
    foreach ($commonCols as $colName => $col1) {
        $col2 = $cols2Map[$colName];
        if ($col1['COLUMN_TYPE'] != $col2['COLUMN_TYPE'] ||
            $col1['IS_NULLABLE'] != $col2['IS_NULLABLE'] ||
            $col1['COLUMN_DEFAULT'] != $col2['COLUMN_DEFAULT'] ||
            $col1['COLUMN_KEY'] != $col2['COLUMN_KEY'] ||
            $col1['EXTRA'] != $col2['EXTRA']) {
            $diffCols[] = [
                'TABLE' => $table,
                'COLUMN' => $colName,
                'DB1_TYPE' => $col1['COLUMN_TYPE'],
                'DB2_TYPE' => $col2['COLUMN_TYPE'],
                'DB1_NULLABLE' => $col1['IS_NULLABLE'],
                'DB2_NULLABLE' => $col2['IS_NULLABLE'],
                'DB1_DEFAULT' => $col1['COLUMN_DEFAULT'] ?? 'NULL',
                'DB2_DEFAULT' => $col2['COLUMN_DEFAULT'] ?? 'NULL',
            ];
        }
    }
    
    if (!empty($onlyIn1Cols) || !empty($onlyIn2Cols) || !empty($diffCols)) {
        echo "\nTabel: $table\n";
        
        if (!empty($onlyIn1Cols)) {
            echo "  Kolom hanya di DB1:\n";
            foreach ($onlyIn1Cols as $col) {
                echo "    - {$col['COLUMN_NAME']} ({$col['COLUMN_TYPE']})\n";
            }
        }
        
        if (!empty($onlyIn2Cols)) {
            echo "  Kolom hanya di DB2:\n";
            foreach ($onlyIn2Cols as $col) {
                echo "    - {$col['COLUMN_NAME']} ({$col['COLUMN_TYPE']})\n";
            }
        }
        
        if (!empty($diffCols)) {
            echo "  Kolom dengan perbedaan:\n";
            printTable($diffCols);
        }
    }
}

// 3. Compare Indexes
printSection("3. PERBANDINGAN INDEX");

$indexDifferences = [];
foreach ($common as $table) {
    $idx1 = getIndexes($pdo1, $db1_config['database'], $table);
    $idx2 = getIndexes($pdo2, $db2_config['database'], $table);
    
    $idx1Map = [];
    foreach ($idx1 as $idx) {
        $key = $idx['INDEX_NAME'] . ':' . $idx['COLUMN_NAME'];
        $idx1Map[$key] = $idx;
    }
    
    $idx2Map = [];
    foreach ($idx2 as $idx) {
        $key = $idx['INDEX_NAME'] . ':' . $idx['COLUMN_NAME'];
        $idx2Map[$key] = $idx;
    }
    
    $onlyIn1Idx = array_diff_key($idx1Map, $idx2Map);
    $onlyIn2Idx = array_diff_key($idx2Map, $idx1Map);
    
    if (!empty($onlyIn1Idx) || !empty($onlyIn2Idx)) {
        echo "\nTabel: $table\n";
        
        if (!empty($onlyIn1Idx)) {
            echo "  Index hanya di DB1:\n";
            foreach ($onlyIn1Idx as $idx) {
                echo "    - {$idx['INDEX_NAME']} pada {$idx['COLUMN_NAME']}\n";
            }
        }
        
        if (!empty($onlyIn2Idx)) {
            echo "  Index hanya di DB2:\n";
            foreach ($onlyIn2Idx as $idx) {
                echo "    - {$idx['INDEX_NAME']} pada {$idx['COLUMN_NAME']}\n";
            }
        }
    }
}

// 4. Compare Foreign Keys
printSection("4. PERBANDINGAN FOREIGN KEY");

foreach ($common as $table) {
    $fk1 = getForeignKeys($pdo1, $db1_config['database'], $table);
    $fk2 = getForeignKeys($pdo2, $db2_config['database'], $table);
    
    $fk1Map = [];
    foreach ($fk1 as $fk) {
        $key = $fk['CONSTRAINT_NAME'] . ':' . $fk['COLUMN_NAME'];
        $fk1Map[$key] = $fk;
    }
    
    $fk2Map = [];
    foreach ($fk2 as $fk) {
        $key = $fk['CONSTRAINT_NAME'] . ':' . $fk['COLUMN_NAME'];
        $fk2Map[$key] = $fk;
    }
    
    $onlyIn1Fk = array_diff_key($fk1Map, $fk2Map);
    $onlyIn2Fk = array_diff_key($fk2Map, $fk1Map);
    
    if (!empty($onlyIn1Fk) || !empty($onlyIn2Fk)) {
        echo "\nTabel: $table\n";
        
        if (!empty($onlyIn1Fk)) {
            echo "  Foreign Key hanya di DB1:\n";
            foreach ($onlyIn1Fk as $fk) {
                echo "    - {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
        
        if (!empty($onlyIn2Fk)) {
            echo "  Foreign Key hanya di DB2:\n";
            foreach ($onlyIn2Fk as $fk) {
                echo "    - {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
            }
        }
    }
}

// Summary
printSection("RINGKASAN");

echo "Total tabel di {$db1_config['database']}: " . count($tables1) . "\n";
echo "Total tabel di {$db2_config['database']}: " . count($tables2) . "\n";
echo "Tabel yang sama: " . count($common) . "\n";
echo "Tabel berbeda: " . (count($onlyIn1) + count($onlyIn2)) . "\n";

$output = ob_get_clean();
echo $output;

// Save to file
file_put_contents('database_compare_result.txt', $output);
echo "\n\nHasil juga disimpan ke: database_compare_result.txt\n";

