<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

foreach ([68 => 27, 47 => 20] as $wh => $outlet) {
    $s = DB::table('outlet_food_inventory_stocks')
        ->where('inventory_item_id', 4579)
        ->where('warehouse_outlet_id', $wh)
        ->where('id_outlet', $outlet)
        ->first();
    echo "wh {$wh} outlet {$outlet}: qty_small={$s->qty_small}, value={$s->value}\n";
}

$exists = DB::table('outlet_transfers')->where('transfer_number', 'OT-20260701-0002')->exists();
echo 'transfer_exists=' . ($exists ? 'yes' : 'no') . "\n";
