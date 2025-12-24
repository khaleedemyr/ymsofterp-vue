<?php

/**
 * Test script untuk cek apakah GR numbers benar-benar masuk ke source_numbers
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TEST GR NUMBERS DI SOURCE_NUMBERS ===\n\n";

// Ambil contoh contra bon yang punya GR
$contraBon = DB::table('food_contra_bons')
    ->where('number', 'CB-20251217-0112')
    ->first();

if (!$contraBon) {
    echo "Contra Bon tidak ditemukan\n";
    exit(1);
}

echo "Contra Bon: {$contraBon->number} (ID: {$contraBon->id})\n";
echo "==========================================\n\n";

// Simulasi logic dari ContraBonController::index()
$sourceNumbers = [];
$sourceOutlets = [];
$sourceTypeDisplays = [];

// Cek sources
$sources = DB::table('food_contra_bon_sources')
    ->where('contra_bon_id', $contraBon->id)
    ->get();

echo "1. CEK SOURCES:\n";
echo "---------------\n";
foreach ($sources as $source) {
    echo "Source ID: {$source->id}, Type: {$source->source_type}, GR ID: " . ($source->gr_id ?? 'NULL') . ", PO ID: " . ($source->po_id ?? 'NULL') . "\n";
    
    if ($source->source_type === 'purchase_order' && $source->po_id) {
        $po = DB::table('purchase_order_foods')->where('id', $source->po_id)->first();
        
        if ($po) {
            echo "  PO: {$po->number}, Source Type: " . ($po->source_type ?? 'NULL') . "\n";
            
            // AMBIL GR NUMBERS DARI SOURCE
            if ($source->gr_id) {
                $grNumber = DB::table('food_good_receives')
                    ->where('id', $source->gr_id)
                    ->value('gr_number');
                if ($grNumber) {
                    echo "  ✅ GR dari source: {$grNumber}\n";
                    $sourceNumbers[] = $grNumber;
                } else {
                    echo "  ❌ GR ID {$source->gr_id} tidak ditemukan di food_good_receives\n";
                }
            } else {
                echo "  ⚠️  Source tidak punya gr_id\n";
            }
            
            // AMBIL GR NUMBERS DARI ITEMS
            $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
                ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
                ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
                ->where('cbi.contra_bon_id', $contraBon->id)
                ->whereNotNull('cbi.gr_item_id')
                ->distinct()
                ->pluck('gr.gr_number')
                ->toArray();
            
            if (count($grNumbersFromItems) > 0) {
                echo "  ✅ GR dari items: " . implode(', ', $grNumbersFromItems) . "\n";
                $sourceNumbers = array_merge($sourceNumbers, $grNumbersFromItems);
            } else {
                echo "  ⚠️  Tidak ada GR dari items\n";
            }
            
            // Get PR numbers
            if ($po->source_type === 'pr_foods' || !$po->source_type) {
                $prNumbers = DB::table('pr_foods as pr')
                    ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                    ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                    ->where('poi.purchase_order_food_id', $po->id)
                    ->distinct()
                    ->pluck('pr.pr_number')
                    ->toArray();
                
                if (count($prNumbers) > 0) {
                    echo "  ✅ PR Numbers: " . implode(', ', $prNumbers) . "\n";
                    $sourceNumbers = array_merge($sourceNumbers, $prNumbers);
                }
            }
        }
    }
    echo "\n";
}

$finalSourceNumbers = array_unique(array_filter($sourceNumbers));

echo "2. HASIL FINAL:\n";
echo "---------------\n";
echo "Source Numbers: " . (count($finalSourceNumbers) > 0 ? implode(', ', $finalSourceNumbers) : 'KOSONG') . "\n";
echo "\n";

// Cek apakah GR-20251218-0032 ada di source_numbers
if (in_array('GR-20251218-0032', $finalSourceNumbers)) {
    echo "✅ GR-20251218-0032 ADA di source_numbers\n";
} else {
    echo "❌ GR-20251218-0032 TIDAK ADA di source_numbers\n";
    echo "\n";
    echo "Debug:\n";
    echo "  - Source Numbers yang ditemukan: " . implode(', ', $finalSourceNumbers) . "\n";
}

