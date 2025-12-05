<?php
/**
 * Refactor Curriculum Structure
 * Script untuk menjalankan refactor struktur kurikulum yang lebih fleksibel
 */

echo "=== Refactor Curriculum Structure ===\n\n";

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
    $sqlFile = 'refactor_curriculum_structure.sql';
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
        echo "\n✓ Curriculum refactor completed successfully!\n";
        
        // Verify the refactor
        echo "\n=== Verifying Refactor ===\n";
        
        try {
            // Check if new tables exist
            $tables = ['lms_sessions', 'lms_session_items'];
            foreach ($tables as $table) {
                try {
                    $columns = DB::select("DESCRIBE $table");
                    echo "✓ Table '$table' exists with " . count($columns) . " columns\n";
                } catch (Exception $e) {
                    echo "✗ Table '$table' error: " . $e->getMessage() . "\n";
                }
            }
            
            // Check migrated data
            try {
                $sessions = DB::select("SELECT COUNT(*) as count FROM lms_sessions WHERE course_id = 5");
                echo "✓ Migrated sessions count: " . $sessions[0]->count . "\n";
                
                $sessionsData = DB::select("SELECT id, session_title, session_number FROM lms_sessions WHERE course_id = 5");
                foreach ($sessionsData as $session) {
                    echo "  - Session {$session->session_number}: {$session->session_title}\n";
                }
            } catch (Exception $e) {
                echo "✗ Error checking migrated data: " . $e->getMessage() . "\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Error checking refactor: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "\n✗ Curriculum refactor completed with errors. Please check the output above.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Script completed ===\n";
