<?php
/**
 * Hitung ulang subtotal_mac tersimpan di outlet_internal_use_waste_headers
 * setelah perbaikan MAC saldo awal (initial_balance).
 *
 * Usage:
 *   php scripts/recalculate_category_cost_subtotal_mac.php
 *   php scripts/recalculate_category_cost_subtotal_mac.php --apply
 *   php scripts/recalculate_category_cost_subtotal_mac.php --apply --from=2026-06-01 --to=2026-06-30
 *   php scripts/recalculate_category_cost_subtotal_mac.php --apply --only-zero
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\CategoryCostMacResolver;
use Illuminate\Support\Facades\DB;

$opts = getopt('', ['apply', 'from::', 'to::', 'outlet::', 'only-zero', 'chunk::']);
$apply = array_key_exists('apply', $opts);
$from = $opts['from'] ?? null;
$to = $opts['to'] ?? null;
$outletFilter = isset($opts['outlet']) ? (int) $opts['outlet'] : null;
$onlyZero = array_key_exists('only-zero', $opts);
$chunkSize = isset($opts['chunk']) ? max(50, (int) $opts['chunk']) : 200;

echo "=== Recalculate category cost subtotal_mac ===\n";
echo 'Mode      : ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo 'From      : ' . ($from ?? 'semua') . "\n";
echo 'To        : ' . ($to ?? 'semua') . "\n";
echo 'Outlet    : ' . ($outletFilter !== null ? (string) $outletFilter : 'semua') . "\n";
echo 'Only zero : ' . ($onlyZero ? 'ya' : 'tidak') . "\n\n";

$query = DB::table('outlet_internal_use_waste_headers as h')
    ->whereIn('h.status', ['APPROVED', 'PROCESSED'])
    ->orderBy('h.id');

if ($from) {
    $query->whereDate('h.date', '>=', $from);
}
if ($to) {
    $query->whereDate('h.date', '<=', $to);
}
if ($outletFilter !== null) {
    $query->where('h.outlet_id', $outletFilter);
}
if ($onlyZero) {
    $query->where(function ($q) {
        $q->whereNull('h.subtotal_mac')->orWhere('h.subtotal_mac', '<=', 0);
    });
}

$totalHeaders = (clone $query)->count();
echo "Header kandidat: {$totalHeaders}\n\n";

$updated = 0;
$unchanged = 0;
$sampleChanges = [];
$macCache = [];
$processed = 0;

$query->select('h.id', 'h.number', 'h.date', 'h.outlet_id', 'h.warehouse_outlet_id', 'h.subtotal_mac')
    ->chunkById($chunkSize, function ($headers) use ($apply, &$updated, &$unchanged, &$sampleChanges, &$macCache, &$processed, $totalHeaders) {
        $headerIds = $headers->pluck('id')->all();
        $details = DB::table('outlet_internal_use_waste_details as d')
            ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
            ->select(
                'd.header_id',
                'd.item_id',
                'd.qty',
                'd.unit_id',
                'i.type as item_type',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty'
            )
            ->whereIn('d.header_id', $headerIds)
            ->get()
            ->groupBy('header_id');

        $itemIds = $details->flatten(1)->pluck('item_id')->unique()->filter()->all();
        $inventoryItems = [];
        if ($itemIds !== []) {
            $inventoryItems = DB::table('outlet_food_inventory_items')
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id')
                ->all();
        }

        foreach ($headers as $header) {
            $headerDetails = $details->get($header->id, collect());
            $subtotalMac = 0.0;

            foreach ($headerDetails as $detail) {
                $inventoryItem = $inventoryItems[$detail->item_id] ?? null;
                if (!$inventoryItem) {
                    continue;
                }

                $macKey = "{$inventoryItem->id}_{$header->outlet_id}_{$header->warehouse_outlet_id}_{$header->date}";
                if (!array_key_exists($macKey, $macCache)) {
                    $macCache[$macKey] = CategoryCostMacResolver::resolveHistoryMacAtDate(
                        (int) $inventoryItem->id,
                        (int) $header->outlet_id,
                        (int) $header->warehouse_outlet_id,
                        (string) $header->date
                    );
                }
                $historyMac = $macCache[$macKey];
                if ($historyMac === null) {
                    continue;
                }

                $itemMaster = (object) [
                    'id' => $detail->item_id,
                    'type' => $detail->item_type ?? null,
                    'small_unit_id' => $detail->small_unit_id,
                    'medium_unit_id' => $detail->medium_unit_id,
                    'large_unit_id' => $detail->large_unit_id,
                    'small_conversion_qty' => $detail->small_conversion_qty,
                    'medium_conversion_qty' => $detail->medium_conversion_qty,
                ];

                $subtotalMac += CategoryCostMacResolver::subtotalFromDetail(
                    $itemMaster,
                    $historyMac,
                    (int) $detail->unit_id,
                    (float) ($detail->qty ?? 0),
                    (int) $header->outlet_id,
                    (int) $header->warehouse_outlet_id,
                    (string) $header->date
                );
            }

            $subtotalMac = round($subtotalMac, 2);
            $old = round((float) ($header->subtotal_mac ?? 0), 2);

            if (abs($subtotalMac - $old) < 0.01) {
                $unchanged++;
                continue;
            }

            if (count($sampleChanges) < 15) {
                $sampleChanges[] = sprintf(
                    '  #%s %s | %s -> %s',
                    $header->number ?? $header->id,
                    $header->date,
                    number_format($old, 2, '.', ','),
                    number_format($subtotalMac, 2, '.', ',')
                );
            }

            if ($apply) {
                if ($subtotalMac > 99_999_999.99) {
                    echo "  SKIP #{$header->number} subtotal terlalu besar: " . number_format($subtotalMac, 2) . "\n";
                    continue;
                }
                DB::table('outlet_internal_use_waste_headers')
                    ->where('id', $header->id)
                    ->update([
                        'subtotal_mac' => $subtotalMac,
                        'updated_at' => now(),
                    ]);
            }

            $updated++;
        }

        $processed += $headers->count();
        if ($processed % 1000 === 0 || $processed >= $totalHeaders) {
            echo "Progress: {$processed}/{$totalHeaders} header...\n";
        }
    }, 'h.id', 'id');

echo "--- Sample perubahan ---\n";
foreach ($sampleChanges as $line) {
    echo $line . "\n";
}

echo "\nDiupdate : {$updated}\n";
echo "Tetap   : {$unchanged}\n";

if (!$apply && $updated > 0) {
    echo "\nJalankan dengan --apply untuk menyimpan.\n";
}
