<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Checking Ticket System Tables ===\n";

$tables = [
    'ticket_statuses',
    'ticket_priorities', 
    'ticket_categories',
    'tickets',
    'ticket_attachments',
    'ticket_comments',
    'ticket_history'
];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "✅ Table '{$table}' exists with {$count} records\n";
    } else {
        echo "❌ Table '{$table}' does NOT exist\n";
    }
}

echo "\n=== Checking Required Data ===\n";

// Check ticket statuses
$statusCount = DB::table('ticket_statuses')->count();
if ($statusCount == 0) {
    echo "❌ No ticket statuses found - need to run insert_ticket_default_data.sql\n";
} else {
    echo "✅ Found {$statusCount} ticket statuses\n";
    $openStatus = DB::table('ticket_statuses')->where('slug', 'open')->first();
    if ($openStatus) {
        echo "✅ Default 'open' status found\n";
    } else {
        echo "❌ Default 'open' status not found\n";
    }
}

// Check ticket priorities
$priorityCount = DB::table('ticket_priorities')->count();
if ($priorityCount == 0) {
    echo "❌ No ticket priorities found - need to run insert_ticket_default_data.sql\n";
} else {
    echo "✅ Found {$priorityCount} ticket priorities\n";
}

echo "\n=== Recommendations ===\n";
echo "1. If tables missing: Run database/sql/create_ticketing_system_tables.sql\n";
echo "2. If no data: Run database/sql/insert_ticket_default_data.sql\n";
echo "3. If max_days missing: Run database/sql/update_ticket_priorities_add_max_days.sql\n";
