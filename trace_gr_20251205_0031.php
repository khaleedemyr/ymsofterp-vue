<?php
/**
 * Script untuk trace kenapa GR 20251205-0031 tidak muncul di menu contra bon
 * 
 * Usage: php trace_gr_20251205_0031.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$grNumber = '20251205-0031';

echo "========================================\n";
echo "TRACE GR: {$grNumber}\n";
echo "========================================\n\n";

// 1. Cek apakah GR ada di database
echo "1. CEK APAKAH GR ADA DI DATABASE\n";
echo "-----------------------------------\n";

// Coba beberapa format
$gr = DB::table('food_good_receives')
    ->where('gr_number', $grNumber)
    ->first();

if (!$gr) {
    // Coba dengan format GR-
    $gr = DB::table('food_good_receives')
        ->where('gr_number', 'GR-' . $grNumber)
        ->first();
}

if (!$gr) {
    echo "❌ GR {$grNumber} TIDAK DITEMUKAN di food_good_receives\n";
    echo "\nMencari dengan pattern yang mirip...\n";
    $similarGRs = DB::table('food_good_receives')
        ->where(function($query) use ($grNumber) {
            $query->where('gr_number', 'like', '%20251205%')
                  ->orWhere('gr_number', 'like', '%0031%');
        })
        ->orderBy('receive_date', 'desc')
        ->limit(20)
        ->get();
    
    if ($similarGRs->count() > 0) {
        echo "GR yang mirip ditemukan:\n";
        foreach ($similarGRs as $similar) {
            echo "  - {$similar->gr_number} (ID: {$similar->id}, Date: {$similar->receive_date})\n";
        }
    }
    
    echo "\nMencari semua GR dengan tanggal 2025-12-05...\n";
    $dateGRs = DB::table('food_good_receives')
        ->whereDate('receive_date', '2025-12-05')
        ->orderBy('gr_number')
        ->get();
    
    if ($dateGRs->count() > 0) {
        echo "GR dengan tanggal 2025-12-05:\n";
        foreach ($dateGRs as $dateGR) {
            echo "  - {$dateGR->gr_number} (ID: {$dateGR->id})\n";
        }
    }
    
    exit;
}

echo "✅ GR ditemukan:\n";
echo "   ID: {$gr->id}\n";
echo "   GR Number: {$gr->gr_number}\n";
echo "   PO ID: {$gr->po_id}\n";
echo "   Supplier ID: {$gr->supplier_id}\n";
echo "   Receive Date: {$gr->receive_date}\n";
echo "   Received By: {$gr->received_by}\n";
echo "\n";

// 2. Cek PO
echo "2. CEK PURCHASE ORDER\n";
echo "-----------------------------------\n";
$po = DB::table('purchase_order_foods')
    ->where('id', $gr->po_id)
    ->first();

if (!$po) {
    echo "❌ PO dengan ID {$gr->po_id} TIDAK DITEMUKAN\n";
    exit;
}

echo "✅ PO ditemukan:\n";
echo "   ID: {$po->id}\n";
echo "   PO Number: {$po->number}\n";
echo "   Supplier ID: {$po->supplier_id}\n";
echo "   Source Type: " . ($po->source_type ?? 'NULL') . "\n";
echo "   Date: {$po->date}\n";
echo "\n";

// 3. Cek Supplier
echo "3. CEK SUPPLIER\n";
echo "-----------------------------------\n";
$supplier = DB::table('suppliers')
    ->where('id', $gr->supplier_id)
    ->first();

if (!$supplier) {
    echo "❌ Supplier dengan ID {$gr->supplier_id} TIDAK DITEMUKAN\n";
} else {
    echo "✅ Supplier ditemukan:\n";
    echo "   ID: {$supplier->id}\n";
    echo "   Name: {$supplier->name}\n";
    echo "\n";
}

// 4. Cek PR (jika ada)
echo "4. CEK PR FOODS\n";
echo "-----------------------------------\n";
// Cek source_type dulu
if ($po->source_type === 'pr_foods' || !$po->source_type) {
    // Cari PR melalui PO items
    $pr = DB::table('pr_foods as pr')
        ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
        ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
        ->where('poi.purchase_order_food_id', $po->id)
        ->select('pr.*')
        ->distinct()
        ->first();
    
    if (!$pr) {
        echo "⚠️  PR tidak ditemukan melalui PO items\n";
    } else {
        echo "✅ PR ditemukan:\n";
        echo "   ID: {$pr->id}\n";
        echo "   PR Number: {$pr->pr_number}\n";
        echo "\n";
    }
} else {
    echo "⚠️  PO source_type: " . ($po->source_type ?? 'NULL') . " (bukan pr_foods)\n";
    echo "\n";
}

// 5. Cek GR Items
echo "5. CEK GR ITEMS\n";
echo "-----------------------------------\n";
$grItems = DB::table('food_good_receive_items')
    ->where('good_receive_id', $gr->id)
    ->get();

if ($grItems->isEmpty()) {
    echo "❌ GR tidak memiliki items - INI MASALAH UTAMA!\n";
    echo "   GR tidak akan muncul di menu contra bon karena tidak ada items\n";
    exit;
}

echo "✅ GR memiliki {$grItems->count()} items:\n";
foreach ($grItems as $index => $item) {
    echo "   Item " . ($index + 1) . ":\n";
    echo "     - ID: {$item->id}\n";
    echo "     - Item ID: " . ($item->item_id ?? 'NULL') . "\n";
    echo "     - Unit ID: " . ($item->unit_id ?? 'NULL') . "\n";
    echo "     - PO Item ID: " . ($item->po_item_id ?? 'NULL') . "\n";
    echo "     - Qty Received: {$item->qty_received}\n";
    echo "\n";
}

// 6. Cek apakah GR items sudah digunakan di contra bon
echo "6. CEK APAKAH GR ITEMS SUDAH DIGUNAKAN DI CONTRA BON\n";
echo "-----------------------------------\n";
$grItemIds = $grItems->pluck('id')->toArray();
$usedItems = DB::table('food_contra_bon_items')
    ->join('food_contra_bons', 'food_contra_bon_items.contra_bon_id', '=', 'food_contra_bons.id')
    ->whereIn('food_contra_bon_items.gr_item_id', $grItemIds)
    ->where(function($query) {
        // Cek soft delete jika ada
        if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
            $query->whereNull('food_contra_bons.deleted_at');
        }
    })
    ->select('food_contra_bon_items.*', 'food_contra_bons.number as cb_number', 'food_contra_bons.status as cb_status')
    ->get();

if ($usedItems->count() > 0) {
    echo "⚠️  {$usedItems->count()} GR items sudah digunakan di contra bon:\n";
    foreach ($usedItems as $used) {
        echo "   - Contra Bon: {$used->cb_number} (Status: {$used->cb_status})\n";
        echo "     GR Item ID: {$used->gr_item_id}\n";
    }
    echo "\n";
} else {
    echo "✅ GR items belum digunakan di contra bon\n";
    echo "\n";
}

// 7. Simulasi query getPOWithApprovedGR
echo "7. SIMULASI QUERY getPOWithApprovedGR\n";
echo "-----------------------------------\n";

// Ambil semua gr_item_id yang sudah digunakan
$usedGRItemIdsQuery = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereNotNull('cbi.gr_item_id');

if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
    $usedGRItemIdsQuery->whereNull('cb.deleted_at');
}

$usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();
echo "   Total GR items yang sudah digunakan: " . count($usedGRItemIds) . "\n";

// Cek apakah GR items ini ada di usedGRItemIds
$isUsed = false;
foreach ($grItemIds as $grItemId) {
    if (in_array($grItemId, $usedGRItemIds)) {
        $isUsed = true;
        echo "   ⚠️  GR Item ID {$grItemId} sudah digunakan\n";
    }
}

if (!$isUsed) {
    echo "   ✅ GR items belum digunakan\n";
}

// Query PO dengan GR
$poWithGR = DB::table('purchase_order_foods as po')
    ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
    ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
    ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
    ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
    ->where('gr.id', $gr->id)
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
    ->first();

if (!$poWithGR) {
    echo "   ❌ GR tidak ditemukan di query PO dengan GR\n";
    echo "   Kemungkinan masalah:\n";
    echo "     - Join dengan suppliers gagal\n";
    echo "     - Join dengan users (po_creator) gagal\n";
    echo "     - Join dengan users (gr_receiver) gagal\n";
    
    // Cek satu per satu
    echo "\n   Cek join satu per satu:\n";
    
    // Cek supplier
    $supplierCheck = DB::table('suppliers')->where('id', $po->supplier_id)->exists();
    echo "     - Supplier exists: " . ($supplierCheck ? 'YES' : 'NO') . "\n";
    
    // Cek po_creator
    $poCreatorCheck = DB::table('users')->where('id', $po->created_by)->exists();
    echo "     - PO Creator exists: " . ($poCreatorCheck ? 'YES' : 'NO') . "\n";
    
    // Cek gr_receiver
    $grReceiverCheck = DB::table('users')->where('id', $gr->received_by)->exists();
    echo "     - GR Receiver exists: " . ($grReceiverCheck ? 'YES' : 'NO') . "\n";
    
    exit;
}

echo "   ✅ GR ditemukan di query PO dengan GR\n";
echo "     PO: {$poWithGR->po_number}\n";
echo "     GR: {$poWithGR->gr_number}\n";
echo "     Supplier: {$poWithGR->supplier_name}\n";
echo "\n";

// 8. Cek GR items yang available (belum digunakan)
echo "8. CEK GR ITEMS YANG AVAILABLE\n";
echo "-----------------------------------\n";
$availableItems = DB::table('food_good_receive_items as gri')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->where('gri.good_receive_id', $gr->id)
    ->whereNotIn('gri.id', $usedGRItemIds)
    ->select(
        'gri.id',
        'gri.item_id',
        'gri.po_item_id',
        'gri.unit_id',
        'gri.qty_received',
        'poi.price as po_price'
    )
    ->get();

if ($availableItems->isEmpty()) {
    echo "❌ TIDAK ADA GR ITEMS YANG AVAILABLE\n";
    echo "   INI ALASAN UTAMA KENAPA GR TIDAK MUNCUL!\n";
    echo "   Semua items sudah digunakan di contra bon\n";
} else {
    echo "✅ Ada {$availableItems->count()} GR items yang available:\n";
    foreach ($availableItems as $index => $item) {
        echo "   Item " . ($index + 1) . ":\n";
        echo "     - ID: {$item->id}\n";
        echo "     - Item ID: " . ($item->item_id ?? 'NULL') . "\n";
        echo "     - Unit ID: " . ($item->unit_id ?? 'NULL') . "\n";
        echo "     - PO Price: " . ($item->po_price ?? 'NULL') . "\n";
        echo "\n";
    }
}

// 9. Cek apakah ada masalah dengan item_id atau unit_id yang NULL
echo "9. CEK MASALAH DATA (item_id/unit_id NULL)\n";
echo "-----------------------------------\n";
$itemsWithNull = DB::table('food_good_receive_items')
    ->where('good_receive_id', $gr->id)
    ->where(function($query) {
        $query->whereNull('item_id')
              ->orWhereNull('unit_id');
    })
    ->get();

if ($itemsWithNull->count() > 0) {
    echo "⚠️  Ada {$itemsWithNull->count()} items dengan item_id atau unit_id NULL:\n";
    foreach ($itemsWithNull as $item) {
        echo "   - GR Item ID: {$item->id}\n";
        echo "     Item ID: " . ($item->item_id ?? 'NULL') . "\n";
        echo "     Unit ID: " . ($item->unit_id ?? 'NULL') . "\n";
    }
    echo "\n";
} else {
    echo "✅ Semua items memiliki item_id dan unit_id\n";
    echo "\n";
}

// 10. Cek apakah GR masuk dalam 500 teratas (limit di query)
echo "10. CEK APAKAH GR MASUK DALAM 500 TERATAS\n";
echo "-----------------------------------\n";
// Query sama seperti di getPOWithApprovedGR
$poWithGRLimited = DB::table('purchase_order_foods as po')
    ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
    ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
    ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
    ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
    ->select(
        'po.id as po_id',
        'po.number as po_number',
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date as gr_date'
    )
    ->orderByDesc('gr.receive_date')
    ->limit(500)
    ->get();

$grInTop500 = $poWithGRLimited->where('gr_id', $gr->id)->first();

if ($grInTop500) {
    $position = $poWithGRLimited->search(function($item) use ($gr) {
        return $item->gr_id == $gr->id;
    }) + 1;
    echo "✅ GR masuk dalam 500 teratas (posisi: {$position})\n";
    echo "   Receive Date: {$grInTop500->gr_date}\n";
} else {
    echo "❌ GR TIDAK MASUK DALAM 500 TERATAS!\n";
    echo "   INI ALASAN UTAMA KENAPA GR TIDAK MUNCUL!\n";
    echo "   Query di getPOWithApprovedGR menggunakan ->limit(500)\n";
    echo "   GR dengan receive_date: {$gr->receive_date}\n";
    
    // Cek GR terakhir yang masuk
    $lastGR = $poWithGRLimited->last();
    if ($lastGR) {
        echo "   GR terakhir yang masuk: {$lastGR->gr_number} (Date: {$lastGR->gr_date})\n";
    }
    
    // Hitung berapa GR yang lebih baru
    $newerGRs = DB::table('food_good_receives')
        ->where('receive_date', '>', $gr->receive_date)
        ->count();
    echo "   Total GR yang lebih baru: {$newerGRs}\n";
}
echo "\n";

// 11. Kesimpulan
echo "========================================\n";
echo "KESIMPULAN\n";
echo "========================================\n";

if (!$gr) {
    echo "❌ GR tidak ditemukan di database\n";
} elseif ($grItems->isEmpty()) {
    echo "❌ GR tidak memiliki items - GR tidak akan muncul\n";
} elseif ($availableItems->isEmpty()) {
    echo "❌ Semua GR items sudah digunakan - GR tidak akan muncul\n";
} elseif ($itemsWithNull->count() > 0) {
    echo "⚠️  Ada items dengan data NULL - mungkin perlu dicek lebih lanjut\n";
} elseif (!$grInTop500) {
    echo "❌ GR TIDAK MUNCUL KARENA TIDAK MASUK DALAM 500 TERATAS\n";
    echo "   Solusi:\n";
    echo "   1. Hapus limit 500 di query getPOWithApprovedGR\n";
    echo "   2. Atau tambahkan pagination\n";
    echo "   3. Atau tambahkan filter tanggal untuk membatasi hasil\n";
} else {
    echo "✅ GR seharusnya muncul di menu contra bon\n";
    echo "   Jika tidak muncul, kemungkinan:\n";
    echo "   1. Ada filter tambahan di frontend\n";
    echo "   2. Ada masalah dengan join di query\n";
    echo "   3. Ada masalah dengan data items (item_id/unit_id tidak ditemukan di tabel items/units)\n";
}

echo "\n";

