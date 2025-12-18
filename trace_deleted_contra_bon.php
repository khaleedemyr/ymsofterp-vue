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

echo "=== TRACE DELETED CONTRA BON - SUPPLIER 105 ===\n";
echo "Supplier ID: {$supplierId}\n";
echo "GR Date: {$grDate}\n\n";

// 1. Cek GR untuk tanggal tersebut
$gr = DB::table('food_good_receives as gr')
    ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->where('po.supplier_id', $supplierId)
    ->whereDate('gr.receive_date', $grDate)
    ->select('gr.id as gr_id', 'gr.gr_number', 'gr.po_id', 'po.number as po_number')
    ->first();

if (!$gr) {
    echo "GR tidak ditemukan!\n";
    exit;
}

echo "1. GR Info:\n";
echo "   GR ID: {$gr->gr_id}\n";
echo "   GR Number: {$gr->gr_number}\n";
echo "   PO: {$gr->po_number}\n\n";

// 2. Cek GR items
$grItems = DB::table('food_good_receive_items')
    ->where('good_receive_id', $gr->gr_id)
    ->select('id', 'item_id', 'unit_id', 'qty_received')
    ->get();

echo "2. GR Items:\n";
foreach ($grItems as $item) {
    echo "   - GR Item ID: {$item->id}, Item ID: {$item->item_id}, Qty: {$item->qty_received}\n";
}
echo "\n";

$grItemIds = $grItems->pluck('id')->toArray();

// 3. Cek SEMUA contra bon items yang pernah menggunakan GR items ini (termasuk yang sudah dihapus)
echo "3. SEMUA Contra Bon Items yang PERNAH menggunakan GR Items ini:\n";
$allContraBonItems = DB::table('food_contra_bon_items')
    ->whereIn('gr_item_id', $grItemIds)
    ->select('id', 'contra_bon_id', 'gr_item_id')
    ->get();

echo "   Total: " . $allContraBonItems->count() . "\n";
foreach ($allContraBonItems as $item) {
    echo "   - CB Item ID: {$item->id}, CB ID: {$item->contra_bon_id}, GR Item ID: {$item->gr_item_id}\n";
}
echo "\n";

// 4. Cek contra bon yang masih ada (tidak dihapus)
$contraBonIds = $allContraBonItems->pluck('contra_bon_id')->unique()->toArray();
echo "4. Contra Bon yang menggunakan GR Items ini:\n";
if (!empty($contraBonIds)) {
    $contraBons = DB::table('food_contra_bons')
        ->whereIn('id', $contraBonIds)
        ->select('id', 'number', 'date', 'status')
        ->get();
    
    echo "   Total Contra Bon: " . $contraBons->count() . "\n";
    foreach ($contraBons as $cb) {
        echo "   - CB: {$cb->number} (ID: {$cb->id}, Date: {$cb->date}, Status: {$cb->status}) -> MASIH ADA\n";
    }
} else {
    echo "   Tidak ada contra bon yang menggunakan GR items ini\n";
}
echo "\n";

// 5. Cek usedGRItemIds dengan join (seharusnya hanya ambil dari contra bon yang masih ada)
echo "5. Query usedGRItemIds (dengan join contra_bons):\n";
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
echo "   GR Item IDs dari GR ini yang ada di usedGRItemIds: " . implode(', ', array_intersect($usedGRItemIds, $grItemIds)) . "\n\n";

// 6. Cek apakah GR items ini muncul di query getPOWithApprovedGR
echo "6. Cek apakah GR Items muncul di getPOWithApprovedGR:\n";
$grItemsInQuery = DB::table('food_good_receive_items as gri')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->whereIn('gri.good_receive_id', [$gr->gr_id])
    ->whereNotIn('gri.id', $usedGRItemIds)
    ->select('gri.id', 'gri.good_receive_id', 'gri.item_id', 'gri.qty_received')
    ->get();

echo "   GR Items yang muncul: " . $grItemsInQuery->count() . "\n";
foreach ($grItemsInQuery as $item) {
    echo "   ✓ GR Item ID: {$item->id}, Item ID: {$item->item_id}, Qty: {$item->qty_received}\n";
}
echo "\n";

// 7. Cek apakah ada orphaned contra bon items (contra bon sudah dihapus tapi items masih ada)
echo "7. Cek Orphaned Contra Bon Items (CB sudah dihapus tapi items masih ada):\n";
if (!empty($contraBonIds)) {
    $existingContraBonIds = DB::table('food_contra_bons')
        ->whereIn('id', $contraBonIds)
        ->pluck('id')
        ->toArray();
    
    $deletedContraBonIds = array_diff($contraBonIds, $existingContraBonIds);
    
    if (!empty($deletedContraBonIds)) {
        echo "   ⚠️  DITEMUKAN ORPHANED ITEMS!\n";
        echo "   Contra Bon IDs yang sudah dihapus: " . implode(', ', $deletedContraBonIds) . "\n";
        
        $orphanedItems = DB::table('food_contra_bon_items')
            ->whereIn('contra_bon_id', $deletedContraBonIds)
            ->whereIn('gr_item_id', $grItemIds)
            ->get();
        
        echo "   Orphaned Items Count: " . $orphanedItems->count() . "\n";
        foreach ($orphanedItems as $item) {
            echo "   - CB Item ID: {$item->id}, CB ID: {$item->contra_bon_id} (DELETED), GR Item ID: {$item->gr_item_id}\n";
        }
        
        echo "\n   ⚠️  MASALAH: Orphaned items ini masih ada di tabel food_contra_bon_items!\n";
        echo "   Ini menyebabkan GR items tidak muncul karena masih terhitung di usedGRItemIds.\n";
    } else {
        echo "   ✓ Tidak ada orphaned items\n";
    }
} else {
    echo "   Tidak ada contra bon yang menggunakan GR items ini\n";
}
echo "\n";

// 8. Summary
echo "=== SUMMARY ===\n";
echo "GR Items untuk GR ini: " . $grItems->count() . "\n";
echo "Contra Bon Items yang pernah menggunakan: " . $allContraBonItems->count() . "\n";
echo "Contra Bon yang masih ada: " . (!empty($contraBonIds) ? count($existingContraBonIds ?? []) : 0) . "\n";
echo "GR Items yang muncul di query: " . $grItemsInQuery->count() . "\n";
echo "Should appear in form: " . ($grItemsInQuery->count() > 0 ? 'YES' : 'NO') . "\n";

