<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = 52537;
$inv = DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->first();
echo 'inventory_item_id=' . $inv->id . PHP_EOL;

$stocks = DB::table('outlet_food_inventory_stocks as s')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 's.id_outlet')
    ->where('s.inventory_item_id', $inv->id)
    ->orderBy('s.id_outlet')
    ->orderBy('s.warehouse_outlet_id')
    ->get([
        's.id', 's.id_outlet', 'o.nama_outlet', 's.warehouse_outlet_id',
        's.qty_small', 's.last_cost_small', 's.last_cost_medium', 's.value',
    ]);

foreach ($stocks as $s) {
    echo json_encode($s, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$draft = DB::table('outlet_internal_use_waste_headers')->where('id', 28583)->first();
if ($draft) {
    echo PHP_EOL . 'Draft 28583: ' . json_encode($draft) . PHP_EOL;
    $lines = DB::table('outlet_internal_use_waste_items as i')
        ->join('items as it', 'it.id', '=', 'i.item_id')
        ->where('i.header_id', 28583)
        ->get(['i.*', 'it.name']);
    foreach ($lines as $l) {
        if ((int) $l->item_id === $itemId) {
            echo 'CIU line: ' . json_encode($l) . PHP_EOL;
        }
    }
}
