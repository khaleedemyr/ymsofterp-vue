<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

/** Sudah diperbaiki di sesi sebelumnya — jangan kalikan lagi */
$alreadyFixed = [
    54667, 54706, 54707, 54668, 54669, 54676, 54670, 54729, 54671, 54672,
    54697, 54698, 54673, 54675, 54731, 54677, 54700, 54694, 54713, 54709,
];

/** Dari audit spreadsheet + scan Main Store / Kitchen */
$targetNames = [
    'Thousand Island',
    'Cream Cheese Neufchatel',
    'Beef Chuck Short Ribs BBQ',
    'Beef Crispy Belly',
    'Beef Sei',
    'Chicken Sei',
    'Chicken Stock',
    'Duck Confit',
    'Paste Rawon',
    'Trimming Sei',
    'Beef Patty',
    'Dressing Salad',
    'Meat Ball Patty',
    'Seasoning Vegetable',
    'Tongue Sei',
    'Teriyaki Sauce',
    'Kuah Rawon',
];

$apply = in_array('--apply', $argv ?? [], true);
$foFrom = '2026-06-01';
$foTo = '2026-06-30';
$fixedIds = [];

echo '=== Fix item_prices (belum pernah di-fix) ===' . PHP_EOL;
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

foreach ($targetNames as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "[SKIP] {$name}: tidak ditemukan\n";
        continue;
    }

    $itemId = (int) $item->id;
    if (in_array($itemId, $alreadyFixed, true)) {
        echo "[SKIP] {$name}: sudah di-fix sebelumnya\n";
        continue;
    }

    $conv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
    if ($conv <= 1) {
        echo "[SKIP] {$name}: conv=1\n";
        continue;
    }

    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?: 'Pack';
    $priceRows = DB::table('item_prices')->where('item_id', $itemId)->get();
    $priced = $priceRows->filter(fn ($r) => (float) $r->price > 0);
    if ($priced->isEmpty()) {
        echo "[SKIP] {$name}: tidak ada item_prices\n";
        continue;
    }

    $priceLarge = (float) $priced->first()->price;
    $sysPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
    $dividedPack = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge / $conv);
    $intendedPack = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);
    $newLarge = round($priceLarge * $conv, 2);

    if (abs($sysPack - $dividedPack) > 1) {
        echo "[SKIP] {$name}: sys Pack tidak mengikuti bagi conv\n";
        continue;
    }
    if ($intendedPack >= 120000) {
        echo "[SKIP] {$name}: harga master {$intendedPack} sudah skala Recipe/large\n";
        continue;
    }
    if (abs($sysPack - $intendedPack) < 1) {
        echo "[SKIP] {$name}: Pack sudah {$sysPack}\n";
        continue;
    }

    echo "{$name} (id {$itemId}, conv {$conv})\n";
    echo "  sys Pack: {$sysPack} -> intended: {$intendedPack}\n";
    echo "  large: " . number_format($priceLarge, 0, ',', '.') . ' -> ' . number_format($newLarge, 0, ',', '.') . PHP_EOL;

    if ($apply) {
        foreach ($priceRows as $row) {
            $old = (float) $row->price;
            if ($old <= 0) {
                continue;
            }
            DB::table('item_prices')->where('id', $row->id)->update([
                'price' => round($old * $conv, 2),
                'updated_at' => now(),
            ]);
        }
        upsertAllPrice($itemId, $newLarge);
        $after = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
        echo "  Pack setelah: {$after}\n";
        $fixedIds[] = $itemId;
    }
    echo PHP_EOL;
}

if ($apply && $fixedIds !== []) {
    $csv = __DIR__ . '/_tmp_fo_fix_batch.csv';
    $fp = fopen($csv, 'w');
    fputcsv($fp, ['item_id']);
    foreach ($fixedIds as $id) {
        fputcsv($fp, [$id]);
    }
    fclose($fp);

    echo '=== Sync FO Juni 2026 (' . count($fixedIds) . ' item) ===' . PHP_EOL;
    passthru(sprintf(
        'php %s floor-order:sync-prices --from=%s --to=%s --all-statuses --items-csv=%s --apply --force',
        escapeshellarg(base_path('artisan')),
        $foFrom,
        $foTo,
        escapeshellarg($csv),
    ));
    @unlink($csv);
}

if (! $apply) {
    echo 'Dry-run. Tambahkan --apply untuk eksekusi.' . PHP_EOL;
}

function upsertAllPrice(int $itemId, float $priceLarge): void
{
    $existing = DB::table('item_prices')
        ->where('item_id', $itemId)
        ->where('availability_price_type', 'all')
        ->first();

    if ($existing) {
        DB::table('item_prices')->where('id', $existing->id)->update([
            'price' => $priceLarge,
            'pricing_mode' => 'manual',
            'updated_at' => now(),
        ]);

        return;
    }

    DB::table('item_prices')->insert([
        'item_id' => $itemId,
        'availability_price_type' => 'all',
        'price' => $priceLarge,
        'pricing_mode' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
