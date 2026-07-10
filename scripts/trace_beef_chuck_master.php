<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Main Kitchen items where small_unit_id = medium_unit_id ===\n";
$rows = DB::table('items as i')
    ->join('categories as c', 'c.id', '=', 'i.category_id')
    ->where('c.name', 'like', '%Main Kitchen%')
    ->whereColumn('i.small_unit_id', 'i.medium_unit_id')
    ->whereNotNull('i.small_unit_id')
    ->select('i.id', 'i.name', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id', 'i.medium_conversion_qty')
    ->orderBy('i.name')
    ->get();

echo 'count=' . $rows->count() . "\n";
foreach ($rows->take(25) as $r) {
    echo "  {$r->id} {$r->name} conv={$r->medium_conversion_qty}\n";
}

echo "\n=== Beef Chuck all outlets Jul GR implied price ===\n";
$itemId = 54706;
$gr = DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_order_items as fo', function ($j) {
        $j->on('fo.item_id', '=', 'gri.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('gri.item_id', $itemId)
    ->where('ffo.id_outlet', '18')
    ->whereBetween('gr.receive_date', ['2026-07-01', '2026-07-31'])
    ->whereNull('gr.deleted_at')
    ->selectRaw('SUM(gri.qty) qty, SUM(gri.qty*fo.price) sub, GROUP_CONCAT(DISTINCT fo.price ORDER BY fo.price) prices')
    ->first();
echo "qty={$gr->qty} sub={$gr->sub} prices={$gr->prices} implied=" . ($gr->qty > 0 ? round($gr->sub / $gr->qty, 2) : 0) . "\n";

$byPrice = DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_order_items as fo', function ($j) {
        $j->on('fo.item_id', '=', 'gri.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->where('gri.item_id', $itemId)
    ->where('ffo.id_outlet', '18')
    ->whereBetween('gr.receive_date', ['2026-07-01', '2026-07-31'])
    ->whereNull('gr.deleted_at')
    ->selectRaw('fo.price, SUM(gri.qty) qty')
    ->groupBy('fo.price')
    ->get();
foreach ($byPrice as $p) {
    echo "  GR qty @ fo.price {$p->price}: {$p->qty}\n";
}
