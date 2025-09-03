<?php
/**
 * Run Database Fix for Curriculum System
 * Script untuk menjalankan fix database structure
 */

echo "=== Running Curriculum Database Fix ===\n\n";

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'ymsofterp';
    $username = 'root'; // Ganti dengan username database Anda
    $password = ''; // Ganti dengan password database Anda
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Database connected successfully\n\n";
    
    // Read and execute SQL file
    $sqlFile = 'fix_curriculum_structure_final.sql';
    if (!file_exists($sqlFile)) {
        echo "✗ SQL file not found: $sqlFile\n";
        exit(1);
    }
    
    $sql = file_get_contents($sqlFile);
    echo "✓ SQL file loaded: $sqlFile\n";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "\nExecuting SQL statements...\n";
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip comments and empty lines
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            $successCount++;
        } catch (PDOException $e) {
            echo "✗ Error executing: " . substr($statement, 0, 50) . "...\n";
            echo "  Error: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n=== Execution Summary ===\n";
    echo "Successful: $successCount\n";
    echo "Errors: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n✓ Database fix completed successfully!\n";
        
        // Verify the fix
        echo "\n=== Verifying Fix ===\n";
        
        // Check if tables exist
        $tables = ['lms_curriculum_items', 'lms_curriculum_materials'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "✓ Table '$table' exists with " . count($columns) . " columns\n";
            } catch (PDOException $e) {
                echo "✗ Table '$table' error: " . $e->getMessage() . "\n";
            }
        }
        
        // Check sample data
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM lms_curriculum_items");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✓ Curriculum items count: " . $result['count'] . "\n";
        } catch (PDOException $e) {
            echo "✗ Error checking data: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "\n✗ Database fix completed with errors. Please check the output above.\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database credentials:\n";
    echo "- Host: $host\n";
    echo "- Database: $dbname\n";
    echo "- Username: $username\n";
    echo "- Password: " . (empty($password) ? '(empty)' : '(set)') . "\n";
} catch (Exception $e) {
    echo "✗ General error: " . $e->getMessage() . "\n";
}

echo "\n=== Script completed ===\n";
