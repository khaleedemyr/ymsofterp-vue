<?php
/**
 * Simple Final Database Fix Runner
 * Script untuk menjalankan fix database structure sederhana
 */

echo "=== Simple Final Database Fix Runner ===\n\n";

try {
    // Try to use Laravel's database connection
    require_once 'vendor/autoload.php';
    
    // Bootstrap Laravel
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    echo "✓ Laravel bootstrapped successfully\n";
    
    // Test database connection
    $connection = DB::connection();
    $connection->getPdo();
    echo "✓ Database connected successfully\n\n";
    
    // Read and execute SQL file
    $sqlFile = 'fix_database_simple_final.sql';
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
            DB::statement($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            $successCount++;
        } catch (Exception $e) {
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
        
        try {
            // Check if table exists and has correct structure
            $columns = DB::select("DESCRIBE lms_curriculum_items");
            echo "✓ Table 'lms_curriculum_items' exists with " . count($columns) . " columns\n";
            
            // Check for required columns
            $requiredColumns = ['course_id', 'quiz_id', 'questionnaire_id'];
            foreach ($requiredColumns as $col) {
                $found = false;
                foreach ($columns as $column) {
                    if ($column->Field === $col) {
                        $found = true;
                        $null = $column->Null === 'YES' ? 'NULL' : 'NOT NULL';
                        echo "✓ Column '{$col}' found: {$column->Type} {$null}\n";
                        break;
                    }
                }
                if (!$found) {
                    echo "✗ Column '{$col}' not found\n";
                }
            }
            
            // Check if curriculum_id was removed
            $hasCurriculumId = false;
            foreach ($columns as $column) {
                if ($column->Field === 'curriculum_id') {
                    $hasCurriculumId = true;
                    break;
                }
            }
            
            if ($hasCurriculumId) {
                echo "⚠️  Column 'curriculum_id' still exists\n";
            } else {
                echo "✓ Column 'curriculum_id' removed successfully\n";
            }
            
            // Check existing data
            $items = DB::select("SELECT id, course_id, session_title, quiz_id, questionnaire_id FROM lms_curriculum_items WHERE course_id = 5");
            echo "✓ Found " . count($items) . " curriculum items for course 5\n";
            
        } catch (Exception $e) {
            echo "✗ Error checking table structure: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "\n✗ Database fix completed with errors. Please check the output above.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Script completed ===\n";
