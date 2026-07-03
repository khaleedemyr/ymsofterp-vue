<?php

declare(strict_types=1);

/**
 * Perbaiki selisih Rekap FJ vs item_prices untuk 10 item batch user.
 *
 * - GR Serial: cost_small dari item_prices
 * - FO auditor: Juni 2026 (RO Utama)
 * - FO terhubung GR Juni tapi tanggal FO di luar Juni (mis. 2026-05-31)
 *
 * Usage:
 *   php scripts/fix_fj_items_user_batch.php
 *   php scripts/fix_fj_items_user_batch.php --apply
 *   php scripts/fix_fj_items_user_batch.php --apply --serial-only
 *   php scripts/fix_fj_items_user_batch.php --apply --fo-only
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);
$serialOnly = in_array('--serial-only', $argv, true);
$foOnly = in_array('--fo-only', $argv, true);
$from = '2026-06-01';
$to = '2026-06-30';

$itemNames = [
    'Artisan Tea Ceylon Green Tea',
    'Artisan Tea Ginger & Mint',
    'Butter Salted',
    'Cup 12 Logo SH',
    'Gas Whipped',
    'Kacang Arab',
    'Kacang Tanah',
    'Kecap Asin Angsa',
    'Salad Oil',
    'Vetcin Powder',
];

$itemIds = [];
$missing = [];
foreach ($itemNames as $name) {
    $item = DB::table('items')->where('name', $name)->first(['id', 'name']);
    if ($item) {
        $itemIds[(int) $item->id] = $item->name;
    } else {
        $missing[] = $name;
    }
}
$idList = implode(',', array_keys($itemIds));

echo "=== Fix FJ user batch (10 items) Juni 2026 ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "GR periode: {$from} .. {$to}\n";
if ($missing !== []) {
    echo 'Missing items: ' . implode(', ', $missing) . "\n";
}
echo "\n";

$runSerial = ! $foOnly;
$runFo = ! $serialOnly;

if ($runSerial && Schema::hasTable('outlet_serial_receive_headers') && $idList !== '') {
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

    $scanned = (int) DB::selectOne($countSql, [$from, $to])->cnt;
    $toFix = (int) DB::selectOne($toFixSql, [$from, $to])->cnt;

    echo "--- GR Serial scanned={$scanned} to_fix={$toFix} ---\n";

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

if ($runFo && $idList !== '') {
    $auditor = new FloorOrderPriceAuditor();
    $itemIdList = array_keys($itemIds);

    $result = $auditor->scan($from, $to, true, $itemIdList);
    $mismatches = $result['mismatches'];

    echo "--- FO auditor Juni (all statuses) ---\n";
    echo "  scanned={$result['rows_scanned']} selisih=" . count($mismatches) . "\n";
    foreach ($result['summary_by_item'] ?? [] as $s) {
        echo "    {$s['item_name']}: {$s['mismatch_rows']} rows\n";
    }

    $grLinkedRows = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->join('food_floor_order_items as ffoi', function ($join) {
            $join->on('gri.item_id', '=', 'ffoi.item_id')
                ->on('ffoi.floor_order_id', '=', 'do.floor_order_id');
        })
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->whereIn('gri.item_id', $itemIdList)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->whereNotIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
        ->where(function ($q) use ($from, $to) {
            $q->whereDate('ffo.tanggal', '<', $from)
                ->orWhereDate('ffo.tanggal', '>', $to);
        })
        ->select(
            'ffoi.id',
            'ffoi.floor_order_id',
            'ffoi.item_id',
            'ffoi.item_name',
            'ffoi.qty',
            'ffoi.price',
            'ffoi.unit',
            'ffo.tanggal',
            'ffo.order_number',
            'ffo.status',
            'ffo.id_outlet',
            'o.region_id',
            'o.nama_outlet',
        )
        ->distinct()
        ->get();

    echo "--- FO terhubung GR Juni, tanggal FO di luar Juni ---\n";
    echo '  scanned=' . $grLinkedRows->count() . "\n";

    $itemMasters = DB::table('items')->whereIn('id', $itemIdList)->get()->keyBy('id');
    $unitNameById = DB::table('units')->pluck('name', 'id')->all();
    $priceRowsByItem = DB::table('item_prices')->whereIn('item_id', $itemIdList)->orderByDesc('id')->get()->groupBy('item_id');

    $existingLineIds = array_flip(array_column($mismatches, 'line_id'));
    $grLinkedFixes = [];

    foreach ($grLinkedRows as $row) {
        $lineId = (int) $row->id;
        if (isset($existingLineIds[$lineId])) {
            continue;
        }

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
        $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
        if ($priceLarge <= 0) {
            continue;
        }

        $tier = FloorOrderItemPriceResolver::detectUnitTier($item, (string) $row->unit, $unitNameById);
        $expected = match ($tier) {
            'large' => FloorOrderItemPriceResolver::roundUpToHundred($priceLarge),
            'small' => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToSmallPrice($priceLarge, $item)
            ),
            default => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToMediumPrice($priceLarge, $item)
            ),
        };

        if ($expected <= 0 || abs($expected - (float) $row->price) < 0.01) {
            continue;
        }

        $grLinkedFixes[] = [
            'line_id' => $lineId,
            'floor_order_id' => (int) $row->floor_order_id,
            'item_id' => $itemId,
            'item_name' => (string) $row->item_name,
            'tanggal' => (string) $row->tanggal,
            'order_number' => (string) ($row->order_number ?? ''),
            'outlet' => (string) ($row->nama_outlet ?? ''),
            'unit' => (string) $row->unit,
            'current_price' => (float) $row->price,
            'expected_price' => $expected,
            'expected_subtotal' => round($expected * (float) $row->qty, 2),
        ];
        $existingLineIds[$lineId] = true;
    }

    echo '  selisih=' . count($grLinkedFixes) . "\n";
    foreach ($grLinkedFixes as $fix) {
        echo "    {$fix['item_name']} line={$fix['line_id']} tanggal={$fix['tanggal']} {$fix['current_price']} -> {$fix['expected_price']} ({$fix['outlet']})\n";
    }

    $allFixes = array_merge(
        $mismatches,
        array_map(static fn (array $f) => [
            'line_id' => $f['line_id'],
            'floor_order_id' => $f['floor_order_id'],
            'expected_price' => $f['expected_price'],
            'expected_subtotal' => $f['expected_subtotal'],
        ], $grLinkedFixes)
    );

    if ($apply && $allFixes !== []) {
        DB::beginTransaction();
        try {
            $stats = $auditor->applyFixes($allFixes);
            DB::commit();
            echo "  FO updated={$stats['updated']} orders_recalculated={$stats['orders_recalculated']}\n";
        } catch (Throwable $e) {
            DB::rollBack();
            echo '  ERROR: ' . $e->getMessage() . "\n";
            exit(1);
        }
    }

    $khususRows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->whereIn('ffoi.item_id', $itemIdList)
        ->whereIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
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
        ->select(
            'ffoi.id',
            'ffoi.floor_order_id',
            'ffoi.item_id',
            'ffoi.qty',
            'ffoi.price',
            'ffoi.unit',
            'ffo.id_outlet',
            'o.region_id',
            'ffo.fo_mode',
            'ffo.tanggal',
            'ffo.order_number',
        )
        ->get();

    echo "--- FO RO Khusus/Supplier (Juni atau terhubung GR Juni) ---\n";
    echo '  scanned=' . $khususRows->count() . "\n";

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
        $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
        if ($priceLarge <= 0) {
            continue;
        }

        $tier = FloorOrderItemPriceResolver::detectUnitTier($item, (string) $row->unit, $unitNameById);
        $expected = match ($tier) {
            'large' => FloorOrderItemPriceResolver::roundUpToHundred($priceLarge),
            'small' => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToSmallPrice($priceLarge, $item)
            ),
            default => FloorOrderItemPriceResolver::roundUpToHundred(
                FloorOrderItemPriceResolver::largeToMediumPrice($priceLarge, $item)
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
            'item_name' => $item->name ?? '',
            'current_price' => (float) $row->price,
            'fo_mode' => (string) $row->fo_mode,
            'tanggal' => (string) $row->tanggal,
        ];
    }

    echo '  selisih=' . count($khususFixes) . "\n";
    foreach ($khususFixes as $fix) {
        echo "    {$fix['item_name']} line={$fix['line_id']} {$fix['fo_mode']} {$fix['tanggal']} {$fix['current_price']} -> {$fix['expected_price']}\n";
    }

    if ($apply && $khususFixes !== []) {
        $stats = $auditor->applyFixes(array_map(static fn (array $f) => [
            'line_id' => $f['line_id'],
            'floor_order_id' => $f['floor_order_id'],
            'expected_price' => $f['expected_price'],
            'expected_subtotal' => $f['expected_subtotal'],
        ], $khususFixes));
        echo "  RO Khusus/Supplier updated={$stats['updated']}\n";
    }
}

echo "\nDone.\n";
if (! $apply) {
    echo "Jalankan dengan --apply untuk perbaiki data.\n";
}
