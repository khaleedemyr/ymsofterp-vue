<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$transferNumber = $argv[1] ?? 'OT-20260701-0002';

$transfer = DB::table('outlet_transfers')->where('transfer_number', $transferNumber)->first();
if (!$transfer) {
    echo "Transfer {$transferNumber} NOT FOUND\n";
    exit(1);
}

echo "=== OUTLET TRANSFER ===\n";
echo json_encode($transfer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$id = (int) $transfer->id;

$tables = [
    'outlet_transfer_items' => DB::table('outlet_transfer_items')->where('outlet_transfer_id', $id)->get(),
    'outlet_transfer_serial_items' => DB::table('outlet_transfer_serial_items')->where('outlet_transfer_id', $id)->get(),
    'outlet_transfer_approval_flows' => DB::table('outlet_transfer_approval_flows')->where('outlet_transfer_id', $id)->get(),
    'inventory_serial_movements' => DB::table('inventory_serial_movements')->where('outlet_transfer_id', $id)->get(),
    'outlet_food_inventory_cards' => DB::table('outlet_food_inventory_cards')
        ->where('reference_type', 'outlet_transfer')->where('reference_id', $id)->get(),
    'outlet_food_inventory_cost_histories' => DB::table('outlet_food_inventory_cost_histories')
        ->where('reference_type', 'outlet_transfer')->where('reference_id', $id)->get(),
];

foreach ($tables as $name => $rows) {
    echo "=== {$name} (" . $rows->count() . " rows) ===\n";
    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}

// Serials linked via transfer_id
$serials = DB::table('inventory_item_serials')->where('transfer_id', $id)->get();
echo '=== inventory_item_serials (transfer_id) (' . $serials->count() . " rows) ===\n";
foreach ($serials as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

// Activity logs
$logs = DB::table('activity_logs')->where('module', 'outlet_transfer')
    ->where(function ($q) use ($id, $transferNumber) {
        $q->where('description', 'like', "%{$transferNumber}%")
          ->orWhere('old_value', 'like', "%{$transferNumber}%");
    })->get();
echo "\n=== activity_logs (" . $logs->count() . " rows) ===\n";
foreach ($logs as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

// Notifications
$notifs = DB::table('notifications')->where('message', 'like', "%{$transferNumber}%")->get();
echo "\n=== notifications (" . $notifs->count() . " rows) ===\n";
foreach ($notifs as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
