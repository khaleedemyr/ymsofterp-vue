<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = 54667;
$outletId = '18';

$rows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->leftJoin('users as u', 'u.id', '=', 'ffo.user_id')
    ->where('ffoi.item_id', $itemId)
    ->where('ffo.id_outlet', $outletId)
    ->whereBetween('ffo.tanggal', ['2026-07-01', '2026-07-10'])
    ->select(
        'ffo.tanggal',
        'ffo.order_number',
        'ffoi.price',
        'ffo.fo_schedule_id',
        'ffo.warehouse_outlet_id',
        'ffo.user_id',
        'ffo.input_mode',
    )
    ->orderBy('ffo.tanggal')
    ->get();

echo "tanggal | price | schedule | warehouse | user | input\n";
foreach ($rows as $r) {
    $flag = (float) $r->price > 50000 ? '***WRONG***' : 'ok';
    echo "{$r->tanggal} | {$r->price} | sched={$r->fo_schedule_id} | wh={$r->warehouse_outlet_id} | user={$r->user_id} | {$r->input_mode} | {$flag}\n";
}
