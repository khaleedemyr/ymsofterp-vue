<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

$itemNames = [
    'Alas Sandwich',
    'Alumunium Foil',
    'Artisan Tea Ceylon Green Tea',
    'Artisan Tea Ginger & Mint',
    'Bawang Goreng',
    'Beef Black Angus Rib Eye 250gr',
    'Beef Rasher',
    'Beras Jepang',
    'Beras Lokal',
    'Beras Pandan Wangi',
    'Biji Wijen Hitam',
    'Black Pepper',
    'Black Truffle Paste',
    'Blue Cheese',
    'Buah Lychee Can',
    'Buah Peach Can',
    'Butter Salted',
    'Cappucinno Powder',
];

$apply = in_array('--apply', $argv ?? [], true);
$foFrom = '2026-06-01';
$foTo = '2026-06-30';
$updatedIp = 0;
$fixedIds = [];

echo '=== Fix batch 18 Main Store (item_prices + FO) ===' . PHP_EOL;
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

foreach ($itemNames as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "[SKIP] {$name}: tidak ditemukan\n";
        continue;
    }

    $itemId = (int) $item->id;
    $ipRows = DB::table('item_prices')->where('item_id', $itemId)->get();
    $allRow = $ipRows->firstWhere('availability_price_type', 'all');
    $mode = $allRow->pricing_mode ?? 'manual';
    $stored = $allRow ? (float) $allRow->price : 0;
    $targetLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $allRow);

    if ($targetLarge <= 0) {
        echo "[SKIP] {$name}: tidak ada harga acuan\n";
        continue;
    }

  $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?: 'Pack';
    $sysBefore = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit, null, null, $item);

    $actions = [];

    if ($mode === 'auto') {
        $grLarge = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        if ($grLarge && abs($stored - $grLarge) > 0.5) {
            $actions[] = sprintf(
                'item_prices all: %s -> %s (GR+12%%)',
                number_format($stored, 2, '.', ','),
                number_format($grLarge, 2, '.', ','),
            );
            if ($apply) {
                foreach ($ipRows as $row) {
                    if ((float) ($row->price ?? 0) <= 0) {
                        continue;
                    }
                    DB::table('item_prices')->where('id', $row->id)->update([
                        'price' => round($grLarge, 2),
                        'updated_at' => now(),
                    ]);
                }
                $updatedIp++;
            }
        }
    }

    $sysAfter = $apply && $actions !== []
        ? FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit, null, null, $item)
        : $sysBefore;

    if ($actions !== []) {
        echo "{$name} (id {$itemId}, mode {$mode})\n";
        foreach ($actions as $a) {
            echo "  {$a}\n";
        }
        echo "  sys {$mediumUnit}: " . number_format($sysBefore, 0, ',', '.')
            . ($apply ? ' -> ' . number_format($sysAfter, 0, ',', '.') : '') . PHP_EOL . PHP_EOL;
        $fixedIds[] = $itemId;
    }
}

$allIds = DB::table('items')->whereIn('name', $itemNames)->pluck('id')->map(fn ($id) => (int) $id)->all();

if ($apply) {
    echo "Updated item_prices: {$updatedIp} item\n\n";

    $csv = __DIR__ . '/_tmp_batch18_fo_sync.csv';
    $fp = fopen($csv, 'w');
    fputcsv($fp, ['item_id']);
    foreach ($allIds as $id) {
        fputcsv($fp, [$id]);
    }
    fclose($fp);

    echo "=== Sync FO {$foFrom} s/d {$foTo} ===" . PHP_EOL;
    passthru(sprintf(
        'php %s floor-order:sync-prices --from=%s --to=%s --all-statuses --items-csv=%s --apply --force',
        escapeshellarg(base_path('artisan')),
        $foFrom,
        $foTo,
        escapeshellarg($csv),
    ));
    @unlink($csv);
} else {
    echo 'Dry-run. Tambahkan --apply untuk update item_prices + sync FO Juni 2026.' . PHP_EOL;
}
