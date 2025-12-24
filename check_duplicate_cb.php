<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CEK DUPLIKASI CB-20251217-0112 ===\n\n";

$contraBons = DB::table('food_contra_bons')
    ->where('number', 'CB-20251217-0112')
    ->get();

echo "Total Contra Bon dengan nomor CB-20251217-0112: " . $contraBons->count() . "\n\n";

foreach ($contraBons as $cb) {
    echo "CB ID: {$cb->id}\n";
    echo "  - Number: {$cb->number}\n";
    echo "  - Date: {$cb->date}\n";
    echo "  - Status: {$cb->status}\n";
    echo "  - PO ID: " . ($cb->po_id ?? 'NULL') . "\n";
    
    // Cek sources
    $sources = DB::table('food_contra_bon_sources')
        ->where('contra_bon_id', $cb->id)
        ->get();
    
    echo "  - Total Sources: " . $sources->count() . "\n";
    foreach ($sources as $src) {
        echo "    * Source ID: {$src->id}, Type: {$src->source_type}, GR ID: " . ($src->gr_id ?? 'NULL') . ", PO ID: " . ($src->po_id ?? 'NULL') . "\n";
        if ($src->gr_id) {
            $gr = DB::table('food_good_receives')->where('id', $src->gr_id)->first();
            if ($gr) {
                echo "      -> GR: {$gr->gr_number}\n";
            }
        }
    }
    
    // Cek items dengan gr_item_id
    $items = DB::table('food_contra_bon_items')
        ->where('contra_bon_id', $cb->id)
        ->whereNotNull('gr_item_id')
        ->get();
    
    echo "  - Total Items dengan gr_item_id: " . $items->count() . "\n";
    $grNumbers = [];
    foreach ($items as $item) {
        $grItem = DB::table('food_good_receive_items')->where('id', $item->gr_item_id)->first();
        if ($grItem) {
            $gr = DB::table('food_good_receives')->where('id', $grItem->good_receive_id)->first();
            if ($gr) {
                $grNumbers[$gr->gr_number] = true;
            }
        }
    }
    if (count($grNumbers) > 0) {
        echo "    -> GR Numbers: " . implode(', ', array_keys($grNumbers)) . "\n";
    }
    
    echo "\n";
}

// Cek GR-20251218-0032
echo "=== CEK GR-20251218-0032 ===\n\n";
$gr = DB::table('food_good_receives')
    ->where('gr_number', 'GR-20251218-0032')
    ->first();

if ($gr) {
    echo "GR ID: {$gr->id}\n";
    echo "PO ID: {$gr->po_id}\n";
    
    // Cek contra bon yang terkait
    $contraBonFromSource = DB::table('food_contra_bon_sources')
        ->where('gr_id', $gr->id)
        ->pluck('contra_bon_id')
        ->toArray();
    
    $grItems = DB::table('food_good_receive_items')
        ->where('good_receive_id', $gr->id)
        ->pluck('id')
        ->toArray();
    
    $contraBonFromItems = [];
    if (count($grItems) > 0) {
        $contraBonFromItems = DB::table('food_contra_bon_items')
            ->whereIn('gr_item_id', $grItems)
            ->pluck('contra_bon_id')
            ->toArray();
    }
    
    $allContraBonIds = array_unique(array_merge($contraBonFromSource, $contraBonFromItems));
    
    echo "Contra Bon yang terkait: " . count($allContraBonIds) . "\n";
    foreach ($allContraBonIds as $cbId) {
        $cb = DB::table('food_contra_bons')->where('id', $cbId)->first();
        if ($cb) {
            echo "  - CB: {$cb->number} (ID: {$cb->id})\n";
        }
    }
}

