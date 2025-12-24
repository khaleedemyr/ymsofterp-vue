<?php

/**
 * Script untuk trace detail kenapa GR number tidak muncul di menu Contra Bon
 * Cek apakah GR sudah dibuat contra bon atau ada yang di-delete
 * 
 * Usage: php trace_gr_missing_detail.php [gr_number]
 * Example: php trace_gr_missing_detail.php GR-20251218-0032
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$grNumber = $argv[1] ?? null;

echo "=== TRACE DETAIL GR NUMBER DI CONTRA BON ===\n\n";

if ($grNumber) {
    echo "Mencari GR: {$grNumber}\n";
    echo "========================================\n\n";
    
    // 1. Cek apakah GR ada di database
    $gr = DB::table('food_good_receives')
        ->where('gr_number', $grNumber)
        ->first();
    
    if (!$gr) {
        echo "❌ ERROR: GR {$grNumber} TIDAK DITEMUKAN di database!\n";
        echo "Kemungkinan: GR sudah di-delete atau nomor salah.\n";
        exit(1);
    }
    
    echo "✅ GR ditemukan:\n";
    echo "   - ID: {$gr->id}\n";
    echo "   - PO ID: {$gr->po_id}\n";
    echo "   - Receive Date: {$gr->receive_date}\n";
    echo "   - Supplier ID: {$gr->supplier_id}\n";
    echo "\n";
    
    // 2. Cek PO yang terkait
    $po = DB::table('purchase_order_foods')
        ->where('id', $gr->po_id)
        ->first();
    
    if ($po) {
        echo "✅ PO ditemukan:\n";
        echo "   - PO Number: {$po->number}\n";
        echo "   - Source Type: " . ($po->source_type ?? 'NULL') . "\n";
        echo "\n";
    } else {
        echo "❌ PO dengan ID {$gr->po_id} TIDAK DITEMUKAN!\n";
        echo "\n";
    }
    
    // 3. Cek apakah GR sudah dibuat contra bon melalui sources
    echo "3. CEK APAKAH GR SUDAH DIBUAT CONTRA BON (MELALUI SOURCES):\n";
    echo "------------------------------------------------------------\n";
    $contraBonFromSource = DB::table('food_contra_bon_sources as cbs')
        ->join('food_contra_bons as cb', 'cbs.contra_bon_id', '=', 'cb.id')
        ->where('cbs.gr_id', $gr->id)
        ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
        ->get();
    
    if ($contraBonFromSource->count() > 0) {
        echo "✅ GR sudah dibuat contra bon melalui sources:\n";
        foreach ($contraBonFromSource as $cb) {
            echo "   - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status}, Date: {$cb->date})\n";
        }
    } else {
        echo "❌ GR BELUM dibuat contra bon melalui sources\n";
    }
    echo "\n";
    
    // 4. Cek apakah GR sudah dibuat contra bon melalui items
    echo "4. CEK APAKAH GR SUDAH DIBUAT CONTRA BON (MELALUI ITEMS):\n";
    echo "----------------------------------------------------------\n";
    $grItems = DB::table('food_good_receive_items')
        ->where('good_receive_id', $gr->id)
        ->pluck('id')
        ->toArray();
    
    if (count($grItems) > 0) {
        $contraBonFromItems = DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
            ->whereIn('cbi.gr_item_id', $grItems)
            ->select('cb.id', 'cb.number', 'cb.date', 'cb.status')
            ->distinct()
            ->get();
        
        if ($contraBonFromItems->count() > 0) {
            echo "✅ GR sudah dibuat contra bon melalui items:\n";
            foreach ($contraBonFromItems as $cb) {
                echo "   - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status}, Date: {$cb->date})\n";
            }
        } else {
            echo "❌ GR BELUM dibuat contra bon melalui items\n";
            echo "   - Total GR Items: " . count($grItems) . "\n";
            echo "   - GR Item IDs: " . implode(', ', array_slice($grItems, 0, 5)) . (count($grItems) > 5 ? '...' : '') . "\n";
        }
    } else {
        echo "❌ GR tidak punya items!\n";
    }
    echo "\n";
    
    // 5. Cek apakah ada contra bon untuk PO yang sama
    echo "5. CEK APAKAH ADA CONTRA BON UNTUK PO YANG SAMA:\n";
    echo "-------------------------------------------------\n";
    $contraBonFromPO = DB::table('food_contra_bons')
        ->where('po_id', $gr->po_id)
        ->select('id', 'number', 'date', 'status', 'source_type')
        ->get();
    
    if ($contraBonFromPO->count() > 0) {
        echo "✅ Ada contra bon untuk PO yang sama:\n";
        foreach ($contraBonFromPO as $cb) {
            echo "   - CB: {$cb->number} (ID: {$cb->id}, Status: {$cb->status}, Source Type: {$cb->source_type})\n";
            
            // Cek apakah CB ini punya source dengan gr_id
            $sourceWithGR = DB::table('food_contra_bon_sources')
                ->where('contra_bon_id', $cb->id)
                ->where('gr_id', $gr->id)
                ->first();
            
            if ($sourceWithGR) {
                echo "      ✅ Source punya gr_id = {$gr->id}\n";
            } else {
                echo "      ❌ Source TIDAK punya gr_id = {$gr->id}\n";
                
                // Cek gr_id di sources untuk CB ini
                $sources = DB::table('food_contra_bon_sources')
                    ->where('contra_bon_id', $cb->id)
                    ->select('id', 'gr_id', 'po_id', 'source_type')
                    ->get();
                
                echo "      Sources untuk CB ini:\n";
                foreach ($sources as $src) {
                    echo "        - Source ID: {$src->id}, GR ID: " . ($src->gr_id ?? 'NULL') . ", PO ID: {$src->po_id}, Type: {$src->source_type}\n";
                }
            }
        }
    } else {
        echo "❌ TIDAK ada contra bon untuk PO ID {$gr->po_id}\n";
    }
    echo "\n";
    
    // 6. Kesimpulan
    echo "6. KESIMPULAN:\n";
    echo "--------------\n";
    $hasContraBon = $contraBonFromSource->count() > 0 || $contraBonFromItems->count() > 0;
    
    if ($hasContraBon) {
        echo "✅ GR {$grNumber} SUDAH dibuat contra bon\n";
        echo "   - Melalui sources: " . ($contraBonFromSource->count() > 0 ? 'YA' : 'TIDAK') . "\n";
        echo "   - Melalui items: " . ($contraBonFromItems->count() > 0 ? 'YA' : 'TIDAK') . "\n";
        echo "\n";
        echo "⚠️  MASALAH: GR sudah dibuat contra bon, tapi tidak muncul di source_numbers\n";
        echo "   Kemungkinan: Logic di ContraBonController::index() tidak mengambil GR numbers\n";
    } else {
        echo "❌ GR {$grNumber} BELUM dibuat contra bon\n";
        echo "   - GR bisa dipilih untuk membuat contra bon baru\n";
    }
    
} else {
    // Mode: Cek semua GR yang tidak muncul di contra bon
    echo "Mode: Cek semua GR yang tidak muncul di contra bon\n";
    echo "==================================================\n\n";
    
    // Ambil semua GR yang punya PO
    $allGRs = DB::table('food_good_receives as gr')
        ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
        ->select('gr.id', 'gr.gr_number', 'gr.po_id', 'po.number as po_number', 'po.source_type')
        ->orderByDesc('gr.receive_date')
        ->limit(50)
        ->get();
    
    echo "Total GR yang dicek: " . $allGRs->count() . "\n\n";
    
    $grWithoutCB = [];
    $grWithCBButNotInSourceNumbers = [];
    
    foreach ($allGRs as $gr) {
        // Cek apakah GR sudah dibuat contra bon
        $hasContraBonSource = DB::table('food_contra_bon_sources')
            ->where('gr_id', $gr->id)
            ->exists();
        
        $grItems = DB::table('food_good_receive_items')
            ->where('good_receive_id', $gr->id)
            ->pluck('id')
            ->toArray();
        
        $hasContraBonItem = false;
        if (count($grItems) > 0) {
            $hasContraBonItem = DB::table('food_contra_bon_items')
                ->whereIn('gr_item_id', $grItems)
                ->exists();
        }
        
        $hasContraBon = $hasContraBonSource || $hasContraBonItem;
        
        if (!$hasContraBon) {
            $grWithoutCB[] = $gr;
        } else {
            // Cek apakah GR number muncul di source_numbers
            // Ambil contra bon yang terkait
            $contraBonIds = [];
            
            if ($hasContraBonSource) {
                $contraBonIds = array_merge($contraBonIds, 
                    DB::table('food_contra_bon_sources')
                        ->where('gr_id', $gr->id)
                        ->pluck('contra_bon_id')
                        ->toArray()
                );
            }
            
            if ($hasContraBonItem) {
                $contraBonIds = array_merge($contraBonIds,
                    DB::table('food_contra_bon_items as cbi')
                        ->whereIn('cbi.gr_item_id', $grItems)
                        ->pluck('cbi.contra_bon_id')
                        ->toArray()
                );
            }
            
            $contraBonIds = array_unique($contraBonIds);
            
            // Simulasi logic dari ContraBonController untuk cek apakah GR number muncul
            // (Ini hanya simulasi, tidak bisa 100% akurat tanpa menjalankan controller sebenarnya)
            $grWithCBButNotInSourceNumbers[] = [
                'gr' => $gr,
                'contra_bon_ids' => $contraBonIds
            ];
        }
    }
    
    echo "Hasil Analisis:\n";
    echo "---------------\n";
    echo "GR yang BELUM dibuat contra bon: " . count($grWithoutCB) . "\n";
    if (count($grWithoutCB) > 0) {
        echo "\nContoh GR yang belum dibuat contra bon:\n";
        foreach (array_slice($grWithoutCB, 0, 10) as $gr) {
            echo "  - {$gr->gr_number} (PO: {$gr->po_number}, Source Type: " . ($gr->source_type ?? 'NULL') . ")\n";
        }
    }
    
    echo "\nGR yang SUDAH dibuat contra bon: " . count($grWithCBButNotInSourceNumbers) . "\n";
    if (count($grWithCBButNotInSourceNumbers) > 0) {
        echo "\nContoh GR yang sudah dibuat contra bon:\n";
        foreach (array_slice($grWithCBButNotInSourceNumbers, 0, 10) as $item) {
            $gr = $item['gr'];
            $cbIds = $item['contra_bon_ids'];
            $cbNumbers = DB::table('food_contra_bons')
                ->whereIn('id', $cbIds)
                ->pluck('number')
                ->toArray();
            echo "  - {$gr->gr_number} (PO: {$gr->po_number})\n";
            echo "    -> Contra Bon: " . implode(', ', $cbNumbers) . "\n";
        }
    }
    
    echo "\n";
    echo "7. REKOMENDASI:\n";
    echo "---------------\n";
    echo "Untuk GR yang sudah dibuat contra bon tapi tidak muncul:\n";
    echo "  - Pastikan logic di ContraBonController::index() mengambil GR numbers\n";
    echo "  - Cek apakah gr_id ada di food_contra_bon_sources\n";
    echo "  - Cek apakah gr_item_id ada di food_contra_bon_items\n";
}

