<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$itemId = 53088;

echo "=== Retail unit=Gram (all) ===\n";
$rows = DB::table('retail_food_items as rfi')
    ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
    ->where('rfi.item_name', 'Cabe Rawit Merah')
    ->where('rfi.unit', 'Gram')
    ->orderByDesc('rf.transaction_date')
    ->get(['rf.retail_number', 'rf.transaction_date', 'rf.status', 'rfi.qty', 'rfi.unit', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
foreach ($rows as $r) {
    echo sprintf("%s %s %s outlet=%s qty=%s %s @ %s sub=%s  => Rp/kg=%s\n",
        $r->transaction_date, $r->retail_number, $r->status, $r->nama_outlet,
        $r->qty, $r->unit, number_format($r->price, 2, ',', '.'), number_format($r->subtotal, 2, ',', '.'),
        number_format($r->price * 1000, 0, ',', '.')
    );
}

echo "\n=== Retail 2026 price/kg > 150000 or < 20000 ===\n";
$rows = DB::table('retail_food_items as rfi')
    ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
    ->where('rfi.item_name', 'Cabe Rawit Merah')
    ->where('rf.transaction_date', '>=', '2026-01-01')
    ->where('rfi.unit', 'Kilogram')
    ->where(function ($q) {
        $q->where('rfi.price', '>', 150000)->orWhere('rfi.price', '<', 20000);
    })
    ->orderByDesc('rfi.price')
    ->get(['rf.retail_number', 'rf.transaction_date', 'rfi.qty', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
foreach ($rows as $r) {
    echo sprintf("%s %s outlet=%s qty=%s @ %s sub=%s\n", $r->transaction_date, $r->retail_number, $r->nama_outlet, $r->qty, number_format($r->price, 0, ',', '.'), number_format($r->subtotal, 0, ',', '.'));
}

echo "\n=== Cost history around GR 2026-03-11 WH5 ===\n";
$inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
echo "inv_id={$inv->id}\n";
$h = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', $inv->id)
    ->where('warehouse_id', 5)
    ->whereBetween('date', ['2026-03-08', '2026-03-15'])
    ->orderBy('date')
    ->get(['date', 'type', 'old_cost', 'new_cost', 'mac', 'reference_type', 'reference_id']);
foreach ($h as $c) {
    echo json_encode($c) . "\n";
}

echo "\n=== Cost history MAC max 2026 any warehouse ===\n";
$stats = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', $inv->id)
    ->where('date', '>=', '2026-01-01')
    ->selectRaw('warehouse_id, max(mac) max_mac, max(new_cost) max_cost, min(mac) min_mac')
    ->groupBy('warehouse_id')
    ->orderByDesc('max_cost')
    ->get();
foreach ($stats as $s) {
    echo json_encode($s) . "\n";
}

echo "\n=== Cards GR-20260311 cost_per_small ===\n";
$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $inv->id)
    ->where('reference_type', 'good_receive')
    ->where('reference_id', 8216)
    ->get(['id', 'warehouse_id', 'date', 'in_qty_small', 'cost_per_small', 'value_in', 'saldo_qty_small', 'saldo_value']);
foreach ($cards as $c) {
    echo json_encode($c) . "\n";
}
