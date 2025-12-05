<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Starting Employee Movement Approver Migration...\n";
    
    // Check if columns already exist
    $columns = Schema::getColumnListing('employee_movements');
    
    if (!in_array('hod_approver_id', $columns)) {
        echo "Adding hod_approver_id column...\n";
        DB::statement('ALTER TABLE employee_movements ADD COLUMN hod_approver_id BIGINT UNSIGNED NULL AFTER hod_approval');
    }
    
    if (!in_array('gm_approver_id', $columns)) {
        echo "Adding gm_approver_id column...\n";
        DB::statement('ALTER TABLE employee_movements ADD COLUMN gm_approver_id BIGINT UNSIGNED NULL AFTER gm_approval');
    }
    
    if (!in_array('gm_hr_approver_id', $columns)) {
        echo "Adding gm_hr_approver_id column...\n";
        DB::statement('ALTER TABLE employee_movements ADD COLUMN gm_hr_approver_id BIGINT UNSIGNED NULL AFTER gm_hr_approval');
    }
    
    if (!in_array('bod_approver_id', $columns)) {
        echo "Adding bod_approver_id column...\n";
        DB::statement('ALTER TABLE employee_movements ADD COLUMN bod_approver_id BIGINT UNSIGNED NULL AFTER bod_approval');
    }
    
    // Add foreign key constraints
    echo "Adding foreign key constraints...\n";
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD CONSTRAINT fk_employee_movements_hod_approver FOREIGN KEY (hod_approver_id) REFERENCES users(id) ON DELETE SET NULL');
    } catch (Exception $e) {
        echo "HOD approver foreign key constraint already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD CONSTRAINT fk_employee_movements_gm_approver FOREIGN KEY (gm_approver_id) REFERENCES users(id) ON DELETE SET NULL');
    } catch (Exception $e) {
        echo "GM approver foreign key constraint already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD CONSTRAINT fk_employee_movements_gm_hr_approver FOREIGN KEY (gm_hr_approver_id) REFERENCES users(id) ON DELETE SET NULL');
    } catch (Exception $e) {
        echo "GM HR approver foreign key constraint already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD CONSTRAINT fk_employee_movements_bod_approver FOREIGN KEY (bod_approver_id) REFERENCES users(id) ON DELETE SET NULL');
    } catch (Exception $e) {
        echo "BOD approver foreign key constraint already exists or failed: " . $e->getMessage() . "\n";
    }
    
    // Add indexes
    echo "Adding indexes...\n";
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD INDEX idx_hod_approver_id (hod_approver_id)');
    } catch (Exception $e) {
        echo "HOD approver index already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD INDEX idx_gm_approver_id (gm_approver_id)');
    } catch (Exception $e) {
        echo "GM approver index already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD INDEX idx_gm_hr_approver_id (gm_hr_approver_id)');
    } catch (Exception $e) {
        echo "GM HR approver index already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        DB::statement('ALTER TABLE employee_movements ADD INDEX idx_bod_approver_id (bod_approver_id)');
    } catch (Exception $e) {
        echo "BOD approver index already exists or failed: " . $e->getMessage() . "\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
