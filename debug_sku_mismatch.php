<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG SKU MISMATCH ===\n\n";

// 1. Cek item dengan SKU yang mirip
echo "=== ITEMS WITH SIMILAR SKU ===\n";
$similarSkuItems = Item::where('sku', 'like', 'GC-20250620-605%')
    ->orWhere('sku', 'like', '%GC-20250620-605%')
    ->get();

foreach ($similarSkuItems as $item) {
    $hasPrice = ItemPrice::where('item_id', $item->id)->exists();
    $priceStatus = $hasPrice ? 'HAS PRICE' : 'NO PRICE';
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Status: {$priceStatus}\n";
}

// 2. Cek hash validation untuk SKU yang berbeda
echo "\n=== HASH VALIDATION FOR DIFFERENT SKUS ===\n";
$itemId = 52360;
$itemName = 'Fryall';

// Hash untuk SKU di template
$templateSku = 'GC-20250620-605';
$templateHash = md5($itemId . $templateSku . $itemName);

// Hash untuk SKU di database
$dbSku = 'GC-20250620-6057';
$dbHash = md5($itemId . $dbSku . $itemName);

echo "Template SKU: {$templateSku}\n";
echo "Template Hash: {$templateHash}\n";
echo "Database SKU: {$dbSku}\n";
echo "Database Hash: {$dbHash}\n";
echo "Hashes Match: " . ($templateHash === $dbHash ? 'YES' : 'NO') . "\n";

// 3. Cek item lain yang mungkin memiliki masalah SKU serupa
echo "\n=== POTENTIAL SKU MISMATCHES ===\n";
$potentialMismatches = Item::where('sku', 'like', 'GC-20250620-%')
    ->orWhere('sku', 'like', 'MK-20250620-%')
    ->limit(10)
    ->get();

foreach ($potentialMismatches as $item) {
    // Cek apakah ada SKU yang mirip tapi berbeda
    $similarSku = Item::where('sku', '!=', $item->sku)
        ->where('sku', 'like', substr($item->sku, 0, -1) . '%')
        ->orWhere('sku', 'like', $item->sku . '%')
        ->first();
    
    if ($similarSku) {
        echo "Potential mismatch: {$item->name} (SKU: {$item->sku}) vs {$similarSku->name} (SKU: {$similarSku->sku})\n";
    }
}

// 4. Cek item yang tidak terupdate hari ini
echo "\n=== ITEMS NOT UPDATED TODAY ===\n";
$itemsNotUpdatedToday = Item::where('status', 'active')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id')
              ->whereDate('item_prices.updated_at', now()->format('Y-m-d'));
    })
    ->limit(10)
    ->get();

foreach ($itemsNotUpdatedToday as $item) {
    $hasPrice = ItemPrice::where('item_id', $item->id)->exists();
    $priceStatus = $hasPrice ? 'HAS PRICE' : 'NO PRICE';
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Status: {$priceStatus}\n";
}

// 5. Solusi untuk masalah SKU mismatch
echo "\n=== RECOMMENDED SOLUTIONS ===\n";
echo "1. Update template dengan SKU yang benar dari database\n";
echo "2. Atau update database dengan SKU yang ada di template\n";
echo "3. Periksa semua item untuk memastikan SKU konsisten\n";
echo "4. Re-import dengan data yang sudah diperbaiki\n\n";

// 6. Generate list item yang perlu diperbaiki SKU-nya
echo "=== ITEMS THAT NEED SKU FIX ===\n";
$itemsNeedingSkuFix = Item::where('status', 'active')
    ->where('sku', 'like', 'GC-20250620-%')
    ->orWhere('sku', 'like', 'MK-20250620-%')
    ->limit(20)
    ->get();

foreach ($itemsNeedingSkuFix as $item) {
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku}\n";
}

echo "\n=== DEBUG COMPLETED ===\n"; 