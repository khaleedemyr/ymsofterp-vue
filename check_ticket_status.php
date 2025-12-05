<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TicketStatus;

echo "=== Checking Ticket Statuses ===\n";

$statuses = TicketStatus::all();

if ($statuses->count() == 0) {
    echo "❌ No ticket statuses found in database!\n";
    echo "Need to run: database/sql/insert_ticketing_system_default_data.sql\n";
} else {
    echo "✅ Found " . $statuses->count() . " ticket statuses:\n";
    foreach ($statuses as $status) {
        echo "  - ID: {$status->id}, Name: {$status->name}, Slug: {$status->slug}\n";
    }
    
    // Check for 'open' status
    $openStatus = TicketStatus::where('slug', 'open')->first();
    if ($openStatus) {
        echo "✅ Default 'open' status found: {$openStatus->name}\n";
    } else {
        echo "❌ Default 'open' status NOT found!\n";
        echo "Available slugs: " . $statuses->pluck('slug')->implode(', ') . "\n";
    }
}

echo "\n=== Checking Ticket Priorities ===\n";

use App\Models\TicketPriority;

$priorities = TicketPriority::all();

if ($priorities->count() == 0) {
    echo "❌ No ticket priorities found in database!\n";
} else {
    echo "✅ Found " . $priorities->count() . " ticket priorities:\n";
    foreach ($priorities as $priority) {
        echo "  - ID: {$priority->id}, Name: {$priority->name}, Level: {$priority->level}, Max Days: " . ($priority->max_days ?? 'NULL') . "\n";
    }
}

echo "\n=== Recommendations ===\n";
echo "1. Run: database/sql/insert_ticketing_system_default_data.sql\n";
echo "2. Run: database/sql/update_ticket_priorities_add_max_days.sql\n";
echo "3. Check if tables exist: ticket_statuses, ticket_priorities\n";
