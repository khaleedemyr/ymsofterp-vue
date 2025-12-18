<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

$supplierId = 105;

echo "=== TRACE DELETE PROCESS - SUPPLIER 105 ===\n";
echo "Supplier ID: {$supplierId}\n\n";

// Cek semua contra bon untuk supplier 105 yang sudah dihapus
echo "1. Cek Contra Bon untuk Supplier 105:\n";

// Ambil semua contra bon yang menggunakan PO dari supplier 105
$contraBons = DB::table('food_contra_bons as cb')
    ->leftJoin('food_contra_bon_sources as cbs', 'cb.id', '=', 'cbs.contra_bon_id')
    ->leftJoin('purchase_order_foods as po', function($join) {
        $join->on('cbs.source_id', '=', 'po.id')
             ->where('cbs.source_type', '=', 'purchase_order');
    })
    ->where('po.supplier_id', $supplierId)
    ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
    ->distinct()
    ->get();

echo "   Total Contra Bon: " . $contraBons->count() . "\n";
foreach ($contraBons as $cb) {
    echo "   - CB: {$cb->number} (ID: {$cb->id}, Date: {$cb->date}, Status: {$cb->status})\n";
}
echo "\n";

// Cek contra bon items untuk contra bon tersebut
$contraBonIds = $contraBons->pluck('id')->toArray();
if (!empty($contraBonIds)) {
    echo "2. Cek Contra Bon Items:\n";
    $contraBonItems = DB::table('food_contra_bon_items')
        ->whereIn('contra_bon_id', $contraBonIds)
        ->whereNotNull('gr_item_id')
        ->select('id', 'contra_bon_id', 'gr_item_id')
        ->get();
    
    echo "   Total Items dengan GR Item ID: " . $contraBonItems->count() . "\n";
    
    // Group by contra_bon_id
    $itemsByCB = $contraBonItems->groupBy('contra_bon_id');
    foreach ($itemsByCB as $cbId => $items) {
        $cb = $contraBons->firstWhere('id', $cbId);
        $cbNumber = $cb ? $cb->number : "ID: {$cbId}";
        echo "   - CB: {$cbNumber} -> {$items->count()} items\n";
        foreach ($items as $item) {
            echo "     GR Item ID: {$item->gr_item_id}\n";
        }
    }
    echo "\n";
    
    // 3. Simulasi delete - cek apakah items benar-benar dihapus
    echo "3. Simulasi Delete Process:\n";
    echo "   Jika CB dihapus, items juga harus dihapus (hard delete)\n";
    echo "   Query untuk cek usedGRItemIds setelah delete:\n";
    
    // Query yang sama dengan getPOWithApprovedGR
    $usedGRItemIds = DB::table('food_contra_bon_items as cbi')
        ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
        ->whereNotNull('cbi.gr_item_id')
        ->pluck('cbi.gr_item_id')
        ->toArray();
    
    $grItemIdsFromCB = $contraBonItems->pluck('gr_item_id')->toArray();
    $grItemIdsStillInUsed = array_intersect($grItemIdsFromCB, $usedGRItemIds);
    
    echo "   Total Used GR Item IDs: " . count($usedGRItemIds) . "\n";
    echo "   GR Item IDs dari CB Supplier 105: " . count($grItemIdsFromCB) . "\n";
    echo "   GR Item IDs yang masih di usedGRItemIds: " . count($grItemIdsStillInUsed) . "\n";
    
    if (count($grItemIdsStillInUsed) > 0) {
        echo "\n   ⚠️  MASALAH: Ada GR items yang masih terhitung meskipun CB sudah dihapus!\n";
        echo "   GR Item IDs: " . implode(', ', $grItemIdsStillInUsed) . "\n";
    } else {
        echo "\n   ✓ Semua GR items sudah di-exclude dengan benar\n";
    }
}

echo "\n=== KESIMPULAN ===\n";
echo "Jika setelah delete contra bon, data tidak muncul, kemungkinan:\n";
echo "1. Items tidak benar-benar dihapus saat delete contra bon\n";
echo "2. Ada cache di frontend\n";
echo "3. Query getPOWithApprovedGR tidak di-refresh setelah delete\n";

