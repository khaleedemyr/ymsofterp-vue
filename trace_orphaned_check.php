<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

echo "=== CEK ORPHANED CONTRA BON ITEMS ===\n\n";

// 1. Cek semua contra bon items yang memiliki gr_item_id
echo "1. Cek semua Contra Bon Items dengan GR Item ID:\n";
$allContraBonItems = DB::table('food_contra_bon_items')
    ->whereNotNull('gr_item_id')
    ->select('id', 'contra_bon_id', 'gr_item_id')
    ->get();

echo "   Total: " . $allContraBonItems->count() . "\n";

$contraBonIds = $allContraBonItems->pluck('contra_bon_id')->unique()->toArray();
echo "   Unique Contra Bon IDs: " . count($contraBonIds) . "\n\n";

// 2. Cek contra bon yang masih ada
echo "2. Cek Contra Bon yang masih ada:\n";
$existingContraBons = DB::table('food_contra_bons')
    ->whereIn('id', $contraBonIds)
    ->pluck('id')
    ->toArray();

echo "   Contra Bon yang masih ada: " . count($existingContraBons) . "\n\n";

// 3. Cek orphaned items (contra bon sudah dihapus tapi items masih ada)
$deletedContraBonIds = array_diff($contraBonIds, $existingContraBons);

if (!empty($deletedContraBonIds)) {
    echo "3. ⚠️  DITEMUKAN ORPHANED ITEMS!\n";
    echo "   Contra Bon IDs yang sudah dihapus: " . count($deletedContraBonIds) . "\n";
    echo "   IDs: " . implode(', ', array_slice($deletedContraBonIds, 0, 20)) . (count($deletedContraBonIds) > 20 ? '...' : '') . "\n\n";
    
    $orphanedItems = $allContraBonItems->whereIn('contra_bon_id', $deletedContraBonIds);
    echo "   Total Orphaned Items: " . $orphanedItems->count() . "\n";
    
    $orphanedGRItemIds = $orphanedItems->pluck('gr_item_id')->toArray();
    echo "   Orphaned GR Item IDs: " . count($orphanedGRItemIds) . "\n";
    echo "   GR Item IDs: " . implode(', ', array_slice($orphanedGRItemIds, 0, 20)) . (count($orphanedGRItemIds) > 20 ? '...' : '') . "\n\n";
    
    // 4. Cek apakah orphaned items ini masih terhitung di usedGRItemIds
    echo "4. Cek apakah Orphaned Items masih terhitung di usedGRItemIds:\n";
    $usedGRItemIds = DB::table('food_contra_bon_items as cbi')
        ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
        ->whereNotNull('cbi.gr_item_id')
        ->pluck('cbi.gr_item_id')
        ->toArray();
    
    $orphanedInUsed = array_intersect($orphanedGRItemIds, $usedGRItemIds);
    
    echo "   Total Used GR Item IDs (dengan join): " . count($usedGRItemIds) . "\n";
    echo "   Orphaned GR Item IDs: " . count($orphanedGRItemIds) . "\n";
    echo "   Orphaned GR Item IDs yang masih di usedGRItemIds: " . count($orphanedInUsed) . "\n";
    
    if (count($orphanedInUsed) > 0) {
        echo "\n   ⚠️  MASALAH: Ada " . count($orphanedInUsed) . " orphaned GR items yang masih terhitung!\n";
        echo "   Ini seharusnya tidak terjadi karena join dengan contra_bons.\n";
        echo "   Kemungkinan ada bug di query atau data tidak konsisten.\n";
    } else {
        echo "\n   ✓ Orphaned items sudah di-exclude dengan benar oleh join\n";
    }
    
    // 5. Cek detail orphaned items untuk supplier 105
    echo "\n5. Cek Orphaned Items untuk Supplier 105:\n";
    $supplierId = 105;
    
    $supplierGRs = DB::table('food_good_receives as gr')
        ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
        ->where('po.supplier_id', $supplierId)
        ->pluck('gr.id')
        ->toArray();
    
    $supplierGRItems = DB::table('food_good_receive_items')
        ->whereIn('good_receive_id', $supplierGRs)
        ->pluck('id')
        ->toArray();
    
    $supplierOrphanedItems = array_intersect($orphanedGRItemIds, $supplierGRItems);
    
    echo "   Orphaned Items untuk Supplier 105: " . count($supplierOrphanedItems) . "\n";
    if (!empty($supplierOrphanedItems)) {
        echo "   GR Item IDs: " . implode(', ', $supplierOrphanedItems) . "\n";
        echo "\n   ⚠️  INI MASALAHNYA! Orphaned items ini menyebabkan GR items tidak muncul!\n";
    }
    
} else {
    echo "3. ✓ Tidak ada orphaned items\n";
}

echo "\n=== KESIMPULAN ===\n";
if (!empty($deletedContraBonIds)) {
    echo "Ada " . count($deletedContraBonIds) . " contra bon yang sudah dihapus.\n";
    echo "Ada " . $orphanedItems->count() . " orphaned items.\n";
    if (!empty($supplierOrphanedItems ?? [])) {
        echo "Ada " . count($supplierOrphanedItems) . " orphaned items untuk Supplier 105.\n";
        echo "Ini yang menyebabkan GR items tidak muncul setelah delete contra bon!\n";
    }
} else {
    echo "Tidak ada orphaned items. Semua items sudah dihapus dengan benar.\n";
}

