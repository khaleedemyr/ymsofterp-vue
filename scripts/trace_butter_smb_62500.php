<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$item = DB::table('items')->where('name', 'Butter Salted')->first();
$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steakhouse SMB')->first();
$from = '2026-06-01';
$to = '2026-06-30';

$rows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'fo.floor_order_id')
    ->where('gr.outlet_id', $outlet->id_outlet)
    ->where('i.item_id', $item->id)
    ->whereBetween('gr.receive_date', [$from, $to])
    ->whereNull('gr.deleted_at')
    ->where('fo.price', 62500)
    ->select('gr.receive_date', 'i.received_qty', 'fo.id as fo_id', 'fo.price', 'ffo.tanggal', 'ffo.order_number', 'ffo.fo_mode', 'do.floor_order_id')
    ->get();

echo 'Rows with price 62500: ' . $rows->count() . "\n";
foreach ($rows as $r) {
    echo "gr={$r->receive_date} recv={$r->received_qty} fo_id={$r->fo_id} price={$r->price} fo_tanggal={$r->tanggal} order={$r->order_number}\n";
}

// duplicate FO lines per order
$dupes = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $item->id)
    ->where('ffo.id_outlet', $outlet->id_outlet)
    ->select('ffoi.floor_order_id', DB::raw('COUNT(*) as cnt'), DB::raw('GROUP_CONCAT(CONCAT(ffoi.id,":",ffoi.price) ORDER BY ffoi.id) as lines'))
    ->groupBy('ffoi.floor_order_id')
    ->having('cnt', '>', 1)
    ->get();
echo "\nDuplicate FO lines same order+item: " . $dupes->count() . "\n";
foreach ($dupes->take(10) as $d) {
    echo "  order_id={$d->floor_order_id} lines={$d->lines}\n";
}
