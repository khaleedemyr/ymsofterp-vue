<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$checks = [
    ['Artisan Tea Ceylon Green Tea', 218500],
    ['Artisan Tea Ginger & Mint', 233900],
    ['Kacang Arab', 41100],
    ['Kacang Arab', 47100],
    ['Kacang Arab', 39100],
    ['Cup 12 Logo SH', 139133],
];

foreach ($checks as [$name, $badPrice]) {
    $id = DB::table('items')->where('name', $name)->value('id');
    echo "=== {$name} bad price {$badPrice} ===\n";
    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->where('ffoi.item_id', $id)
        ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
        ->where('ffoi.price', $badPrice)
        ->select(
            'ffoi.id',
            'ffo.order_number',
            'ffo.tanggal',
            'o.nama_outlet',
            'ffoi.qty',
            'ffoi.price',
            'ffoi.unit',
            'ffo.status',
            'ffo.fo_mode'
        )
        ->get();
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo 'count=' . $rows->count() . "\n\n";
}

foreach (['Salad Oil', 'Vetcin Powder', 'Kecap Asin Angsa'] as $name) {
    $id = DB::table('items')->where('name', $name)->value('id');
    echo "=== {$name} item_prices ===\n";
    foreach (DB::table('item_prices')->where('item_id', $id)->orderByDesc('id')->limit(5)->get() as $p) {
        echo json_encode($p, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}
