<?php

declare(strict_types=1);

/**
 * Sinkronkan harga Onion (53111) di food_floor_order_items dengan item_prices terkini.
 *
 * Usage:
 *   php scripts/fix_onion_fo_prices.php
 *   php scripts/fix_onion_fo_prices.php --from=2026-06 --to=2026-06
 *   php scripts/fix_onion_fo_prices.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

const ONION_ITEM_ID = 53111;

$opts = getopt('', ['from::', 'to::', 'apply']);
$fromYm = $opts['from'] ?? '2026-06';
$toYm = $opts['to'] ?? Carbon::now()->format('Y-m');
$apply = array_key_exists('apply', $opts);

$item = DB::table('items')->where('id', ONION_ITEM_ID)->first();
if (! $item) {
    echo "Onion item tidak ditemukan.\n";
    exit(1);
}

$ip = DB::table('item_prices')->where('item_id', ONION_ITEM_ID)->where('availability_price_type', 'all')->first();
$expected = FloorOrderItemPriceResolver::resolveLineUnitPrice(ONION_ITEM_ID, 'Kilogram');

echo "=== Fix Onion FO prices ===\n";
echo "item_prices (all): " . ($ip ? number_format((float) $ip->price, 0, ',', '.') : '-') . " ({$ip->pricing_mode})\n";
echo "Expected FO Kilogram: " . number_format($expected, 0, ',', '.') . "\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$start = Carbon::createFromFormat('Y-m', $fromYm)->startOfMonth();
$end = Carbon::createFromFormat('Y-m', $toYm)->startOfMonth();
$auditor = new FloorOrderPriceAuditor();
$totalMismatches = 0;
$totalUpdated = 0;

while ($start->lte($end)) {
    $from = $start->toDateString();
    $to = $start->copy()->endOfMonth()->toDateString();
    $label = $start->format('Y-m');

    $result = $auditor->scan($from, $to, true, [ONION_ITEM_ID]);
    $mismatches = $result['mismatches'];
    $count = count($mismatches);
    $totalMismatches += $count;

    echo "--- {$label}: scanned={$result['rows_scanned']} selisih={$count} ---\n";
    foreach ($mismatches as $m) {
        echo sprintf(
            "  %s %s %s: %s -> %s (qty %s)\n",
            $m['tanggal'],
            $m['order_number'],
            $m['outlet'],
            number_format($m['current_price'], 0, ',', '.'),
            number_format($m['expected_price'], 0, ',', '.'),
            $m['qty'],
        );
    }

    if ($count > 0 && $apply) {
        DB::beginTransaction();
        try {
            $stats = $auditor->applyFixes($mismatches);
            DB::commit();
            $totalUpdated += $stats['updated'];
            echo "  updated={$stats['updated']} orders_recalculated={$stats['orders_recalculated']}\n";
        } catch (Throwable $e) {
            DB::rollBack();
            echo "ERROR: {$e->getMessage()}\n";
            exit(1);
        }
    }

    $start->addMonth();
}

echo "\nTotal selisih: {$totalMismatches}\n";
echo 'Total baris diupdate: ' . ($apply ? $totalUpdated : 0) . "\n";

if (! $apply && $totalMismatches > 0) {
    echo "\nJalankan dengan --apply untuk sinkronkan FO.\n";
}
