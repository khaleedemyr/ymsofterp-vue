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

echo "=== TRACE DETAILED GETPOWithApprovedGR ===\n";
echo "Supplier ID: {$supplierId}\n";
echo "GR Date: {$grDate}\n\n";

// Simulasi query getPOWithApprovedGR
echo "1. Query usedGRItemIds:\n";
$usedGRItemIdsQuery = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereNotNull('cbi.gr_item_id');

if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
    $usedGRItemIdsQuery->whereNull('cb.deleted_at');
}

$usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();
echo "   Total Used GR Item IDs: " . count($usedGRItemIds) . "\n";
echo "   GR Item ID 8973 in usedGRItemIds: " . (in_array(8973, $usedGRItemIds) ? 'YES' : 'NO') . "\n\n";

// 2. Query PO with GR
echo "2. Query PO with GR:\n";
$poWithGR = DB::table('purchase_order_foods as po')
    ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
    ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
    ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
    ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
    ->where('po.supplier_id', $supplierId)
    ->whereDate('gr.receive_date', $grDate)
    ->select(
        'po.id as po_id',
        'po.number as po_number',
        'po.date as po_date',
        'po.source_type',
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date as gr_date',
        's.id as supplier_id',
        's.name as supplier_name'
    )
    ->orderByDesc('gr.receive_date')
    ->limit(500)
    ->get();

echo "   Total PO with GR: " . $poWithGR->count() . "\n";
foreach ($poWithGR as $row) {
    echo "   - PO: {$row->po_number}, GR: {$row->gr_number}, GR ID: {$row->gr_id}\n";
}
echo "\n";

if ($poWithGR->isEmpty()) {
    echo "Tidak ada PO dengan GR untuk supplier dan tanggal tersebut!\n";
    exit;
}

// 3. Query GR Items
$grIds = $poWithGR->pluck('gr_id')->toArray();
echo "3. Query GR Items (whereNotIn usedGRItemIds):\n";
$allGRItems = DB::table('food_good_receive_items as gri')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->whereIn('gri.good_receive_id', $grIds)
    ->whereNotIn('gri.id', $usedGRItemIds)
    ->select(
        'gri.good_receive_id',
        'gri.id',
        'gri.item_id',
        'gri.po_item_id',
        'gri.unit_id',
        'gri.qty_received'
    )
    ->get();

echo "   Total GR Items (after filter): " . $allGRItems->count() . "\n";
foreach ($allGRItems as $item) {
    echo "   - GR Item ID: {$item->id}, GR ID: {$item->good_receive_id}, Item ID: {$item->item_id}, Qty: {$item->qty_received}\n";
}
echo "\n";

// 4. Group by GR
echo "4. Group GR Items by GR ID:\n";
$allItemsGrouped = $allGRItems->groupBy('good_receive_id');
foreach ($allItemsGrouped as $grId => $items) {
    echo "   GR ID {$grId}: " . $items->count() . " items\n";
}
echo "\n";

// 5. Final result
echo "5. Final Result (PO dengan items):\n";
$result = [];
foreach ($poWithGR as $row) {
    $items = $allItemsGrouped->get($row->gr_id, collect());
    
    if ($items->isEmpty()) {
        echo "   - PO: {$row->po_number}, GR: {$row->gr_number} -> SKIP (no items)\n";
        continue;
    }
    
    echo "   - PO: {$row->po_number}, GR: {$row->gr_number} -> INCLUDE ({$items->count()} items)\n";
    $result[] = $row->po_id;
}

echo "\n=== SUMMARY ===\n";
echo "PO yang muncul di form: " . count($result) . "\n";
echo "PO IDs: " . implode(', ', $result) . "\n";

