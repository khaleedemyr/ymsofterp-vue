<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = (int) DB::table('items')->where('name', 'Thai Dressing')->value('id');

$rows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('i.item_id', $itemId)
    ->whereDate('gr.receive_date', '2026-06-14')
    ->whereNull('gr.deleted_at')
    ->select(
        'gr.number as gr_number',
        'gr.receive_date',
        'ffo.order_number',
        'ffo.tanggal as fo_tanggal',
        'ffo.status as fo_status',
        'o.nama_outlet',
        'fo.id as fo_item_id',
        'fo.price',
        'i.received_qty'
    )
    ->orderBy('fo.price', 'desc')
    ->limit(20)
    ->get();

foreach ($rows as $r) {
    echo "{$r->receive_date} GR {$r->gr_number} | RO {$r->order_number} (tanggal {$r->fo_tanggal}, {$r->fo_status}) | {$r->nama_outlet} | fo#{$r->fo_item_id} price={$r->price} qty={$r->received_qty}\n";
}
