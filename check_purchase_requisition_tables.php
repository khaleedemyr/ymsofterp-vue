<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking Purchase Requisition Tables...\n\n";

// Check if purchase_requisitions table exists
if (Schema::hasTable('purchase_requisitions')) {
    echo "✅ purchase_requisitions table exists\n";
    
    // Check columns
    $columns = Schema::getColumnListing('purchase_requisitions');
    echo "Columns: " . implode(', ', $columns) . "\n\n";
    
    // Check if required columns exist
    $requiredColumns = ['division_id', 'category_id', 'outlet_id', 'ticket_id', 'title', 'description', 'amount', 'priority'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns)) {
            $missingColumns[] = $column;
        }
    }
    
    if (empty($missingColumns)) {
        echo "✅ All required columns exist\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
        echo "Please run: source database/sql/alter_purchase_requisitions_table.sql\n";
    }
} else {
    echo "❌ purchase_requisitions table does not exist\n";
    echo "Please run: source database/sql/create_purchase_requisition_tables.sql\n";
}

// Check other required tables
$requiredTables = [
    'purchase_requisition_categories',
    'purchase_requisition_attachments', 
    'purchase_requisition_comments',
    'purchase_requisition_history',
    'division_budgets'
];

echo "\nChecking other required tables:\n";
foreach ($requiredTables as $table) {
    if (Schema::hasTable($table)) {
        echo "✅ $table table exists\n";
    } else {
        echo "❌ $table table does not exist\n";
    }
}

echo "\nDone!\n";
