<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$name = $argv[1] ?? 'Artisan Tea Ceylon Green Tea';
$outlet = $argv[2] ?? 'Justus Steakhouse SMB';
$from = '2026-06-01';
$to = '2026-06-30';

$itemId = DB::table('items')->where('name', $name)->value('id');
echo "Item: {$name} ({$itemId}) outlet={$outlet}\n\n";

$rows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $outlet)
    ->where('i.item_id', $itemId)
    ->whereBetween('gr.receive_date', [$from, $to])
    ->whereNull('gr.deleted_at')
    ->select(
        'gr.id as gr_id',
        'gr.receive_date',
        'gr.id',
        'i.received_qty',
        'do.floor_order_id',
        'fo.id as fo_line_id',
        'fo.price as fo_price',
        'fo.qty as fo_qty',
        'fo.unit as fo_unit',
    )
    ->orderBy('gr.receive_date')
    ->get();

echo "Joined rows: " . $rows->count() . "\n";
foreach ($rows as $r) {
    echo "{$r->receive_date} gr_id={$r->gr_id} recv_qty={$r->received_qty} fo_id={$r->fo_line_id} fo_price={$r->fo_price} fo_unit={$r->fo_unit}\n";
}

$dupFo = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('ffoi.item_id', $itemId)
    ->where('o.nama_outlet', $outlet)
    ->whereBetween('ffo.tanggal', [$from, $to])
    ->selectRaw('ffoi.floor_order_id, ffo.order_number, COUNT(*) as cnt, GROUP_CONCAT(ffoi.price) as prices')
    ->groupBy('ffoi.floor_order_id', 'ffo.order_number')
    ->havingRaw('COUNT(*) > 1')
    ->get();
echo "\nDuplicate FO lines same item+order:\n";
foreach ($dupFo as $d) {
    echo "order={$d->order_number} floor_order_id={$d->floor_order_id} cnt={$d->cnt} prices={$d->prices}\n";
}
