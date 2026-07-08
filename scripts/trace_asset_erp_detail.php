<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$refIds = [
    'asset_disposal' => [1, 2],
    'asset_stock_adjustment' => [1, 2, 3, 4, 5],
    'asset_owner_transfer' => [5, 10, 11],
    'asset_inventory_transfer' => [5, 6],
    'asset_good_receive' => [2],
];

foreach ($refIds as $type => $ids) {
    echo "\n=== {$type} ===\n";
    foreach ($ids as $id) {
        $cards = DB::table('asset_inventory_cards')
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->count();
        $costs = DB::table('asset_inventory_cost_histories')
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->count();
        echo "ref_id={$id} cards={$cards} cost_hist={$costs}\n";
    }
}

echo "\n=== initial_balance cards for target stocks ===\n";
$stockIds = [20, 15, 14, 13, 11, 9];
foreach ($stockIds as $sid) {
    $stock = DB::table('asset_inventory_stocks as s')
        ->join('items as i', 's.inventory_item_id', '=', DB::raw('(select inventory_item_id from asset_inventory_stocks where id='.$sid.')'))
        ->where('s.id', $sid)->first();
    $stock = DB::table('asset_inventory_stocks')->where('id', $sid)->first();
    if (!$stock) { echo "stock {$sid} not found\n"; continue; }
    $cards = DB::table('asset_inventory_cards')
        ->where('inventory_item_id', $stock->inventory_item_id)
        ->where('owner_outlet_id', $stock->owner_outlet_id)
        ->where('warehouse_outlet_id', $stock->warehouse_outlet_id)
        ->where('reference_type', 'initial_balance')
        ->get(['id','date','in_qty_small','saldo_qty_small']);
    echo "stock_id={$sid} inv_item={$stock->inventory_item_id} initial_balance_cards=" . $cards->count() . "\n";
    foreach ($cards as $c) {
        echo "  card {$c->id} date={$c->date} in={$c->in_qty_small} saldo={$c->saldo_qty_small}\n";
    }
}

echo "\n=== GR items ===\n";
$items = DB::table('asset_good_receive_items')->where('asset_good_receive_id', 2)->get();
foreach ($items as $i) {
    $name = DB::table('items')->where('id', $i->item_id)->value('name');
    echo "item={$name} qty={$i->qty_received} unit={$i->unit_id}\n";
}
