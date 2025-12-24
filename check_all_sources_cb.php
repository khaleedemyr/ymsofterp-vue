<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ContraBon;
use Illuminate\Support\Facades\DB;

echo "=== CEK SEMUA SOURCES UNTUK CB-20251217-0112 ===\n\n";

$contraBon = ContraBon::with(['sources.purchaseOrder'])
    ->where('number', 'CB-20251217-0112')
    ->first();

if (!$contraBon) {
    echo "Contra Bon tidak ditemukan\n";
    exit(1);
}

echo "Contra Bon: {$contraBon->number} (ID: {$contraBon->id})\n";
echo "Total Sources: " . $contraBon->sources->count() . "\n\n";

// Cek semua sources
foreach ($contraBon->sources as $idx => $source) {
    echo "Source #" . ($idx + 1) . ":\n";
    echo "  - ID: {$source->id}\n";
    echo "  - Type: {$source->source_type}\n";
    echo "  - GR ID: " . ($source->gr_id ?? 'NULL') . "\n";
    echo "  - PO ID: " . ($source->po_id ?? 'NULL') . "\n";
    
    if ($source->gr_id) {
        $gr = DB::table('food_good_receives')
            ->where('id', $source->gr_id)
            ->first();
        if ($gr) {
            echo "  - GR Number: {$gr->gr_number}\n";
        }
    }
    
    if ($source->purchaseOrder) {
        echo "  - PO Number: {$source->purchaseOrder->number}\n";
    }
    echo "\n";
}

// Cek semua items untuk CB ini
echo "CEK SEMUA ITEMS:\n";
echo "----------------\n";
$items = DB::table('food_contra_bon_items')
    ->where('contra_bon_id', $contraBon->id)
    ->whereNotNull('gr_item_id')
    ->get();

echo "Total Items dengan gr_item_id: " . $items->count() . "\n\n";

$grNumbersFromItems = [];
foreach ($items as $item) {
    $grItem = DB::table('food_good_receive_items')
        ->where('id', $item->gr_item_id)
        ->first();
    
    if ($grItem) {
        $gr = DB::table('food_good_receives')
            ->where('id', $grItem->good_receive_id)
            ->first();
        
        if ($gr) {
            $grNumbersFromItems[$gr->gr_number] = true;
            echo "  - Item ID: {$item->id}, GR Item ID: {$item->gr_item_id}, GR: {$gr->gr_number}\n";
        }
    }
}

echo "\nGR Numbers dari items: " . implode(', ', array_keys($grNumbersFromItems)) . "\n";

// Cek apakah GR-20251218-0032 ada
if (isset($grNumbersFromItems['GR-20251218-0032'])) {
    echo "\n✅ GR-20251218-0032 DITEMUKAN di items!\n";
} else {
    echo "\n❌ GR-20251218-0032 TIDAK ditemukan di items\n";
}

