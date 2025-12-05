<?php

// Simple PHP script to fix double-encoded documentation_paths
$host = 'localhost';
$dbname = 'db_justus';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connecting to database...\n";
    
    // Get all records with documentation_paths
    $stmt = $pdo->prepare("
        SELECT id, documentation_paths 
        FROM dynamic_inspection_details 
        WHERE documentation_paths IS NOT NULL 
          AND documentation_paths != '[]'
          AND documentation_paths != 'null'
          AND documentation_paths LIKE '%dynamic-inspection-docs%'
    ");
    
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($records) . " records to check...\n";
    
    foreach ($records as $record) {
        $id = $record['id'];
        $currentPaths = $record['documentation_paths'];
        
        echo "Record ID: $id\n";
        echo "Current: $currentPaths\n";
        
        // Check if it's double-encoded (starts and ends with quotes)
        if (strpos($currentPaths, '"[') === 0 && substr($currentPaths, -1) === '"') {
            // Remove outer quotes
            $fixedPaths = trim($currentPaths, '"');
            echo "Fixed: $fixedPaths\n";
            
            // Update the record
            $updateStmt = $pdo->prepare("
                UPDATE dynamic_inspection_details 
                SET documentation_paths = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$fixedPaths, $id]);
            echo "Updated!\n";
        } else {
            echo "No fix needed\n";
        }
        echo "---\n";
    }
    
    echo "Done!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
