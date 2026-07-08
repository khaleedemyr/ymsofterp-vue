<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$names = ['Steak Fork', 'Souffle Dish Cream / MK 10 AAB', 'Sauce Dish 40ml / MK 04', 'Dinner Fork'];
foreach ($names as $n) {
    $i = DB::table('items')->where('name', $n)->first(['id','name','sku']);
    echo ($i ? json_encode($i) : "NOT FOUND: $n") . "\n";
}

// all cards for these inventory items
$invIds = [12,10,11,2];
foreach ($invIds as $invId) {
    $cards = DB::table('asset_inventory_cards')->where('inventory_item_id', $invId)->orderBy('date')->orderBy('id')->get(['id','date','reference_type','reference_id','in_qty_small','out_qty_small','saldo_qty_small','owner_outlet_id','warehouse_outlet_id']);
    echo "\ninv_item {$invId} cards: " . $cards->count() . "\n";
    foreach ($cards as $c) {
        echo "  {$c->id} {$c->date} {$c->reference_type}#{$c->reference_id} in={$c->in_qty_small} out={$c->out_qty_small} saldo={$c->saldo_qty_small} owner={$c->owner_outlet_id} wh={$c->warehouse_outlet_id}\n";
    }
}
