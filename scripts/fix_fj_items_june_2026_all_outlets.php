<?php

declare(strict_types=1);

/**
 * Perbaiki data Juni 2026 untuk ~99 item audit Rekap FJ — semua outlet.
 *
 * Usage:
 *   php scripts/fix_fj_items_june_2026_all_outlets.php
 *   php scripts/fix_fj_items_june_2026_all_outlets.php --apply
 *   php scripts/fix_fj_items_june_2026_all_outlets.php --apply --serial-only
 *   php scripts/fix_fj_items_june_2026_all_outlets.php --apply --fo-only
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderPriceAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);
$serialOnly = in_array('--serial-only', $argv, true);
$foOnly = in_array('--fo-only', $argv, true);
$from = '2026-06-01';
$to = '2026-06-30';
$itemNames = require __DIR__ . '/fj_audit_item_names.php';

function resolveItemIds(array $names): array
{
    $ids = [];
    $missing = [];

    foreach ($names as $name) {
        $item = DB::table('items')->where('name', $name)->first(['id', 'name']);
        if (! $item) {
            $item = DB::table('items')->where('name', 'like', '%' . $name . '%')->first(['id', 'name']);
        }
        if ($item) {
            $ids[(int) $item->id] = $item->name;
        } else {
            $missing[] = $name;
        }
    }

    return [$ids, $missing];
}

[$itemMap, $missingNames] = resolveItemIds($itemNames);
$itemIds = array_keys($itemMap);
$idList = implode(',', $itemIds);

echo "=== Fix FJ items Juni 2026 (semua outlet) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Periode: {$from} .. {$to}\n";
echo 'Items resolved: ' . count($itemIds) . "\n";
if ($missingNames !== []) {
    echo 'Items tidak ditemukan: ' . implode(', ', $missingNames) . "\n";
}
echo "\n";

$runSerial = ! $foOnly;
$runFo = ! $serialOnly;

// --- 1) GR Serial (bulk SQL) ---
if ($runSerial && Schema::hasTable('outlet_serial_receive_headers')) {
    $countSql = "
        SELECT COUNT(*) as cnt
        FROM outlet_serial_receive_items si
        INNER JOIN outlet_serial_receive_headers h ON h.id = si.header_id
        INNER JOIN items it ON it.id = si.item_id
        INNER JOIN (
            SELECT ip1.item_id, ip1.price
            FROM item_prices ip1
            INNER JOIN (
                SELECT item_id, MAX(id) AS max_id
                FROM item_prices
                WHERE availability_price_type = 'all' AND item_id IN ({$idList})
                GROUP BY item_id
            ) latest ON latest.max_id = ip1.id
        ) ip ON ip.item_id = si.item_id
        WHERE si.item_id IN ({$idList})
          AND h.receive_date BETWEEN ? AND ?
          AND h.deleted_at IS NULL
          AND ip.price > 0
    ";

    $toFixSql = $countSql . "
          AND ABS(
            COALESCE(si.cost_small, 0) - (
              CASE
                WHEN si.unit_id = it.large_unit_id THEN ROUND(ip.price / (GREATEST(COALESCE(it.small_conversion_qty, 1), 1) * GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)), 4)
                WHEN si.unit_id = it.medium_unit_id THEN ROUND((ip.price / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
                WHEN si.unit_id = it.small_unit_id THEN ROUND(ip.price / (GREATEST(COALESCE(it.medium_conversion_qty, 1), 1) * GREATEST(COALESCE(it.small_conversion_qty, 1), 1)), 2)
                ELSE ROUND((ip.price / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
              END
            )
          ) > 0.0001
    ";

    $scanned = (int) DB::selectOne(str_replace('COUNT(*) as cnt', 'COUNT(*) as cnt', $countSql), [$from, $to])->cnt;
    $toFix = (int) DB::selectOne(str_replace('COUNT(*) as cnt', 'COUNT(*) as cnt', $toFixSql), [$from, $to])->cnt;

    echo "--- GR Serial (bulk) scanned={$scanned} to_fix={$toFix} ---\n";

    if ($apply && $toFix > 0) {
        $updateSql = "
            UPDATE outlet_serial_receive_items si
            INNER JOIN outlet_serial_receive_headers h ON h.id = si.header_id
            INNER JOIN items it ON it.id = si.item_id
            INNER JOIN (
                SELECT ip1.item_id, ip1.price
                FROM item_prices ip1
                INNER JOIN (
                    SELECT item_id, MAX(id) AS max_id
                    FROM item_prices
                    WHERE availability_price_type = 'all' AND item_id IN ({$idList})
                    GROUP BY item_id
                ) latest ON latest.max_id = ip1.id
            ) ip ON ip.item_id = si.item_id
            SET si.cost_small = (
                CASE
                    WHEN si.unit_id = it.large_unit_id THEN ROUND(ip.price / (GREATEST(COALESCE(it.small_conversion_qty, 1), 1) * GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)), 4)
                    WHEN si.unit_id = it.medium_unit_id THEN ROUND((ip.price / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
                    WHEN si.unit_id = it.small_unit_id THEN ROUND(ip.price / (GREATEST(COALESCE(it.medium_conversion_qty, 1), 1) * GREATEST(COALESCE(it.small_conversion_qty, 1), 1)), 2)
                    ELSE ROUND((ip.price / GREATEST(COALESCE(it.medium_conversion_qty, 1), 1)) / GREATEST(COALESCE(it.small_conversion_qty, 1), 1), 4)
                END
            ),
            si.updated_at = NOW()
            WHERE si.item_id IN ({$idList})
              AND h.receive_date BETWEEN ? AND ?
              AND h.deleted_at IS NULL
              AND ip.price > 0
        ";

        $updated = DB::update($updateSql, [$from, $to]);
        echo "  updated={$updated}\n";
    }
    echo "\n";
}

// --- 2) FO via auditor (RO Utama + draft/submitted) ---
if ($runFo) {
    $auditor = new FloorOrderPriceAuditor();
    $result = $auditor->scan($from, $to, true, $itemIds);
    $mismatches = $result['mismatches'];

    echo "--- FO (auditor, tanpa RO Khusus/Supplier) ---\n";
    echo "  scanned={$result['rows_scanned']} selisih=" . count($mismatches) . "\n";

    if ($apply && $mismatches !== []) {
        DB::beginTransaction();
        try {
            $stats = $auditor->applyFixes($mismatches);
            DB::commit();
            echo "  updated={$stats['updated']} orders_recalculated={$stats['orders_recalculated']}\n";
        } catch (Throwable $e) {
            DB::rollBack();
            echo '  ERROR: ' . $e->getMessage() . "\n";
            exit(1);
        }
    }

    // RO Khusus / RO Supplier — auditor tidak menyentuh, perbaiki terpisah
    $khususRows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->whereIn('ffoi.item_id', $itemIds)
        ->whereBetween('ffo.tanggal', [$from, $to])
        ->whereIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
        ->select(
            'ffoi.id',
            'ffoi.floor_order_id',
            'ffoi.item_id',
            'ffoi.qty',
            'ffoi.price',
            'ffoi.unit',
            'ffo.id_outlet',
            'o.region_id',
        )
        ->get();

    echo "--- FO RO Khusus/Supplier ---\n";
    echo '  scanned=' . $khususRows->count() . "\n";

    $itemMasters = DB::table('items')->whereIn('id', $itemIds)->get()->keyBy('id');
    $unitNameById = DB::table('units')->pluck('name', 'id')->all();
    $priceRowsByItem = DB::table('item_prices')->whereIn('item_id', $itemIds)->orderByDesc('id')->get()->groupBy('item_id');

    $khususFixes = [];
    foreach ($khususRows as $row) {
        $itemId = (int) $row->item_id;
        $item = $itemMasters->get($itemId);
        if (! $item) {
            continue;
        }

        $rows = $priceRowsByItem->get($itemId, collect());
        $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
        $regionId = $row->region_id ? (int) $row->region_id : null;
        $pick = static function (string $type, ?int $region = null, ?string $outlet = null) use ($rows): ?object {
            return $rows->first(function ($r) use ($type, $region, $outlet) {
                if (($r->availability_price_type ?? '') !== $type) {
                    return false;
                }
                if ($type === 'region' && (int) ($r->region_id ?? 0) !== (int) $region) {
                    return false;
                }
                if ($type === 'outlet' && (string) ($r->outlet_id ?? '') !== (string) $outlet) {
                    return false;
                }
                if ((float) ($r->price ?? 0) <= 0) {
                    return false;
                }

                return true;
            });
        };
        $priceRow = $pick('outlet', null, $outletId) ?? $pick('region', $regionId, null) ?? $pick('all') ?? $rows->first();
        $priceLarge = \App\Support\FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
        if ($priceLarge <= 0) {
            continue;
        }

        $tier = \App\Support\FloorOrderItemPriceResolver::detectUnitTier($item, (string) $row->unit, $unitNameById);
        $expected = match ($tier) {
            'large' => \App\Support\FloorOrderItemPriceResolver::roundUpToHundred($priceLarge),
            'small' => \App\Support\FloorOrderItemPriceResolver::roundUpToHundred(
                \App\Support\FloorOrderItemPriceResolver::largeToSmallPrice($priceLarge, $item)
            ),
            default => \App\Support\FloorOrderItemPriceResolver::roundUpToHundred(
                \App\Support\FloorOrderItemPriceResolver::largeToMediumPrice($priceLarge, $item)
            ),
        };

        if ($expected <= 0 || abs($expected - (float) $row->price) < 0.01) {
            continue;
        }

        $khususFixes[] = [
            'line_id' => (int) $row->id,
            'floor_order_id' => (int) $row->floor_order_id,
            'expected_price' => $expected,
            'expected_subtotal' => round($expected * (float) $row->qty, 2),
        ];
    }

    echo '  selisih=' . count($khususFixes) . "\n";
    if ($apply && $khususFixes !== []) {
        $stats = $auditor->applyFixes($khususFixes);
        echo "  updated={$stats['updated']} orders_recalculated={$stats['orders_recalculated']}\n";
    }
}

echo "\nDone.\n";
if (! $apply) {
    echo "Jalankan dengan --apply untuk perbaiki data.\n";
}
