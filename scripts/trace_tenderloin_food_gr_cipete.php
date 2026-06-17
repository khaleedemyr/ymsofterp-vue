<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$customer = 'Justus Steak House Cipete';
$from = '2026-06-01';
$to = '2026-06-30';

foreach ([52984, 52985] as $itemId) {
    $name = DB::table('items')->where('id', $itemId)->value('name');
    echo "\n=== Food GR: {$name} ===\n";

    $rows = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->leftJoin('food_floor_order_items as fo', function ($join) {
            $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
        ->where('o.nama_outlet', $customer)
        ->where('i.item_id', $itemId)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->select('gr.receive_date', 'i.received_qty', 'fo.price', 'i.unit_id')
        ->orderByDesc('gr.receive_date')
        ->limit(10)
        ->get();

    foreach ($rows as $r) {
        echo "{$r->receive_date} qty={$r->received_qty} fo.price={$r->price}\n";
    }

    $agg = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->leftJoin('food_floor_order_items as fo', function ($join) {
            $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
        ->where('o.nama_outlet', $customer)
        ->where('i.item_id', $itemId)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->selectRaw('SUM(i.received_qty) as qty, AVG(COALESCE(fo.price,0)) as avg_price, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal')
        ->first();

    echo "Report-style Food: qty={$agg->qty} avg_price=" . number_format((float) $agg->avg_price, 2) . " subtotal=" . number_format((float) $agg->subtotal, 2) . "\n";
}

echo "\nDone.\n";
