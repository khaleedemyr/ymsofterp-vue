<?php

declare(strict_types=1);

/**
 * Perbaiki cost_small GR Serial mode auto yang tersimpan sebagai HPP mentah (tanpa +12%).
 *
 * Prioritas harga target:
 * 1) Harga FO pada DO yang sama (paling akurat vs Rekap FJ)
 * 2) HPP serial FGR Pusat + 12% (autoSellCostSmallFromGrHpp)
 *
 * Usage:
 *   php scripts/fix_serial_gr_auto_sell_prices.php
 *   php scripts/fix_serial_gr_auto_sell_prices.php --apply
 *   php scripts/fix_serial_gr_auto_sell_prices.php --from=2026-06-01 --to=2026-07-31
 *   php scripts/fix_serial_gr_auto_sell_prices.php --list69
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\ItemUnitCost;
use App\Support\SerialReceiveItemPriceResolver;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$list69 = in_array('--list69', $argv, true);
$from = '2026-01-01';
$to = date('Y-m-d');

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

echo "=== Fix Serial GR auto sell prices (HPP → jual +12%) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Periode: {$from} .. {$to}\n";
echo 'Filter item: ' . ($list69 ? '69 item audit' : 'semua item auto') . "\n\n";

$itemIds = null;
if ($list69) {
    $names = require __DIR__ . '/item_list_69.php';
    $itemIds = [];
    foreach ($names as $name) {
        $item = DB::table('items')->where('name', $name)->first(['id']);
        if (! $item) {
            $item = DB::table('items')->where('name', 'like', '%' . $name . '%')->first(['id']);
        }
        if ($item) {
            $itemIds[] = (int) $item->id;
        }
    }
    echo 'Items resolved: ' . count($itemIds) . "\n\n";
}

$query = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'it.id', '=', 'si.item_id')
    ->join('inventory_item_serials as s', 's.id', '=', 'si.serial_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
    ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
    ->whereNull('h.deleted_at')
    ->whereBetween('h.receive_date', [$from, $to])
    ->where('s.source_type', 'good_receive')
    ->whereRaw('COALESCE(si.cost_small, 0) > 0')
    ->whereRaw('COALESCE(s.cost_small, 0) > 0');

if ($itemIds !== null) {
    $query->whereIn('si.item_id', $itemIds);
}

$rows = $query->select(
    'si.id',
    'si.header_id',
    'si.item_id',
    'si.unit_id',
    'si.cost_small',
    'si.cost_source',
    'si.delivery_order_id',
    'si.qty',
    'h.number as gsr_number',
    'h.receive_date',
    'h.outlet_id',
    'o.nama_outlet',
    'o.region_id',
    'it.name as item_name',
    'it.small_unit_id',
    'it.medium_unit_id',
    'it.large_unit_id',
    'it.small_conversion_qty',
    'it.medium_conversion_qty',
    's.cost_small as serial_hpp_cost_small',
    's.source_type',
    'u.name as unit_name',
)->orderBy('h.receive_date')->orderBy('si.id')->get();

$itemMasters = DB::table('items')->whereIn('id', $rows->pluck('item_id')->unique())->get()->keyBy('id');

$doIds = $rows->pluck('delivery_order_id')->filter()->unique()->values()->all();
$foPriceByDoItem = [];
if ($doIds !== []) {
    $foRows = DB::table('delivery_orders as do')
        ->join('food_floor_orders as fo', 'fo.id', '=', 'do.floor_order_id')
        ->join('food_floor_order_items as foi', 'foi.floor_order_id', '=', 'fo.id')
        ->whereIn('do.id', $doIds)
        ->select('do.id as do_id', 'foi.item_id', 'foi.price')
        ->get();

    foreach ($foRows as $foRow) {
        $foPriceByDoItem[(int) $foRow->do_id . ':' . (int) $foRow->item_id] = (float) $foRow->price;
    }
}

$manualModeCache = [];

$fixes = [];
$skippedManual = 0;
$skippedOk = 0;
$skippedNoHpp = 0;

foreach ($rows as $row) {
    $itemId = (int) $row->item_id;
    $item = $itemMasters->get($itemId);
    if (! $item) {
        continue;
    }

    $outletId = $row->outlet_id ? (string) $row->outlet_id : null;
    $regionId = $row->region_id ? (int) $row->region_id : null;
    $manualKey = $itemId . ':' . ($outletId ?? '');

    if (! array_key_exists($manualKey, $manualModeCache)) {
        $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, $regionId, $outletId);
        $manualModeCache[$manualKey] = SerialReceiveItemPriceResolver::isManualMode($itemId, $priceRow, $regionId, $outletId);
    }

    if ($manualModeCache[$manualKey]) {
        $skippedManual++;
        continue;
    }

    $stored = (float) $row->cost_small;
    $hpp = (float) $row->serial_hpp_cost_small;
    if ($hpp <= 0) {
        $skippedNoHpp++;
        continue;
    }

    $serial = (object) [
        'unit_id' => (int) $row->unit_id,
        'cost_small' => $hpp,
        'source_type' => 'good_receive',
    ];

    $expected = null;
    $expectedSource = 'auto_fgr_12pct';

    if ($row->delivery_order_id) {
        $foPrice = $foPriceByDoItem[(int) $row->delivery_order_id . ':' . $itemId] ?? 0.0;
        if ($foPrice > 0) {
            $foUnitPrice = FloorOrderItemPriceResolver::roundUpToHundred($foPrice);
            $expected = ItemUnitCost::costSmallFromUnitPrice($foUnitPrice, $item, (int) $row->unit_id);
            $expectedSource = 'fo_do_linked';
        }
    }

    if ($expected === null || $expected <= 0) {
        $expected = SerialReceiveItemPriceResolver::autoSellCostSmallFromGrHpp($hpp, $item, $serial);
    }

    if ($expected <= 0) {
        continue;
    }

    if (abs($stored - $expected) <= 0.0001) {
        $skippedOk++;
        continue;
    }

    // Hanya perbaiki baris yang masih menyimpan HPP mentah (atau turunan HPP), bukan harga jual.
    $storedUnit = ItemUnitCost::priceForUnit($stored, $item, (int) $row->unit_id);
    $hppUnit = ItemUnitCost::priceForUnit($hpp, $item, (int) $row->unit_id);
    $expectedUnit = ItemUnitCost::priceForUnit($expected, $item, (int) $row->unit_id);

    if (abs($storedUnit - $hppUnit) > 1 && abs($stored - $hpp) > 0.0001) {
        $skippedOk++;
        continue;
    }

    $fixes[] = [
        'id' => (int) $row->id,
        'gsr' => $row->gsr_number,
        'date' => $row->receive_date,
        'outlet' => $row->nama_outlet,
        'item' => $row->item_name,
        'unit' => $row->unit_name,
        'stored' => $stored,
        'expected' => $expected,
        'expected_source' => $expectedSource,
        'stored_unit' => $storedUnit,
        'expected_unit' => $expectedUnit,
        'hpp_unit' => $hppUnit,
    ];
}

echo 'Scanned: ' . $rows->count() . "\n";
echo "Skip manual={$skippedManual} already_ok={$skippedOk} no_hpp={$skippedNoHpp}\n";
echo 'To fix: ' . count($fixes) . "\n\n";

$preview = array_slice($fixes, 0, 25);
foreach ($preview as $f) {
    echo "{$f['date']} {$f['gsr']} | {$f['item']} | {$f['outlet']}\n";
    echo "  stored={$f['stored']} (Rp " . number_format($f['stored_unit'], 0, ',', '.') . "/{$f['unit']})";
    echo ' -> ' . $f['expected'] . ' (Rp ' . number_format($f['expected_unit'], 0, ',', '.') . "/{$f['unit']})";
    echo " [{$f['expected_source']}]\n";
}
if (count($fixes) > 25) {
    echo '... dan ' . (count($fixes) - 25) . " baris lainnya\n";
}

if (! $apply || $fixes === []) {
    echo "\nDone.\n";
    if (! $apply && $fixes !== []) {
        echo "Jalankan dengan --apply untuk perbaiki data.\n";
    }
    exit(0);
}

DB::beginTransaction();
try {
    $updated = 0;
    foreach ($fixes as $f) {
        $n = DB::table('outlet_serial_receive_items')
            ->where('id', $f['id'])
            ->update([
                'cost_small' => $f['expected'],
                'cost_source' => 'auto_fgr_12pct',
                'updated_at' => now(),
            ]);
        $updated += $n;
    }
    DB::commit();
    echo "\nUpdated rows: {$updated}\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "\nERROR: {$e->getMessage()}\n";
    exit(1);
}

echo "Done.\n";
