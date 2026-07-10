<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

/** Item default; bisa override lewat argumen CLI (nama item dipisah spasi, setelah --apply) */
$itemNames = [
    'Beef Chuck Short Ribs Dice',
    'Beef Oxtail',
    'Blackpepper Sauce',
    'Bolognaise Sauce',
    'Cheese Sauce',
    'Coating Bakar',
    'Coating Goreng',
    'Curry Sauce',
    'Duck Crispy',
    'Japanese Teriyaki Sauce',
    'Korean BBQ Sauce',
    'Kuah Buntut',
    'Kuah Garang Asam',
    'Mushroom Sauce',
    'Sauce Blueberry',
    'Smoked Chicken',
];

$cliNames = array_values(array_filter(
    array_slice($argv ?? [], 1),
    static fn (string $a): bool => ! str_starts_with($a, '--'),
));
if ($cliNames !== []) {
    $itemNames = $cliNames;
}

$apply = in_array('--apply', $argv ?? [], true);
$foFrom = '2026-06-01';
$foTo = '2026-06-30';

echo '=== Fix item_prices (Pack price × medium_conv → large) ===' . PHP_EOL;
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;

$fixedItemIds = [];

foreach ($itemNames as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "[SKIP] {$name}: tidak ditemukan\n";
        continue;
    }

    $itemId = (int) $item->id;
    $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?: 'Pack';

    $priceRows = DB::table('item_prices')->where('item_id', $itemId)->get();
    $pricedRows = $priceRows->filter(fn ($r) => (float) ($r->price ?? 0) > 0);

    $action = null;
    $packTarget = null;

    if ($mediumConv <= 1) {
        $sysPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
        if ($pricedRows->isEmpty() || $sysPack <= 0) {
            $packTarget = dominantFoPackPrice($itemId, $foFrom, $foTo);
            if ($packTarget <= 0) {
                echo "[SKIP] {$name}: conv=1, tidak ada harga master maupun FO\n";
                continue;
            }
            $action = 'insert_all';
        } else {
            echo "[SKIP] {$name}: conv=1, harga sudah ada (Pack ~{$sysPack})\n";
            continue;
        }
    } elseif ($pricedRows->isNotEmpty()) {
        $priceLarge = (float) $pricedRows->first()->price;
        $sysPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
        $packFromLarge = FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToMediumPrice($priceLarge, $item)
        );
        if (abs($sysPack - $packFromLarge) < 0.01) {
            echo "[SKIP] {$name}: Pack sudah ~" . number_format($packFromLarge, 0, ',', '.') . " (large sudah benar)\n";
            continue;
        }
        $legacyPackInLargeField = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);
        if (abs($sysPack - $legacyPackInLargeField) < 0.01) {
            $packTarget = $legacyPackInLargeField;
            $action = 'multiply';
        } elseif ($sysPack > 0 && abs($sysPack - $packFromLarge) > 0.01) {
            echo "[SKIP] {$name}: sys Pack {$sysPack} tidak cocok pola legacy maupun large/conv\n";
            continue;
        } else {
            $packTarget = $legacyPackInLargeField;
            $action = 'multiply';
        }
    } else {
        $packTarget = legacyUndividedPackPrice($itemId, $foFrom, $foTo, $mediumConv);
        if ($packTarget <= 0) {
            $packTarget = dominantFoPackPrice($itemId, $foFrom, $foTo);
        }
        if ($packTarget <= 0) {
            echo "[SKIP] {$name}: item_prices kosong & tidak ada acuan FO\n";
            continue;
        }
        $action = 'insert_all';
    }

    $newLarge = round($packTarget * $mediumConv, 2);
    $newPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);

    echo "{$name} (id {$itemId}, conv {$mediumConv})\n";
    echo "  Pack target: " . number_format($packTarget, 0, ',', '.') . "\n";
    echo "  large baru:  " . number_format($newLarge, 0, ',', '.') . "\n";

    if ($action === 'multiply') {
        foreach ($priceRows as $row) {
            $old = (float) $row->price;
            if ($old <= 0) {
                continue;
            }
            $new = round($old * $mediumConv, 2);
            echo "  id={$row->id} {$row->availability_price_type}: "
                . number_format($old, 0, ',', '.') . ' -> ' . number_format($new, 0, ',', '.') . PHP_EOL;
            if ($apply) {
                DB::table('item_prices')->where('id', $row->id)->update([
                    'price' => $new,
                    'updated_at' => now(),
                ]);
            }
        }
        if ($apply) {
            upsertAllPrice($itemId, $newLarge);
            echo "  + baris all di-set ke " . number_format($newLarge, 0, ',', '.') . PHP_EOL;
        }
    } elseif ($action === 'insert_all') {
        echo "  (insert/update baris all, harga large {$newLarge})\n";
        if ($apply) {
            upsertAllPrice($itemId, $newLarge);
        }
    }

    if ($apply) {
        $afterPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
        echo "  Pack setelah fix: " . number_format($afterPack, 0, ',', '.') . PHP_EOL;
        $fixedItemIds[] = $itemId;
    }

    echo PHP_EOL;
}

if ($apply && $fixedItemIds !== []) {
    $csv = __DIR__ . '/_tmp_fo_price_fix_items.csv';
    $fp = fopen($csv, 'w');
    fputcsv($fp, ['item_id']);
    foreach ($fixedItemIds as $id) {
        fputcsv($fp, [$id]);
    }
    fclose($fp);

    echo '=== Sync FO Juni 2026 ===' . PHP_EOL;
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
    echo PHP_EOL . 'Dry-run selesai. Jalankan dengan --apply untuk update item_prices + FO.' . PHP_EOL;
}

function dominantFoPackPrice(int $itemId, string $from, string $to): float
{
    $row = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereDate('ffo.tanggal', '>=', $from)
        ->whereDate('ffo.tanggal', '<=', $to)
        ->selectRaw('ffoi.price, count(*) as cnt')
        ->groupBy('ffoi.price')
        ->orderByDesc('cnt')
        ->first();

    return $row ? (float) $row->price : 0.0;
}

function legacyUndividedPackPrice(int $itemId, string $from, string $to, float $mediumConv): float
{
    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereDate('ffo.tanggal', '>=', $from)
        ->whereDate('ffo.tanggal', '<=', $to)
        ->selectRaw('ffoi.price, count(*) as cnt')
        ->groupBy('ffoi.price')
        ->orderByDesc('ffoi.price')
        ->get();

    if ($rows->isEmpty()) {
        return 0.0;
    }

    $dominant = (float) $rows->sortByDesc('cnt')->first()->price;
    $max = (float) $rows->first()->price;

    if ($max > $dominant * 1.5) {
        return $max;
    }

    return FloorOrderItemPriceResolver::roundUpToHundred($dominant * $mediumConv);
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
        'region_id' => null,
        'outlet_id' => null,
        'price' => $priceLarge,
        'pricing_mode' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
