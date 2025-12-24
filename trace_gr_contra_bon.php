<?php

/**
 * Script untuk trace kenapa GR number tidak muncul di menu Contra Bon
 * 
 * Usage: php trace_gr_contra_bon.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TRACE GR NUMBER DI CONTRA BON ===\n\n";

// 1. Cek struktur tabel food_contra_bons
echo "1. CEK STRUKTUR TABEL FOOD_CONTRA_BONS:\n";
echo "----------------------------------------\n";
$columns = DB::select("SHOW COLUMNS FROM food_contra_bons");
foreach ($columns as $col) {
    if (stripos($col->Field, 'gr') !== false) {
        echo "  - {$col->Field}: {$col->Type}\n";
    }
}
echo "\n";

// 2. Cek struktur tabel food_contra_bon_items
echo "2. CEK STRUKTUR TABEL FOOD_CONTRA_BON_ITEMS:\n";
echo "-----------------------------------------------\n";
$columns = DB::select("SHOW COLUMNS FROM food_contra_bon_items");
foreach ($columns as $col) {
    if (stripos($col->Field, 'gr') !== false) {
        echo "  - {$col->Field}: {$col->Type}\n";
    }
}
echo "\n";

// 3. Cek struktur tabel food_contra_bon_sources
echo "3. CEK STRUKTUR TABEL FOOD_CONTRA_BON_SOURCES:\n";
echo "------------------------------------------------\n";
$columns = DB::select("SHOW COLUMNS FROM food_contra_bon_sources");
foreach ($columns as $col) {
    if (stripos($col->Field, 'gr') !== false) {
        echo "  - {$col->Field}: {$col->Type}\n";
    }
}
echo "\n";

// 4. Cek data contra bon yang punya gr_id (jika ada kolom gr_id di food_contra_bons)
echo "4. CEK CONTRA BON YANG PUNYA GR_ID:\n";
echo "------------------------------------\n";
try {
    $contraBonsWithGR = DB::table('food_contra_bons')
        ->whereNotNull('gr_id')
        ->select('id', 'number', 'gr_id', 'po_id', 'source_type', 'date')
        ->get();
    
    echo "Total Contra Bon dengan gr_id: " . $contraBonsWithGR->count() . "\n";
    if ($contraBonsWithGR->count() > 0) {
        echo "\nContoh data:\n";
        foreach ($contraBonsWithGR->take(5) as $cb) {
            echo "  - CB ID: {$cb->id}, Number: {$cb->number}, GR ID: {$cb->gr_id}, PO ID: {$cb->po_id}, Source Type: {$cb->source_type}\n";
        }
    }
} catch (\Exception $e) {
    echo "  Kolom gr_id tidak ada di tabel food_contra_bons\n";
    $contraBonsWithGR = collect([]);
}

if ($contraBonsWithGR->count() > 0) {
    echo "Total Contra Bon dengan gr_id: " . $contraBonsWithGR->count() . "\n";
    echo "\nContoh data:\n";
    foreach ($contraBonsWithGR->take(5) as $cb) {
        echo "  - CB ID: {$cb->id}, Number: {$cb->number}, GR ID: {$cb->gr_id}, PO ID: {$cb->po_id}, Source Type: {$cb->source_type}\n";
    }
} else {
    echo "Total Contra Bon dengan gr_id: 0\n";
}
echo "\n";

// 5. Cek food_contra_bon_sources yang punya gr_id
echo "5. CEK FOOD_CONTRA_BON_SOURCES YANG PUNYA GR_ID:\n";
echo "--------------------------------------------------\n";
$sourcesWithGR = DB::table('food_contra_bon_sources')
    ->whereNotNull('gr_id')
    ->select('id', 'contra_bon_id', 'source_type', 'gr_id', 'po_id')
    ->get();

echo "Total Sources dengan gr_id: " . $sourcesWithGR->count() . "\n";
if ($sourcesWithGR->count() > 0) {
    echo "\nContoh data:\n";
    foreach ($sourcesWithGR->take(5) as $source) {
        echo "  - Source ID: {$source->id}, CB ID: {$source->contra_bon_id}, Source Type: {$source->source_type}, GR ID: {$source->gr_id}, PO ID: {$source->po_id}\n";
    }
}
echo "\n";

// 6. Cek food_contra_bon_items yang punya gr_item_id
echo "6. CEK FOOD_CONTRA_BON_ITEMS YANG PUNYA GR_ITEM_ID:\n";
echo "----------------------------------------------------\n";
$itemsWithGR = DB::table('food_contra_bon_items')
    ->whereNotNull('gr_item_id')
    ->select('id', 'contra_bon_id', 'gr_item_id', 'item_id', 'po_item_id')
    ->get();

echo "Total Items dengan gr_item_id: " . $itemsWithGR->count() . "\n";
if ($itemsWithGR->count() > 0) {
    echo "\nContoh data:\n";
    foreach ($itemsWithGR->take(5) as $item) {
        echo "  - Item ID: {$item->id}, CB ID: {$item->contra_bon_id}, GR Item ID: {$item->gr_item_id}, Item ID: {$item->item_id}, PO Item ID: {$item->po_item_id}\n";
    }
}
echo "\n";

// 7. Cek GR yang terkait dengan PO yang ada di contra bon
echo "7. CEK GR YANG TERKAIT DENGAN PO DI CONTRA BON:\n";
echo "------------------------------------------------\n";
$contraBonsWithPO = DB::table('food_contra_bons')
    ->whereNotNull('po_id')
    ->select('id', 'number', 'po_id')
    ->get();

echo "Total Contra Bon dengan po_id: " . $contraBonsWithPO->count() . "\n";

if ($contraBonsWithPO->count() > 0) {
    echo "\nCek GR untuk setiap PO:\n";
    foreach ($contraBonsWithPO->take(10) as $cb) {
        $grNumbers = DB::table('food_good_receives')
            ->where('po_id', $cb->po_id)
            ->select('id', 'gr_number', 'receive_date')
            ->get();
        
        echo "  - CB: {$cb->number}, PO ID: {$cb->po_id}, GR ID di CB: " . ($cb->gr_id ?? 'NULL') . "\n";
        if ($grNumbers->count() > 0) {
            foreach ($grNumbers as $gr) {
                echo "    -> GR: {$gr->gr_number} (ID: {$gr->id}, Date: {$gr->receive_date})\n";
            }
        } else {
            echo "    -> Tidak ada GR untuk PO ini\n";
        }
    }
}
echo "\n";

// 8. Cek contoh GR yang seharusnya muncul
echo "8. CEK CONTOH GR YANG SEHARUSNYA MUNCUL:\n";
echo "-----------------------------------------\n";
$sampleGR = DB::table('food_good_receives as gr')
    ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->leftJoin('food_contra_bons as cb', 'cb.po_id', '=', 'po.id')
    ->select(
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date',
        'po.id as po_id',
        'po.number as po_number',
        'cb.id as contra_bon_id',
        'cb.number as contra_bon_number'
    )
    ->orderByDesc('gr.receive_date')
    ->limit(10)
    ->get();

echo "Contoh GR dan relasinya dengan Contra Bon:\n";
foreach ($sampleGR as $gr) {
    echo "  - GR: {$gr->gr_number} (ID: {$gr->gr_id}, Date: {$gr->receive_date})\n";
    echo "    PO: {$gr->po_number} (ID: {$gr->po_id})\n";
    if ($gr->contra_bon_id) {
        echo "    -> Ada Contra Bon: {$gr->contra_bon_number} (ID: {$gr->contra_bon_id})\n";
    } else {
        echo "    -> TIDAK ADA CONTRA BON (ini yang mungkin masalahnya)\n";
    }
    echo "\n";
}

// 9. Analisis: Cek bagaimana GR number seharusnya ditampilkan
echo "9. ANALISIS: BAGAIMANA GR NUMBER SEHARUSNYA DITAMPILKAN:\n";
echo "--------------------------------------------------------\n";
echo "Dari kode ContraBonController::index(), saya lihat:\n";
echo "  - Ketika source_type = 'purchase_order', hanya mengambil PR numbers atau RO numbers\n";
echo "  - TIDAK mengambil GR numbers dari food_good_receives\n";
echo "  - GR number seharusnya diambil dari:\n";
echo "    * contra_bons.gr_id -> food_good_receives.id -> food_good_receives.gr_number\n";
echo "    * ATAU dari contra_bon_items.gr_item_id -> food_good_receive_items.good_receive_id -> food_good_receives.gr_number\n";
echo "    * ATAU dari contra_bon_sources.gr_id -> food_good_receives.id -> food_good_receives.gr_number\n";
echo "\n";

// 10. Cek relasi GR dengan contra bon melalui items
echo "10. CEK RELASI GR DENGAN CONTRA BON MELALUI ITEMS:\n";
echo "---------------------------------------------------\n";
$grFromItems = DB::table('food_contra_bon_items as cbi')
    ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->select(
        'cb.id as contra_bon_id',
        'cb.number as contra_bon_number',
        'gr.id as gr_id',
        'gr.gr_number',
        'gr.receive_date'
    )
    ->distinct()
    ->limit(10)
    ->get();

echo "GR yang terkait melalui contra_bon_items:\n";
foreach ($grFromItems as $item) {
    echo "  - CB: {$item->contra_bon_number} (ID: {$item->contra_bon_id})\n";
    echo "    -> GR: {$item->gr_number} (ID: {$item->gr_id}, Date: {$item->receive_date})\n";
}
echo "\n";

// 11. Kesimpulan
echo "11. KESIMPULAN:\n";
echo "---------------\n";
echo "Masalah: GR number tidak muncul di source_numbers karena:\n";
echo "  1. Di ContraBonController::index(), ketika source_type = 'purchase_order',\n";
echo "     hanya mengambil PR numbers atau RO numbers, TIDAK mengambil GR numbers\n";
echo "  2. GR number perlu ditambahkan ke source_numbers dengan cara:\n";
echo "     - Ambil dari contra_bons.gr_id (jika ada)\n";
echo "     - ATAU ambil dari contra_bon_sources.gr_id (jika ada)\n";
echo "     - ATAU ambil dari contra_bon_items.gr_item_id -> food_good_receive_items.good_receive_id\n";
echo "\n";
echo "Solusi: Modifikasi ContraBonController::index() untuk menambahkan GR numbers\n";
echo "        ke dalam source_numbers ketika source_type = 'purchase_order'\n";

