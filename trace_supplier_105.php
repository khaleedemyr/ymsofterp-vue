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

echo "=== TRACE SUPPLIER 105 - GR YANG BELUM ADA DI CONTRA BON ===\n";
echo "Supplier ID: {$supplierId}\n\n";

// 1. Ambil semua GR untuk supplier 105
echo "1. Semua GR untuk Supplier 105:\n";
$allGRs = DB::table('food_good_receives as gr')
    ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->where('po.supplier_id', $supplierId)
    ->select(
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date',
        'gr.po_id',
        'po.number as po_number',
        'po.supplier_id'
    )
    ->orderByDesc('gr.receive_date')
    ->get();

echo "   Total GR: " . $allGRs->count() . "\n\n";

if ($allGRs->isEmpty()) {
    echo "Tidak ada GR untuk supplier ini!\n";
    exit;
}

$grIds = $allGRs->pluck('gr_id')->toArray();

// 2. Ambil semua GR items untuk GR tersebut
echo "2. Semua GR Items untuk GR tersebut:\n";
$allGRItems = DB::table('food_good_receive_items')
    ->whereIn('good_receive_id', $grIds)
    ->select('id', 'good_receive_id', 'item_id', 'unit_id', 'qty_received')
    ->get();

echo "   Total GR Items: " . $allGRItems->count() . "\n";
$allGRItemIds = $allGRItems->pluck('id')->toArray();
echo "   GR Item IDs: " . implode(', ', array_slice($allGRItemIds, 0, 20)) . (count($allGRItemIds) > 20 ? '...' : '') . "\n\n";

// 3. Cek usedGRItemIds (yang sudah digunakan di contra bon)
echo "3. GR Items yang SUDAH digunakan di Contra Bon:\n";
$hasSoftDelete = Schema::hasColumn('food_contra_bons', 'deleted_at');

$usedGRItemIdsQuery = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereNotNull('cbi.gr_item_id');

if ($hasSoftDelete) {
    $usedGRItemIdsQuery->whereNull('cb.deleted_at');
}

$usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();

echo "   Total Used GR Item IDs (semua supplier): " . count($usedGRItemIds) . "\n";

// Cek GR items dari supplier 105 yang sudah digunakan
$usedGRItemIdsForSupplier = array_intersect($usedGRItemIds, $allGRItemIds);
echo "   GR Items dari Supplier 105 yang sudah digunakan: " . count($usedGRItemIdsForSupplier) . "\n";
if (!empty($usedGRItemIdsForSupplier)) {
    echo "   Used GR Item IDs: " . implode(', ', array_slice($usedGRItemIdsForSupplier, 0, 20)) . (count($usedGRItemIdsForSupplier) > 20 ? '...' : '') . "\n";
}
echo "\n";

// 4. Cek GR items yang BELUM digunakan (seharusnya muncul)
echo "4. GR Items yang BELUM digunakan (seharusnya muncul di form):\n";
$availableGRItems = $allGRItems->whereNotIn('id', $usedGRItemIds);
echo "   Total Available GR Items: " . $availableGRItems->count() . "\n\n";

// 5. Group by GR untuk melihat detail
echo "5. Detail per GR:\n";
$grItemsByGR = $allGRItems->groupBy('good_receive_id');
$availableGRItemsByGR = $availableGRItems->groupBy('good_receive_id');

foreach ($allGRs as $gr) {
    $grItems = $grItemsByGR->get($gr->gr_id, collect());
    $availableItems = $availableGRItemsByGR->get($gr->gr_id, collect());
    $usedItems = $grItems->whereIn('id', $usedGRItemIds);
    
    echo "   GR: {$gr->gr_number} (ID: {$gr->gr_id}, Date: {$gr->receive_date})\n";
    echo "      PO: {$gr->po_number}\n";
    echo "      Total Items: {$grItems->count()}\n";
    echo "      Used Items: {$usedItems->count()}\n";
    echo "      Available Items: {$availableItems->count()}\n";
    
    if ($availableItems->count() > 0) {
        echo "      ✓ SEHARUSNYA MUNCUL DI FORM\n";
        foreach ($availableItems as $item) {
            echo "         - GR Item ID: {$item->id}, Item ID: {$item->item_id}, Qty: {$item->qty_received}\n";
        }
    } else {
        echo "      ✗ TIDAK MUNCUL (semua sudah digunakan)\n";
        if ($usedItems->count() > 0) {
            echo "      Used by Contra Bon:\n";
            foreach ($usedItems as $item) {
                // Cek contra bon yang menggunakan item ini
                $contraBons = DB::table('food_contra_bon_items as cbi')
                    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
                    ->where('cbi.gr_item_id', $item->id)
                    ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
                    ->get();
                
                foreach ($contraBons as $cb) {
                    $deleted = $hasSoftDelete && $cb->deleted_at ? ' (DELETED)' : '';
                    echo "         - CB: {$cb->number} (ID: {$cb->id}, Date: {$cb->date}, Status: {$cb->status}){$deleted}\n";
                }
            }
        }
    }
    echo "\n";
}

// 6. Cek query getPOWithApprovedGR untuk supplier 105
echo "6. Simulasi Query getPOWithApprovedGR untuk Supplier 105:\n";
$poWithGR = DB::table('purchase_order_foods as po')
    ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
    ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
    ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
    ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
    ->where('po.supplier_id', $supplierId)
    ->select(
        'po.id as po_id',
        'po.number as po_number',
        'po.date as po_date',
        'po.source_type',
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date as gr_date'
    )
    ->orderByDesc('gr.receive_date')
    ->limit(500)
    ->get();

echo "   Total PO with GR: " . $poWithGR->count() . "\n";

$poWithGRIds = $poWithGR->pluck('gr_id')->toArray();
$grItemsForQuery = DB::table('food_good_receive_items as gri')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->whereIn('gri.good_receive_id', $poWithGRIds)
    ->whereNotIn('gri.id', $usedGRItemIds)
    ->select('gri.good_receive_id', 'gri.id')
    ->get();

$grItemsGrouped = $grItemsForQuery->groupBy('good_receive_id');

$poThatShouldAppear = [];
foreach ($poWithGR as $row) {
    $items = $grItemsGrouped->get($row->gr_id, collect());
    if ($items->isNotEmpty()) {
        $poThatShouldAppear[] = $row->po_id;
        echo "   ✓ PO: {$row->po_number} (GR: {$row->gr_number}) -> {$items->count()} items\n";
    } else {
        echo "   ✗ PO: {$row->po_number} (GR: {$row->gr_number}) -> SKIP (no items)\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total GR untuk Supplier 105: " . $allGRs->count() . "\n";
echo "Total GR Items: " . $allGRItems->count() . "\n";
echo "GR Items yang sudah digunakan: " . count($usedGRItemIdsForSupplier) . "\n";
echo "GR Items yang seharusnya muncul: " . $availableGRItems->count() . "\n";
echo "PO yang seharusnya muncul di form: " . count($poThatShouldAppear) . "\n";
echo "PO IDs: " . implode(', ', array_slice($poThatShouldAppear, 0, 10)) . (count($poThatShouldAppear) > 10 ? '...' : '') . "\n";

