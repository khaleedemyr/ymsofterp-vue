<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$inv = DB::table('food_inventory_items')->where('item_id', 53235)->first();
$hist = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', $inv->id)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->limit(8)
    ->get(['warehouse_id','date','mac','new_cost','qty_in','qty_out','id']);
foreach ($hist as $h) {
    echo "hist id={$h->id} wh={$h->warehouse_id} date={$h->date} mac={$h->mac} new_cost={$h->new_cost}\n";
}
