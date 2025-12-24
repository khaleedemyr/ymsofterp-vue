<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ContraBon;
use Illuminate\Support\Facades\DB;

echo "=== TEST CB ID 2657 (yang punya GR-20251218-0032) ===\n\n";

$contraBon = ContraBon::with(['sources.purchaseOrder', 'sources.retailFood', 'sources.warehouseRetailFood', 'sources.retailNonFood'])
    ->find(2657);

if (!$contraBon) {
    echo "Contra Bon tidak ditemukan\n";
    exit(1);
}

echo "Contra Bon: {$contraBon->number} (ID: {$contraBon->id})\n";
echo "Total Sources: " . $contraBon->sources->count() . "\n\n";

// Simulasi logic dari ContraBonController::index()
$sourceNumbers = [];
$sourceOutlets = [];
$sourceTypeDisplays = [];

if ($contraBon->sources && $contraBon->sources->count() > 0) {
    foreach ($contraBon->sources as $source) {
        echo "Source ID: {$source->id}, Type: {$source->source_type}\n";
        echo "  - GR ID: " . ($source->gr_id ?? 'NULL') . "\n";
        echo "  - PO ID: " . ($source->po_id ?? 'NULL') . "\n";
        
        if ($source->source_type === 'purchase_order' && $source->purchaseOrder) {
            $po = $source->purchaseOrder;
            echo "  ✅ PO: {$po->number}\n";
            
            // AMBIL GR NUMBERS DARI SOURCE
            if ($source->gr_id) {
                $grNumber = DB::table('food_good_receives')
                    ->where('id', $source->gr_id)
                    ->value('gr_number');
                if ($grNumber) {
                    echo "  ✅ GR dari source: {$grNumber}\n";
                    $sourceNumbers[] = $grNumber;
                }
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
                $sourceTypeDisplays[] = 'PR Foods';
            } elseif ($po->source_type === 'ro_supplier') {
                $roData = DB::table('food_floor_orders as fo')
                    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                    ->where('poi.purchase_order_food_id', $po->id)
                    ->select('fo.order_number', 'o.nama_outlet')
                    ->distinct()
                    ->get();
                
                $roNumbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                if (count($roNumbers) > 0) {
                    echo "  ✅ RO Numbers: " . implode(', ', $roNumbers) . "\n";
                    $sourceNumbers = array_merge($sourceNumbers, $roNumbers);
                }
                $sourceOutlets = array_merge($sourceOutlets, $roData->pluck('nama_outlet')->unique()->filter()->toArray());
                $sourceTypeDisplays[] = 'RO Supplier';
            }
        }
        echo "\n";
    }
    
    $finalSourceNumbers = array_unique(array_filter($sourceNumbers));
    
    echo "HASIL FINAL:\n";
    echo "------------\n";
    echo "Source Numbers: " . (count($finalSourceNumbers) > 0 ? implode(', ', $finalSourceNumbers) : 'KOSONG') . "\n";
    echo "Source Type Displays: " . implode(', ', array_unique($sourceTypeDisplays)) . "\n";
    echo "\n";
    
    if (in_array('GR-20251218-0032', $finalSourceNumbers)) {
        echo "✅ GR-20251218-0032 ADA di source_numbers\n";
    } else {
        echo "❌ GR-20251218-0032 TIDAK ADA di source_numbers\n";
    }
}

