<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$grNumber = 'GR-20251218-0032';

echo "=== TRACE DETAIL GR-20251218-0032 ===\n\n";

// 1. Cek GR
$gr = DB::table('food_good_receives')
    ->where('gr_number', $grNumber)
    ->first();

if (!$gr) {
    echo "❌ GR tidak ditemukan\n";
    exit(1);
}

echo "GR Info:\n";
echo "  - ID: {$gr->id}\n";
echo "  - PO ID: {$gr->po_id}\n";
echo "  - Receive Date: {$gr->receive_date}\n";
echo "\n";

// 2. Cek semua contra bon yang terkait dengan GR ini
echo "2. CEK SEMUA CONTRA BON YANG TERKAIT:\n";
echo "---------------------------------------\n";

// Via sources
$contraBonFromSources = DB::table('food_contra_bon_sources as cbs')
    ->join('food_contra_bons as cb', 'cbs.contra_bon_id', '=', 'cb.id')
    ->where('cbs.gr_id', $gr->id)
    ->select('cb.id', 'cb.number', 'cb.date', 'cb.status', 'cbs.id as source_id', 'cbs.po_id as source_po_id')
    ->get();

echo "Via Sources:\n";
if ($contraBonFromSources->count() > 0) {
    foreach ($contraBonFromSources as $cb) {
        echo "  - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status})\n";
        echo "    Source ID: {$cb->source_id}, PO ID di source: {$cb->source_po_id}\n";
    }
} else {
    echo "  ❌ Tidak ada\n";
}
echo "\n";

// Via items
$grItems = DB::table('food_good_receive_items')
    ->where('good_receive_id', $gr->id)
    ->pluck('id')
    ->toArray();

echo "GR Items: " . count($grItems) . " items\n";
if (count($grItems) > 0) {
    echo "GR Item IDs: " . implode(', ', array_slice($grItems, 0, 10)) . (count($grItems) > 10 ? '...' : '') . "\n";
}
echo "\n";

$contraBonFromItems = DB::table('food_contra_bon_items as cbi')
    ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
    ->whereIn('cbi.gr_item_id', $grItems)
    ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
    ->distinct()
    ->get();

echo "Via Items:\n";
if ($contraBonFromItems->count() > 0) {
    foreach ($contraBonFromItems as $cb) {
        echo "  - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status})\n";
    }
} else {
    echo "  ❌ Tidak ada\n";
}
echo "\n";

// 3. Cek semua contra bon untuk PO yang sama
echo "3. CEK SEMUA CONTRA BON UNTUK PO YANG SAMA:\n";
echo "--------------------------------------------\n";
$contraBonForPO = DB::table('food_contra_bons')
    ->where('po_id', $gr->po_id)
    ->select('id', 'number', 'date', 'status', 'source_type')
    ->get();

echo "Contra Bon untuk PO ID {$gr->po_id}:\n";
if ($contraBonForPO->count() > 0) {
    foreach ($contraBonForPO as $cb) {
        echo "  - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status}, Source Type: {$cb->source_type})\n";
        
        // Cek sources untuk CB ini
        $sources = DB::table('food_contra_bon_sources')
            ->where('contra_bon_id', $cb->id)
            ->get();
        
        echo "    Sources:\n";
        foreach ($sources as $src) {
            echo "      - Source ID: {$src->id}, Type: {$src->source_type}, GR ID: " . ($src->gr_id ?? 'NULL') . ", PO ID: " . ($src->po_id ?? 'NULL') . "\n";
        }
    }
} else {
    echo "  ❌ Tidak ada\n";
}
echo "\n";

// 4. Simulasi logic dari controller untuk CB yang terkait
echo "4. SIMULASI LOGIC DARI CONTROLLER:\n";
echo "-----------------------------------\n";

$allContraBonIds = $contraBonFromSources->pluck('id')->merge($contraBonFromItems->pluck('id'))->unique();

foreach ($allContraBonIds as $cbId) {
    $cb = DB::table('food_contra_bons')->where('id', $cbId)->first();
    echo "CB: {$cb->number} (ID: {$cb->id})\n";
    
    $sourceNumbers = [];
    
    // Cek sources
    $sources = DB::table('food_contra_bon_sources')
        ->where('contra_bon_id', $cbId)
        ->get();
    
    foreach ($sources as $source) {
        if ($source->source_type === 'purchase_order' && $source->po_id) {
            // AMBIL GR NUMBERS DARI SOURCE
            if ($source->gr_id) {
                $grNumberFromSource = DB::table('food_good_receives')
                    ->where('id', $source->gr_id)
                    ->value('gr_number');
                if ($grNumberFromSource) {
                    $sourceNumbers[] = $grNumberFromSource;
                    echo "  ✅ GR dari source: {$grNumberFromSource}\n";
                }
            }
            
            // AMBIL GR NUMBERS DARI ITEMS
            $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
                ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
                ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
                ->where('cbi.contra_bon_id', $cbId)
                ->whereNotNull('cbi.gr_item_id')
                ->distinct()
                ->pluck('gr.gr_number')
                ->toArray();
            
            if (count($grNumbersFromItems) > 0) {
                echo "  ✅ GR dari items: " . implode(', ', $grNumbersFromItems) . "\n";
                $sourceNumbers = array_merge($sourceNumbers, $grNumbersFromItems);
            }
        }
    }
    
    $finalSourceNumbers = array_unique(array_filter($sourceNumbers));
    echo "  Final Source Numbers: " . (count($finalSourceNumbers) > 0 ? implode(', ', $finalSourceNumbers) : 'KOSONG') . "\n";
    
    if (in_array($grNumber, $finalSourceNumbers)) {
        echo "  ✅ {$grNumber} ADA di source_numbers\n";
    } else {
        echo "  ❌ {$grNumber} TIDAK ADA di source_numbers\n";
    }
    echo "\n";
}

