<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$item = DB::table('items')->where('name', 'Barbeque Sauce')->first();
if (! $item) {
    echo "Item Barbeque Sauce tidak ditemukan.\n";
    exit(1);
}

$itemId = (int) $item->id;
$mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;

$rows = DB::table('item_prices')->where('item_id', $itemId)->get();
if ($rows->isEmpty()) {
    echo "Tidak ada item_prices untuk item {$itemId}.\n";
    exit(1);
}

$apply = in_array('--apply', $argv ?? [], true);

echo "=== Barbeque Sauce item_prices (id {$itemId}) ===\n";
echo "medium_conversion_qty: {$mediumConv}\n\n";

$updated = 0;
foreach ($rows as $row) {
    $old = (float) $row->price;
    if ($old <= 0) {
        echo "Skip id={$row->id} ({$row->availability_price_type}): harga 0\n";
        continue;
    }

    $new = round($old * $mediumConv, 2);
    echo sprintf(
        "id=%d type=%s: %s -> %s\n",
        $row->id,
        $row->availability_price_type,
        number_format($old, 2, '.', ','),
        number_format($new, 2, '.', ','),
    );

    if ($apply) {
        DB::table('item_prices')->where('id', $row->id)->update([
            'price' => $new,
            'updated_at' => now(),
        ]);
        $updated++;
    }
}

if (! $apply) {
    echo "\nDry-run. Tambahkan --apply untuk update.\n";
    exit(0);
}

$pack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Pack');
echo "\nUpdated {$updated} baris item_prices.\n";
echo "Harga Pack (FO/GR) setelah update: {$pack}\n";
