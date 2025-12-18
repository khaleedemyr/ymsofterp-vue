<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$supplierId = 105;
$grDate = '2025-11-13';

echo "=== TRACE CONTRA BON ===\n";
echo "Supplier ID: {$supplierId}\n";
echo "GR Date: {$grDate}\n\n";

// 1. Cek GR untuk supplier dan tanggal tersebut
echo "1. Cek Good Receives:\n";
$goodReceives = DB::table('food_good_receives as gr')
    ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->where('po.supplier_id', $supplierId)
    ->whereDate('gr.receive_date', $grDate)
    ->select(
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date',
        'gr.po_id',
        'po.number as po_number',
        'po.supplier_id'
    )
    ->get();

echo "   Total GR: " . $goodReceives->count() . "\n";
foreach ($goodReceives as $gr) {
    echo "   - GR ID: {$gr->gr_id}, GR Number: {$gr->gr_number}, PO: {$gr->po_number}\n";
}
echo "\n";

if ($goodReceives->isEmpty()) {
    echo "Tidak ada GR untuk supplier dan tanggal tersebut!\n";
    exit;
}

$grIds = $goodReceives->pluck('gr_id')->toArray();

// 2. Cek GR items
echo "2. Cek GR Items:\n";
$grItems = DB::table('food_good_receive_items')
    ->whereIn('good_receive_id', $grIds)
    ->select('id', 'good_receive_id', 'item_id', 'unit_id', 'qty_received')
    ->get();

echo "   Total GR Items: " . $grItems->count() . "\n";
$grItemIds = $grItems->pluck('id')->toArray();
echo "   GR Item IDs: " . implode(', ', $grItemIds) . "\n\n";

// 3. Cek contra bon items yang menggunakan GR items
echo "3. Cek Contra Bon Items yang menggunakan GR Items:\n";
$contraBonItems = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereIn('cbi.gr_item_id', $grItemIds)
    ->select(
        'cbi.id as item_id',
        'cbi.contra_bon_id',
        'cbi.gr_item_id',
        'cb.number as contra_bon_number',
        'cb.date as contra_bon_date',
        'cb.status'
    )
    ->get();

echo "   Total Contra Bon Items: " . $contraBonItems->count() . "\n";
foreach ($contraBonItems as $item) {
    echo "   - CB Item ID: {$item->item_id}, CB: {$item->contra_bon_number}, GR Item ID: {$item->gr_item_id}, Status: {$item->status}\n";
}
echo "\n";

// 4. Cek usedGRItemIds dari query getPOWithApprovedGR
echo "4. Cek usedGRItemIds (yang digunakan di getPOWithApprovedGR):\n";
$hasSoftDelete = Schema::hasColumn('food_contra_bons', 'deleted_at');
echo "   Soft Delete: " . ($hasSoftDelete ? 'YES' : 'NO') . "\n";

$usedGRItemIdsQuery = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereNotNull('cbi.gr_item_id');

if ($hasSoftDelete) {
    $usedGRItemIdsQuery->whereNull('cb.deleted_at');
}

$usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();

echo "   Total Used GR Item IDs: " . count($usedGRItemIds) . "\n";
echo "   Used GR Item IDs untuk GR ini: " . implode(', ', array_intersect($usedGRItemIds, $grItemIds)) . "\n\n";

// 5. Cek GR items yang seharusnya muncul
echo "5. Cek GR Items yang seharusnya muncul (tidak di usedGRItemIds):\n";
$availableGRItems = $grItems->whereNotIn('id', $usedGRItemIds);
echo "   Available GR Items Count: " . $availableGRItems->count() . "\n";
foreach ($availableGRItems as $item) {
    echo "   - GR Item ID: {$item->id}, Item ID: {$item->item_id}, Qty: {$item->qty_received}\n";
}
echo "\n";

// 6. Summary
echo "=== SUMMARY ===\n";
echo "Total GR: " . $goodReceives->count() . "\n";
echo "Total GR Items: " . $grItems->count() . "\n";
echo "Contra Bon Items menggunakan GR Items: " . $contraBonItems->count() . "\n";
echo "Used GR Item IDs (total): " . count($usedGRItemIds) . "\n";
echo "Available GR Items: " . $availableGRItems->count() . "\n";
echo "Should appear in form: " . ($availableGRItems->count() > 0 ? 'YES' : 'NO') . "\n";

