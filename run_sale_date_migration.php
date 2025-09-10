<?php

/**
 * Script untuk menambahkan kolom sale_date ke tabel retail_warehouse_sales
 * Jalankan script ini untuk update database
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "[" . date('Y-m-d H:i:s') . "] Starting sale_date migration...\n";

try {
    // Check if column already exists
    $columns = DB::select("SHOW COLUMNS FROM retail_warehouse_sales LIKE 'sale_date'");
    
    if (count($columns) > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Column 'sale_date' already exists. Skipping migration.\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Adding sale_date column...\n";
    
    // Add sale_date column
    DB::statement("ALTER TABLE retail_warehouse_sales ADD COLUMN sale_date DATE NOT NULL DEFAULT (CURDATE()) AFTER customer_id");
    
    echo "[" . date('Y-m-d H:i:s') . "] ✓ Column added successfully\n";
    
    // Update existing records
    echo "[" . date('Y-m-d H:i:s') . "] Updating existing records...\n";
    
    $updated = DB::update("UPDATE retail_warehouse_sales SET sale_date = DATE(created_at) WHERE sale_date IS NULL OR sale_date = '0000-00-00'");
    
    echo "[" . date('Y-m-d H:i:s') . "] ✓ Updated {$updated} existing records\n";
    
    // Add indexes
    echo "[" . date('Y-m-d H:i:s') . "] Adding indexes...\n";
    
    try {
        DB::statement("CREATE INDEX idx_retail_warehouse_sales_sale_date ON retail_warehouse_sales(sale_date)");
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Index on sale_date created\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "[" . date('Y-m-d H:i:s') . "] Index on sale_date already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        DB::statement("CREATE INDEX idx_retail_warehouse_sales_date_customer ON retail_warehouse_sales(sale_date, customer_id)");
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Index on sale_date + customer_id created\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "[" . date('Y-m-d H:i:s') . "] Index on sale_date + customer_id already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Verify the migration
    echo "[" . date('Y-m-d H:i:s') . "] Verifying migration...\n";
    
    $columns = DB::select("SHOW COLUMNS FROM retail_warehouse_sales LIKE 'sale_date'");
    if (count($columns) > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Column verification successful\n";
    } else {
        throw new Exception("Column verification failed");
    }
    
    // Show sample data
    $sample = DB::select("SELECT id, number, sale_date, created_at FROM retail_warehouse_sales ORDER BY id DESC LIMIT 3");
    echo "[" . date('Y-m-d H:i:s') . "] Sample data:\n";
    foreach ($sample as $row) {
        echo "[" . date('Y-m-d H:i:s') . "] ID: {$row->id}, Number: {$row->number}, Sale Date: {$row->sale_date}, Created: {$row->created_at}\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ✗ Migration failed: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
