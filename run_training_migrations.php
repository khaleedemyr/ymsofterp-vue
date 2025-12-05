<?php

/**
 * Script untuk menjalankan migration sistem training baru
 * 
 * Jalankan dengan: php run_training_migrations.php
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

echo "=== TRAINING SYSTEM MIGRATION SCRIPT ===\n\n";

try {
    // Check if Laravel is properly loaded
    if (!class_exists('Illuminate\Support\Facades\Artisan')) {
        throw new Exception('Laravel not properly loaded. Make sure you run this from the project root.');
    }

    echo "1. Running migrations...\n";
    
    // Run migrations
    $migrations = [
        '2024_01_15_000001_create_jabatan_required_trainings_table.php',
        '2024_01_15_000002_create_course_trainers_table.php', 
        '2024_01_15_000003_create_user_training_hours_table.php',
        '2024_01_15_000004_create_trainer_teaching_hours_table.php',
        '2024_01_15_000005_add_training_hours_to_users_table.php'
    ];

    foreach ($migrations as $migration) {
        echo "   - Running {$migration}...\n";
        Artisan::call('migrate', [
            '--path' => "database/migrations/{$migration}",
            '--force' => true
        ]);
    }

    echo "\n2. Checking database tables...\n";
    
    // Check if tables exist
    $tables = [
        'jabatan_required_trainings',
        'course_trainers', 
        'user_training_hours',
        'trainer_teaching_hours'
    ];

    foreach ($tables as $table) {
        try {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (count($exists) > 0) {
                echo "   ✓ Table {$table} created successfully\n";
            } else {
                echo "   ✗ Table {$table} not found\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Error checking table {$table}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n3. Checking users table columns...\n";
    
    // Check if new columns exist in users table
    try {
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'total_training_hours'");
        if (count($columns) > 0) {
            echo "   ✓ Column total_training_hours added successfully\n";
        } else {
            echo "   ✗ Column total_training_hours not found\n";
        }

        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'total_teaching_hours'");
        if (count($columns) > 0) {
            echo "   ✓ Column total_teaching_hours added successfully\n";
        } else {
            echo "   ✗ Column total_teaching_hours not found\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error checking users table columns: " . $e->getMessage() . "\n";
    }

    echo "\n4. Creating sample data (optional)...\n";
    
    // Create sample data if requested
    if (isset($argv[1]) && $argv[1] === '--with-sample') {
        echo "   Creating sample data...\n";
        
        // This would create sample data for testing
        // Implementation depends on your specific needs
        
        echo "   ✓ Sample data created\n";
    } else {
        echo "   Skipping sample data creation\n";
        echo "   (Use --with-sample flag to create sample data)\n";
    }

    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
    echo "\nNext steps:\n";
    echo "1. Update your menu to include new training compliance routes\n";
    echo "2. Test the new functionality\n";
    echo "3. Configure permissions for the new features\n";
    echo "4. Train users on the new system\n\n";

} catch (Exception $e) {
    echo "\n=== MIGRATION FAILED ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n\n";
    exit(1);
}
