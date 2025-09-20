<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Illuminate\Support\Facades\DB;

echo "=== Fixing Ticket System Data ===\n";

try {
    DB::beginTransaction();

    // Check and create ticket statuses
    echo "Checking ticket statuses...\n";
    $statusCount = TicketStatus::count();
    
    if ($statusCount == 0) {
        echo "Creating default ticket statuses...\n";
        
        $statuses = [
            ['name' => 'Open', 'slug' => 'open', 'color' => '#3B82F6', 'description' => 'Tiket baru, belum diproses', 'is_final' => 0, 'order' => 1, 'status' => 'A'],
            ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#F59E0B', 'description' => 'Tiket sedang dalam proses penanganan', 'is_final' => 0, 'order' => 2, 'status' => 'A'],
            ['name' => 'Pending', 'slug' => 'pending', 'color' => '#8B5CF6', 'description' => 'Tiket menunggu informasi atau approval', 'is_final' => 0, 'order' => 3, 'status' => 'A'],
            ['name' => 'Resolved', 'slug' => 'resolved', 'color' => '#10B981', 'description' => 'Tiket sudah diselesaikan, menunggu konfirmasi', 'is_final' => 0, 'order' => 4, 'status' => 'A'],
            ['name' => 'Closed', 'slug' => 'closed', 'color' => '#6B7280', 'description' => 'Tiket sudah ditutup', 'is_final' => 1, 'order' => 5, 'status' => 'A'],
            ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#EF4444', 'description' => 'Tiket dibatalkan', 'is_final' => 1, 'order' => 6, 'status' => 'A'],
        ];
        
        foreach ($statuses as $status) {
            TicketStatus::create($status);
            echo "  ✅ Created status: {$status['name']}\n";
        }
    } else {
        echo "✅ Found {$statusCount} ticket statuses\n";
    }

    // Check and create ticket priorities
    echo "\nChecking ticket priorities...\n";
    $priorityCount = TicketPriority::count();
    
    if ($priorityCount == 0) {
        echo "Creating default ticket priorities...\n";
        
        $priorities = [
            ['name' => 'Low', 'level' => 1, 'max_days' => 14, 'color' => '#10B981', 'description' => 'Prioritas rendah, bisa diselesaikan dalam beberapa hari', 'status' => 'A'],
            ['name' => 'Medium', 'level' => 2, 'max_days' => 7, 'color' => '#F59E0B', 'description' => 'Prioritas sedang, harus diselesaikan dalam 1-2 hari', 'status' => 'A'],
            ['name' => 'High', 'level' => 3, 'max_days' => 3, 'color' => '#EF4444', 'description' => 'Prioritas tinggi, harus diselesaikan dalam beberapa jam', 'status' => 'A'],
            ['name' => 'Critical', 'level' => 4, 'max_days' => 1, 'color' => '#DC2626', 'description' => 'Prioritas kritis, harus diselesaikan segera', 'status' => 'A'],
        ];
        
        foreach ($priorities as $priority) {
            TicketPriority::create($priority);
            echo "  ✅ Created priority: {$priority['name']}\n";
        }
    } else {
        echo "✅ Found {$priorityCount} ticket priorities\n";
        
        // Check if max_days column exists and update if needed
        $firstPriority = TicketPriority::first();
        if (!isset($firstPriority->max_days)) {
            echo "Adding max_days to existing priorities...\n";
            TicketPriority::where('name', 'Low')->update(['max_days' => 14]);
            TicketPriority::where('name', 'Medium')->update(['max_days' => 7]);
            TicketPriority::where('name', 'High')->update(['max_days' => 3]);
            TicketPriority::where('name', 'Critical')->update(['max_days' => 1]);
            echo "  ✅ Updated max_days for existing priorities\n";
        }
    }

    DB::commit();
    echo "\n✅ All ticket system data fixed successfully!\n";
    
    // Verify
    echo "\n=== Verification ===\n";
    echo "Statuses: " . TicketStatus::count() . "\n";
    echo "Priorities: " . TicketPriority::count() . "\n";
    
    $openStatus = TicketStatus::where('slug', 'open')->first();
    if ($openStatus) {
        echo "✅ Default 'open' status found: {$openStatus->name}\n";
    } else {
        echo "❌ Default 'open' status still not found\n";
    }

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
