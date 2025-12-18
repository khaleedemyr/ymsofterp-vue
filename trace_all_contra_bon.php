<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

$supplierId = 105;

echo "=== TRACE ALL CONTRA BON ITEMS - SUPPLIER 105 ===\n";
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

// 2. Cek SEMUA contra bon items yang menggunakan GR items supplier 105
echo "2. SEMUA Contra Bon Items yang menggunakan GR Items Supplier 105:\n";
$allContraBonItems = DB::table('food_contra_bon_items')
    ->whereIn('gr_item_id', $allGRItems)
    ->select('id', 'contra_bon_id', 'gr_item_id')
    ->get();

echo "   Total: " . $allContraBonItems->count() . "\n";

$contraBonIds = $allContraBonItems->pluck('contra_bon_id')->unique()->toArray();
echo "   Unique Contra Bon IDs: " . count($contraBonIds) . "\n";
echo "   Contra Bon IDs: " . implode(', ', $contraBonIds) . "\n\n";

// 3. Cek contra bon yang masih ada
echo "3. Cek Contra Bon yang masih ada:\n";
$existingContraBons = DB::table('food_contra_bons')
    ->whereIn('id', $contraBonIds)
    ->select('id', 'number', 'date', 'status')
    ->get();

echo "   Contra Bon yang masih ada: " . $existingContraBons->count() . "\n";
foreach ($existingContraBons as $cb) {
    echo "   - CB: {$cb->number} (ID: {$cb->id}, Date: {$cb->date}, Status: {$cb->status})\n";
}
echo "\n";

// 4. Cek contra bon yang sudah dihapus
$existingContraBonIds = $existingContraBons->pluck('id')->toArray();
$deletedContraBonIds = array_diff($contraBonIds, $existingContraBonIds);

if (!empty($deletedContraBonIds)) {
    echo "4. Contra Bon yang sudah dihapus:\n";
    echo "   Contra Bon IDs: " . implode(', ', $deletedContraBonIds) . "\n";
    
    $orphanedItems = $allContraBonItems->whereIn('contra_bon_id', $deletedContraBonIds);
    echo "   Orphaned Items: " . $orphanedItems->count() . "\n";
    foreach ($orphanedItems as $item) {
        echo "   - CB Item ID: {$item->id}, CB ID: {$item->contra_bon_id} (DELETED), GR Item ID: {$item->gr_item_id}\n";
    }
    echo "\n";
} else {
    echo "4. Tidak ada contra bon yang sudah dihapus\n\n";
}

// 5. Cek usedGRItemIds dengan join
echo "5. Query usedGRItemIds (dengan join contra_bons):\n";
$usedGRItemIds = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereNotNull('cbi.gr_item_id')
    ->pluck('cbi.gr_item_id')
    ->toArray();

echo "   Total Used GR Item IDs: " . count($usedGRItemIds) . "\n";

// Cek GR items supplier 105 yang masih di usedGRItemIds
$grItemsStillInUsed = array_intersect($allGRItems, $usedGRItemIds);
echo "   GR Items Supplier 105 yang masih di usedGRItemIds: " . count($grItemsStillInUsed) . "\n";

if (!empty($grItemsStillInUsed)) {
    echo "   GR Item IDs: " . implode(', ', $grItemsStillInUsed) . "\n";
    
    // Cek contra bon yang menggunakan GR items ini
    echo "\n   Detail Contra Bon yang menggunakan GR Items ini:\n";
    foreach ($grItemsStillInUsed as $grItemId) {
        $cbItems = DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
            ->where('cbi.gr_item_id', $grItemId)
            ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
            ->get();
        
        foreach ($cbItems as $cb) {
            echo "     - GR Item ID: {$grItemId} -> CB: {$cb->number} (ID: {$cb->id}, Date: {$cb->date}, Status: {$cb->status})\n";
        }
    }
} else {
    echo "   ✓ Semua GR items sudah di-exclude dengan benar\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total GR Items Supplier 105: " . count($allGRItems) . "\n";
echo "Contra Bon Items menggunakan GR Items: " . $allContraBonItems->count() . "\n";
echo "Contra Bon yang masih ada: " . count($existingContraBonIds) . "\n";
echo "Contra Bon yang sudah dihapus: " . count($deletedContraBonIds) . "\n";
echo "GR Items yang masih di usedGRItemIds: " . count($grItemsStillInUsed) . "\n";
echo "GR Items yang seharusnya muncul: " . (count($allGRItems) - count($grItemsStillInUsed)) . "\n";

