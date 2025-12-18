<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

$supplierId = 105;

echo "=== TRACE ORPHANED CONTRA BON ITEMS - SUPPLIER 105 ===\n";
echo "Supplier ID: {$supplierId}\n\n";

// 1. Ambil semua GR items untuk supplier 105
$allGRs = DB::table('food_good_receives as gr')
    ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->where('po.supplier_id', $supplierId)
    ->pluck('gr.id')
    ->toArray();

$allGRItems = DB::table('food_good_receive_items')
    ->whereIn('good_receive_id', $allGRs)
    ->pluck('id')
    ->toArray();

echo "1. Total GR Items untuk Supplier 105: " . count($allGRItems) . "\n\n";

// 2. Cek semua contra bon items yang menggunakan GR items supplier 105
echo "2. Cek Contra Bon Items yang menggunakan GR Items Supplier 105:\n";
$contraBonItems = DB::table('food_contra_bon_items')
    ->whereIn('gr_item_id', $allGRItems)
    ->select('id', 'contra_bon_id', 'gr_item_id')
    ->get();

echo "   Total Contra Bon Items: " . $contraBonItems->count() . "\n";

$contraBonIds = $contraBonItems->pluck('contra_bon_id')->unique()->toArray();
echo "   Unique Contra Bon IDs: " . count($contraBonIds) . "\n\n";

// 3. Cek contra bon yang masih ada
echo "3. Cek Contra Bon yang masih ada:\n";
$existingContraBons = DB::table('food_contra_bons')
    ->whereIn('id', $contraBonIds)
    ->pluck('id')
    ->toArray();

echo "   Contra Bon yang masih ada: " . count($existingContraBons) . "\n";

// 4. Cek orphaned items (contra bon sudah dihapus tapi items masih ada)
$deletedContraBonIds = array_diff($contraBonIds, $existingContraBons);

if (!empty($deletedContraBonIds)) {
    echo "\n   ⚠️  DITEMUKAN ORPHANED ITEMS!\n";
    echo "   Contra Bon IDs yang sudah dihapus: " . implode(', ', $deletedContraBonIds) . "\n\n";
    
    $orphanedItems = DB::table('food_contra_bon_items')
        ->whereIn('contra_bon_id', $deletedContraBonIds)
        ->whereIn('gr_item_id', $allGRItems)
        ->select('id', 'contra_bon_id', 'gr_item_id')
        ->get();
    
    echo "4. Orphaned Items Detail:\n";
    echo "   Total Orphaned Items: " . $orphanedItems->count() . "\n";
    
    foreach ($orphanedItems as $item) {
        // Cek GR item info
        $grItem = DB::table('food_good_receive_items as gri')
            ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->where('gri.id', $item->gr_item_id)
            ->select('gri.id', 'gr.gr_number', 'gr.receive_date', 'po.number as po_number')
            ->first();
        
        echo "   - CB Item ID: {$item->id}, CB ID: {$item->contra_bon_id} (DELETED)\n";
        echo "     GR Item ID: {$item->gr_item_id}\n";
        if ($grItem) {
            echo "     GR: {$grItem->gr_number}, Date: {$grItem->receive_date}, PO: {$grItem->po_number}\n";
        }
        echo "\n";
    }
    
    // 5. Cek usedGRItemIds dengan join (seharusnya exclude orphaned items)
    echo "5. Cek usedGRItemIds (dengan join - seharusnya exclude orphaned):\n";
    $usedGRItemIds = DB::table('food_contra_bon_items as cbi')
        ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
        ->whereNotNull('cbi.gr_item_id')
        ->pluck('cbi.gr_item_id')
        ->toArray();
    
    $orphanedGRItemIds = $orphanedItems->pluck('gr_item_id')->toArray();
    $orphanedInUsed = array_intersect($orphanedGRItemIds, $usedGRItemIds);
    
    echo "   Total Used GR Item IDs: " . count($usedGRItemIds) . "\n";
    echo "   Orphaned GR Item IDs: " . count($orphanedGRItemIds) . "\n";
    echo "   Orphaned GR Item IDs yang masih di usedGRItemIds: " . count($orphanedInUsed) . "\n";
    
    if (count($orphanedInUsed) > 0) {
        echo "\n   ⚠️  MASALAH DITEMUKAN!\n";
        echo "   Ada " . count($orphanedInUsed) . " orphaned GR items yang masih terhitung di usedGRItemIds.\n";
        echo "   Ini menyebabkan GR items tidak muncul di form.\n";
        echo "   Orphaned GR Item IDs: " . implode(', ', $orphanedInUsed) . "\n";
    } else {
        echo "\n   ✓ Tidak ada masalah - orphaned items sudah di-exclude dengan benar\n";
    }
} else {
    echo "\n   ✓ Tidak ada orphaned items\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total GR Items Supplier 105: " . count($allGRItems) . "\n";
echo "Contra Bon Items menggunakan GR Items: " . $contraBonItems->count() . "\n";
echo "Contra Bon yang masih ada: " . count($existingContraBons) . "\n";
echo "Contra Bon yang sudah dihapus: " . count($deletedContraBonIds ?? []) . "\n";
if (!empty($deletedContraBonIds)) {
    echo "Orphaned Items: " . ($orphanedItems->count() ?? 0) . "\n";
}

