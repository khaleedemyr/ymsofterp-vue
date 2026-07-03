<?php

declare(strict_types=1);

/**
 * Set harga jual target Vetcin Powder & Kacang Tanah, lalu selaraskan FO + GR Serial Juni 2026.
 *
 * Usage:
 *   php scripts/fix_vetcin_kacang_target_prices.php
 *   php scripts/fix_vetcin_kacang_target_prices.php --apply
 *   php scripts/fix_vetcin_kacang_target_prices.php --apply --from=2026-06-01 --to=2026-06-30
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);
$from = '2026-06-01';
$to = '2026-06-30';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

$targets = [
    'Vetcin Powder' => 50900.0,
    'Kacang Tanah' => 43900.0,
];

echo "=== Fix harga target Vetcin & Kacang Tanah ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "GR/FO periode: {$from} .. {$to}\n\n";

$itemMap = [];
foreach ($targets as $name => $priceLarge) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "{$name}: NOT FOUND\n";
        continue;
    }
    $itemId = (int) $item->id;
    $itemMap[$itemId] = ['name' => $name, 'price_large' => $priceLarge, 'item' => $item];

    $ip = DB::table('item_prices')
        ->where('item_id', $itemId)
        ->where('availability_price_type', 'all')
        ->orderByDesc('id')
        ->first();

    echo "{$name} (id={$itemId}):\n";
    echo "  item_prices sekarang: " . ($ip->price ?? '-') . ' mode=' . ($ip->pricing_mode ?? '-') . "\n";
    echo "  target large/medium: " . number_format($priceLarge, 0) . "\n";

    $targetCostSmall = round($priceLarge / max((float) $item->small_conversion_qty, 1), 4);
    echo "  target cost_small (serial): {$targetCostSmall}\n";

    if ($apply && $ip) {
        $update = ['price' => $priceLarge, 'updated_at' => now()];
        if (Schema::hasColumn('item_prices', 'pricing_mode')) {
            $update['pricing_mode'] = 'manual';
        }
        DB::table('item_prices')->where('id', $ip->id)->update($update);
        echo "  item_prices updated id={$ip->id}\n";
    }
    echo "\n";
}

$itemIds = array_keys($itemMap);
if ($itemIds === []) {
    exit(1);
}
$idList = implode(',', $itemIds);

// --- Serial GR ---
if (Schema::hasTable('outlet_serial_receive_headers')) {
    foreach ($itemMap as $itemId => $meta) {
        $priceLarge = $meta['price_large'];
        $item = $meta['item'];

        $toFix = DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'it.id', '=', 'si.item_id')
            ->where('si.item_id', $itemId)
            ->whereBetween('h.receive_date', [$from, $to])
            ->whereNull('h.deleted_at')
            ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [
                round($priceLarge / max((float) $item->small_conversion_qty, 1), 4),
            ])
            ->count();

        echo "--- GR Serial {$meta['name']}: to_fix={$toFix} ---\n";

        if ($apply && $toFix > 0) {
            $updated = DB::update("
                UPDATE outlet_serial_receive_items si
                INNER JOIN outlet_serial_receive_headers h ON h.id = si.header_id
                INNER JOIN items it ON it.id = si.item_id
                SET si.cost_small = (
                    CASE
                        WHEN si.unit_id = it.large_unit_id THEN ROUND(? / (GREATEST(COALESCE(it.small_conversion_qty, 1), 1) * GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)), 4)
                        WHEN si.unit_id = it.medium_unit_id THEN ROUND((? / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
                        WHEN si.unit_id = it.small_unit_id THEN ROUND(? / (GREATEST(COALESCE(it.medium_conversion_qty, 1), 1) * GREATEST(COALESCE(it.small_conversion_qty, 1), 1)), 2)
                        ELSE ROUND((? / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
                    END
                ),
                si.updated_at = NOW()
                WHERE si.item_id = ?
                  AND h.receive_date BETWEEN ? AND ?
                  AND h.deleted_at IS NULL
            ", [$priceLarge, $priceLarge, $priceLarge, $priceLarge, $itemId, $from, $to]);
            echo "  updated={$updated}\n";
        }
    }
    echo "\n";
}

// --- FO lines (semua yang terhubung GR periode + tanggal FO dalam periode) ---
$auditor = new FloorOrderPriceAuditor();
$itemMasters = DB::table('items')->whereIn('id', $itemIds)->get()->keyBy('id');
$unitNameById = DB::table('units')->pluck('name', 'id')->all();

$foRows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->whereIn('ffoi.item_id', $itemIds)
    ->where(function ($q) use ($from, $to) {
        $q->whereBetween('ffo.tanggal', [$from, $to])
            ->orWhereExists(function ($sub) use ($from, $to) {
                $sub->select(DB::raw(1))
                    ->from('outlet_food_good_receives as gr')
                    ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
                    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->whereColumn('gri.item_id', 'ffoi.item_id')
                    ->whereColumn('do.floor_order_id', 'ffoi.floor_order_id')
                    ->whereBetween('gr.receive_date', [$from, $to])
                    ->whereNull('gr.deleted_at');
            });
    })
    ->select('ffoi.id', 'ffoi.floor_order_id', 'ffoi.item_id', 'ffoi.item_name', 'ffoi.qty', 'ffoi.price', 'ffoi.unit', 'ffo.tanggal', 'ffo.fo_mode')
    ->get();

$fixes = [];
foreach ($foRows as $row) {
    $itemId = (int) $row->item_id;
    if (! isset($itemMap[$itemId])) {
        continue;
    }
    $expected = $itemMap[$itemId]['price_large'];
    $item = $itemMasters->get($itemId);
    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, (string) $row->unit, $unitNameById);
    if ($tier === 'small') {
        $expected = FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToSmallPrice($expected, $item)
        );
    }

    if (abs($expected - (float) $row->price) < 0.01) {
        continue;
    }

    $fixes[] = [
        'line_id' => (int) $row->id,
        'floor_order_id' => (int) $row->floor_order_id,
        'item_name' => (string) $row->item_name,
        'tanggal' => (string) $row->tanggal,
        'fo_mode' => (string) $row->fo_mode,
        'current_price' => (float) $row->price,
        'expected_price' => $expected,
        'expected_subtotal' => round($expected * (float) $row->qty, 2),
    ];
}

echo "--- FO lines to fix: " . count($fixes) . " ---\n";
foreach ($fixes as $f) {
    echo "  {$f['item_name']} line={$f['line_id']} {$f['tanggal']} {$f['fo_mode']} {$f['current_price']} -> {$f['expected_price']}\n";
}

if ($apply && $fixes !== []) {
    $stats = $auditor->applyFixes(array_map(static fn (array $f) => [
        'line_id' => $f['line_id'],
        'floor_order_id' => $f['floor_order_id'],
        'expected_price' => $f['expected_price'],
        'expected_subtotal' => $f['expected_subtotal'],
    ], $fixes));
    echo "  FO updated={$stats['updated']}\n";
}

echo "\nDone.\n";
if (! $apply) {
    echo "Jalankan dengan --apply untuk perbaiki data.\n";
}
