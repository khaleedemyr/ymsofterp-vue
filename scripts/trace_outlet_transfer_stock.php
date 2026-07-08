<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$transferNumber = $argv[1] ?? 'OT-20260701-0002';
$transfer = DB::table('outlet_transfers')->where('transfer_number', $transferNumber)->first();
if (!$transfer) {
    echo "NOT FOUND\n";
    exit(1);
}

$wf = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
$wt = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();
$inv = DB::table('outlet_food_inventory_items')->where('item_id', 52994)->first();

echo "From outlet {$wf->outlet_id} warehouse {$wf->id}\n";
echo "To outlet {$wt->outlet_id} warehouse {$wt->id}\n";
echo "Inventory item id: {$inv->id}\n\n";

foreach ([$wf, $wt] as $wh) {
    $stock = DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', $inv->id)
        ->where('id_outlet', $wh->outlet_id)
        ->where('warehouse_outlet_id', $wh->id)
        ->first();
    echo "Stock wh {$wh->id} outlet {$wh->outlet_id}: " . json_encode($stock) . "\n";
}
