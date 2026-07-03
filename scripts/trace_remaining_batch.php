<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$from = '2026-06-01';
$to = '2026-06-30';

foreach (['Butter Salted', 'Salad Oil'] as $itemName) {
    $item = DB::table('items')->where('name', $itemName)->first();
    $outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steakhouse SMB')->first();
    echo "=== {$itemName} SMB ===\n";

    $rows = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->leftJoin('food_floor_order_items as fo', function ($join) {
            $join->on('i.item_id', '=', 'fo.item_id')
                ->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->where('gr.outlet_id', $outlet->id_outlet)
        ->where('i.item_id', $item->id)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->select('gr.receive_date', 'i.received_qty', 'fo.id as fo_id', 'fo.price', 'fo.unit', 'do.floor_order_id')
        ->orderBy('gr.receive_date')
        ->get();

    $byPrice = [];
    foreach ($rows as $r) {
        $key = (string) $r->price;
        $byPrice[$key] = ($byPrice[$key] ?? 0) + (float) $r->received_qty;
    }
    echo "GR join price breakdown:\n";
    foreach ($byPrice as $p => $q) {
        echo "  price={$p} qty_sum={$q}\n";
    }

    $badFo = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $item->id)
        ->where('ffo.id_outlet', $outlet->id_outlet)
        ->whereBetween('ffo.tanggal', [$from, $to])
        ->where('ffoi.price', '!=', $itemName === 'Salad Oil' ? 184800 : 87600)
        ->select('ffoi.id', 'ffoi.price', 'ffo.tanggal', 'ffo.order_number', 'ffo.fo_mode', 'ffo.status')
        ->get();
    echo "FO rows wrong price in Jun:\n";
    foreach ($badFo as $b) {
        echo "  id={$b->id} price={$b->price} tanggal={$b->tanggal} mode={$b->fo_mode} status={$b->status} order={$b->order_number}\n";
    }
    echo "\n";
}
