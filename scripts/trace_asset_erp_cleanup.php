<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$numbers = [
    'gr' => ['AGR202606060001'],
    'ait' => ['AIT-20260609-0002', 'AIT-20260609-0001'],
    'aot' => ['AOT-20260610-0001', 'AOT-20260609-0002', 'AOT-20260609-0001'],
    'asa' => ['ASA202606110002', 'ASA202606110001', 'ASA202606100002', 'ASA202606100001', 'ASA202605210001'],
    'adp' => ['ADP202606100002', 'ADP202606100001'],
    'serials' => ['ASSTS-20260429-5823', 'ASSTK-20260429-9039', 'ASSTK-20260429-3742', 'ASSTS-20260429-2922'],
];

foreach ($numbers['gr'] as $n) {
    $r = DB::table('asset_good_receives')->where('gr_number', $n)->first();
    echo "GR {$n}: " . ($r ? json_encode(['id' => $r->id, 'status' => $r->status]) : 'NOT FOUND') . "\n";
}
foreach ($numbers['ait'] as $n) {
    $r = DB::table('asset_inventory_transfers')->where('transfer_number', $n)->first();
    echo "AIT {$n}: " . ($r ? json_encode(['id' => $r->id, 'status' => $r->status]) : 'NOT FOUND') . "\n";
}
foreach ($numbers['aot'] as $n) {
    $r = DB::table('asset_owner_transfers')->where('transfer_number', $n)->first();
    echo "AOT {$n}: " . ($r ? json_encode(['id' => $r->id, 'status' => $r->status]) : 'NOT FOUND') . "\n";
}
foreach ($numbers['asa'] as $n) {
    $r = DB::table('asset_inventory_adjustments')->where('number', $n)->first();
    echo "ASA {$n}: " . ($r ? json_encode(['id' => $r->id, 'status' => $r->status, 'type' => $r->type]) : 'NOT FOUND') . "\n";
}
foreach ($numbers['adp'] as $n) {
    $r = DB::table('asset_disposals')->where('number', $n)->first();
    echo "ADP {$n}: " . ($r ? json_encode(['id' => $r->id, 'status' => $r->status]) : 'NOT FOUND') . "\n";
}

echo "\n--- Serials ---\n";
if (DB::getSchemaBuilder()->hasTable('asset_inventory_serials')) {
    foreach ($numbers['serials'] as $n) {
        $r = DB::table('asset_inventory_serials')->where('serial_number', $n)->first();
        echo "SERIAL {$n}: " . ($r ? json_encode($r, JSON_UNESCAPED_UNICODE) : 'NOT FOUND') . "\n";
    }
}

echo "\n--- Saldo awal stocks (Dago related items) ---\n";
$items = DB::table('items')
    ->whereIn('name', ['Steak Fork', 'Souffle Dish Cream / MK 10 AAB', 'Sauce Dish 40ml / MK 04', 'Dinner Fork'])
    ->orWhere('name', 'like', '%Steak Fork%')
    ->orWhere('name', 'like', '%Souffle Dish%')
    ->orWhere('name', 'like', '%Sauce Dish%')
    ->orWhere('name', 'like', '%Dinner Fork%')
    ->get(['id', 'name', 'sku']);

foreach ($items as $item) {
    $inv = DB::table('asset_inventory_items')->where('item_id', $item->id)->first();
    if (!$inv) continue;
    $stocks = DB::table('asset_inventory_stocks as s')
        ->join('tbl_data_outlet as o', 's.owner_outlet_id', '=', 'o.id_outlet')
        ->join('warehouse_outlets as w', 's.warehouse_outlet_id', '=', 'w.id')
        ->where('s.inventory_item_id', $inv->id)
        ->select('s.*', 'o.nama_outlet', 'w.name as warehouse_name')
        ->get();
    foreach ($stocks as $s) {
        echo "{$item->name} | stock_id={$s->id} owner={$s->nama_outlet} wh={$s->warehouse_name} qty={$s->qty_small}\n";
    }
}
