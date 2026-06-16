<?php

/**
 * Koreksi cost_small GSR yang salah pakai batch serial padahal item_prices = manual.
 *
 * Memperbaiki:
 *   - outlet_serial_receive_items (cost_small, cost_source)
 *   - outlet_payments.total_amount untuk header GSR terkait
 *   - (opsional --with-cards) kartu stok baris serial_receive + cost_histories
 *
 * Usage:
 *   php scripts/fix_gsr_manual_item_prices.php
 *   php scripts/fix_gsr_manual_item_prices.php --apply
 *   php scripts/fix_gsr_manual_item_prices.php --apply --with-cards
 *   php scripts/fix_gsr_manual_item_prices.php --cards-only --apply   # hanya kartu stok GSR serial_receive
 *   php scripts/fix_gsr_manual_item_prices.php --item-name="%Tenderloin%Aussie%"
 *   php scripts/fix_gsr_manual_item_prices.php --item-id=52984 --since=2026-06-01
 *   php scripts/fix_gsr_manual_item_prices.php --all-manual --apply
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\ItemUnitCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);
$withCards = in_array('--with-cards', $argv ?? [], true);
$cardsOnly = in_array('--cards-only', $argv ?? [], true);
$allManual = in_array('--all-manual', $argv ?? [], true);

if ($cardsOnly) {
    $withCards = true;
}

$itemNamePattern = '%Aussie%';
$itemIdsFilter = [];
$since = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--item-name=')) {
        $itemNamePattern = substr($arg, strlen('--item-name='));
    }
    if (str_starts_with($arg, '--item-id=')) {
        $itemIdsFilter[] = (int) substr($arg, strlen('--item-id='));
    }
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, strlen('--since='));
    }
}

echo "=== Fix GSR manual item_prices ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . ($cardsOnly ? ' (cards-only)' : '') . "\n";
echo 'Scope item: ' . ($allManual ? 'semua pricing_mode=manual' : "nama LIKE {$itemNamePattern}") . "\n";
if ($since) {
    echo "Since receive_date: {$since}\n";
}
echo 'With cards/histories: ' . ($withCards ? 'yes' : 'no') . "\n\n";

if (! Schema::hasTable('outlet_serial_receive_items')) {
    echo "Tabel outlet_serial_receive_items tidak ada.\n";
    exit(1);
}

function itemPriceLargeToCostSmall(float $priceLarge, ?object $itemMaster): float
{
    if ($priceLarge <= 0 || ! $itemMaster) {
        return 0.0;
    }

    $smallConv = (float) ($itemMaster->small_conversion_qty ?? 1) ?: 1;
    $mediumConv = (float) ($itemMaster->medium_conversion_qty ?? 1) ?: 1;
    $divisor = ($smallConv > 0 && $mediumConv > 0) ? ($smallConv * $mediumConv) : 1;

    return round($priceLarge / $divisor, 4);
}

function resolveItemPriceRowForOutlet(int $itemId, ?string $outletId): ?object
{
    $regionId = null;
    if ($outletId) {
        $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
    }

    return DB::table('item_prices')
        ->where('item_id', $itemId)
        ->where(function ($q) use ($regionId, $outletId) {
            $q->where('availability_price_type', 'all');
            if ($regionId) {
                $q->orWhere(function ($q2) use ($regionId) {
                    $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                });
            }
            if ($outletId) {
                $q->orWhere(function ($q2) use ($outletId) {
                    $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                });
            }
        })
        ->orderByRaw("CASE
            WHEN availability_price_type = 'outlet' THEN 1
            WHEN availability_price_type = 'region' THEN 2
            ELSE 3 END")
        ->orderByDesc('id')
        ->first();
}

function isManualPricingMode(?object $priceRow): bool
{
    if (! $priceRow) {
        return true;
    }
    if (! Schema::hasColumn('item_prices', 'pricing_mode')) {
        return true;
    }

    return ($priceRow->pricing_mode ?? 'manual') !== 'auto';
}

function resolveExpectedManualCostSmall(int $itemId, ?string $outletId, ?object $itemMaster): ?float
{
    $priceRow = resolveItemPriceRowForOutlet($itemId, $outletId);
    if (! isManualPricingMode($priceRow)) {
        return null;
    }
    if (! $priceRow || (float) $priceRow->price <= 0) {
        return null;
    }

    return itemPriceLargeToCostSmall((float) $priceRow->price, $itemMaster);
}

function lineSubtotal(float $costSmall, object $itemMaster, $unitId, float $qty): float
{
    return ItemUnitCost::lineSubtotal($costSmall, $itemMaster, $unitId, $qty);
}

$manualItemQuery = DB::table('item_prices as ip')
    ->join('items as it', 'it.id', '=', 'ip.item_id')
    ->where('ip.price', '>', 0);

if (Schema::hasColumn('item_prices', 'pricing_mode')) {
    $manualItemQuery->where(function ($q) {
        $q->whereNull('ip.pricing_mode')->orWhere('ip.pricing_mode', '!=', 'auto');
    });
}

if ($itemIdsFilter !== []) {
    $manualItemQuery->whereIn('it.id', $itemIdsFilter);
} elseif (! $allManual) {
    $manualItemQuery->where('it.name', 'like', $itemNamePattern);
}

$targetItemIds = $manualItemQuery->distinct()->pluck('it.id');

if ($targetItemIds->isEmpty()) {
    echo "Tidak ada item manual yang cocok filter.\n";
    exit(0);
}

echo 'Item target: ' . $targetItemIds->count() . "\n";
foreach (DB::table('items')->whereIn('id', $targetItemIds)->orderBy('name')->get(['id', 'name']) as $it) {
    echo "  [{$it->id}] {$it->name}\n";
}
echo "\n";

$itemMasters = DB::table('items')->whereIn('id', $targetItemIds)->get()->keyBy('id');

/** @var array<string, ?float> */
$expectedCostCache = [];

$resolveExpectedCached = static function (int $itemId, ?string $outletId, ?object $itemMaster) use (&$expectedCostCache): ?float {
    $key = $itemId . '|' . ($outletId ?? '0');
    if (! array_key_exists($key, $expectedCostCache)) {
        $expectedCostCache[$key] = resolveExpectedManualCostSmall($itemId, $outletId, $itemMaster);
    }

    return $expectedCostCache[$key];
};

$rowsQuery = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->whereIn('si.item_id', $targetItemIds)
    ->whereNull('h.deleted_at');

if ($since) {
    $rowsQuery->whereDate('h.receive_date', '>=', $since);
}

$rows = $rowsQuery
    ->select(
        'si.id',
        'si.header_id',
        'si.serial_number',
        'si.item_id',
        'si.unit_id',
        'si.qty',
        'si.cost_small',
        'si.cost_source',
        'si.outlet_id',
        'h.number as header_number',
        'h.receive_date'
    )
    ->orderBy('si.id')
    ->get();

echo "Memuat baris GSR... selesai ({$rows->count()})\n";

$toFix = [];
$skippedAuto = 0;
$skippedOk = 0;

foreach ($rows as $row) {
    $itemMaster = $itemMasters[$row->item_id] ?? null;
    if (! $itemMaster) {
        continue;
    }

    $outletId = $row->outlet_id ? (string) $row->outlet_id : null;
    $expected = $resolveExpectedCached((int) $row->item_id, $outletId, $itemMaster);

    if ($expected === null) {
        $skippedAuto++;
        continue;
    }

    $current = round((float) $row->cost_small, 4);

    if ($cardsOnly) {
        // Hanya baris yang sudah pakai item_prices (sudah dikoreksi) — update kartu GSR saja.
        if ($row->cost_source !== 'item_prices' || abs($current - $expected) >= 0.0001) {
            continue;
        }
        $toFix[] = [
            'row' => $row,
            'item' => $itemMaster,
            'expected' => $expected,
            'current' => $current,
            'old_line' => lineSubtotal($current, $itemMaster, $row->unit_id, (float) $row->qty),
            'new_line' => lineSubtotal($expected, $itemMaster, $row->unit_id, (float) $row->qty),
            'cards_only' => true,
        ];
        continue;
    }

    if (abs($current - $expected) < 0.0001) {
        $skippedOk++;
        continue;
    }

    $toFix[] = [
        'row' => $row,
        'item' => $itemMaster,
        'expected' => $expected,
        'current' => $current,
        'old_line' => lineSubtotal($current, $itemMaster, $row->unit_id, (float) $row->qty),
        'new_line' => lineSubtotal($expected, $itemMaster, $row->unit_id, (float) $row->qty),
        'cards_only' => false,
    ];
}

echo "Baris GSR diperiksa: {$rows->count()}\n";
if (! $cardsOnly) {
    echo "Sudah benar: {$skippedOk}\n";
    echo "Skip (bukan manual / harga kosong): {$skippedAuto}\n";
}
echo 'Perlu ' . ($cardsOnly ? 'update kartu GSR' : 'koreksi') . ': ' . count($toFix) . "\n\n";

if ($toFix === []) {
    echo "Tidak ada yang perlu diperbaiki.\n";
    exit(0);
}

$headerIds = collect($toFix)->pluck('row.header_id')->unique();
$newHeaderTotals = [];

if (! $cardsOnly) {
    foreach ($rows->groupBy('header_id') as $hid => $headerRows) {
    $fixMap = collect($toFix)->filter(fn ($f) => $f['row']->header_id == $hid)->keyBy(fn ($f) => $f['row']->id);
    if ($fixMap->isEmpty()) {
        continue;
    }

    $allHeaderRows = DB::table('outlet_serial_receive_items as si')
        ->where('header_id', $hid)
        ->get(['id', 'item_id', 'unit_id', 'qty', 'cost_small']);

    $newTotal = 0.0;
    foreach ($allHeaderRows as $hr) {
        $itemMaster = $itemMasters[$hr->item_id] ?? DB::table('items')->where('id', $hr->item_id)->first();
        if (! $itemMaster) {
            continue;
        }
        $cost = $fixMap->has($hr->id)
            ? $fixMap[$hr->id]['expected']
            : (float) $hr->cost_small;
        $newTotal += lineSubtotal($cost, $itemMaster, $hr->unit_id, (float) $hr->qty);
    }
    $newHeaderTotals[$hid] = round($newTotal, 2);
    }
}

$shown = 0;
foreach ($toFix as $fix) {
    if ($shown >= 25) {
        echo '... dan ' . (count($toFix) - 25) . " baris lainnya\n";
        break;
    }
    $r = $fix['row'];
    $pcsOld = ItemUnitCost::priceForUnit($fix['current'], $fix['item'], $r->unit_id);
    $pcsNew = ItemUnitCost::priceForUnit($fix['expected'], $fix['item'], $r->unit_id);
    echo "{$r->header_number} | {$r->receive_date} | {$r->serial_number}\n";
    echo "  {$fix['item']->name}\n";
    echo "  cost_small {$fix['current']} -> {$fix['expected']} | harga/Pcs " . number_format($pcsOld, 2) . ' -> ' . number_format($pcsNew, 2) . "\n";
    echo "  line " . number_format($fix['old_line'], 2) . ' -> ' . number_format($fix['new_line'], 2) . " | src was: {$r->cost_source}\n\n";
    $shown++;
}

$paymentUpdates = [];
if (! $cardsOnly) {
    foreach ($headerIds as $hid) {
    if (! isset($newHeaderTotals[$hid])) {
        continue;
    }
    $payments = DB::table('outlet_payments')
        ->where('gsr_id', $hid)
        ->where('status', '!=', 'cancelled')
        ->get(['id', 'payment_number', 'total_amount']);

    foreach ($payments as $p) {
        $paymentUpdates[] = [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'header_id' => $hid,
            'old_total' => (float) $p->total_amount,
            'new_total' => $newHeaderTotals[$hid],
        ];
    }
    }
}

if ($paymentUpdates !== []) {
    echo '--- Outlet payments terdampak: ' . count($paymentUpdates) . " ---\n";
    foreach (array_slice($paymentUpdates, 0, 15) as $pu) {
        echo "  OP {$pu['payment_number']} (GSR #{$pu['header_id']}): " . number_format($pu['old_total'], 2) . ' -> ' . number_format($pu['new_total'], 2) . "\n";
    }
    echo "\n";
}

if (! $apply) {
    echo "DRY-RUN selesai. Jalankan dengan --apply untuk commit.\n";
    exit(0);
}

DB::beginTransaction();
try {
    $fixedItems = 0;
    $fixedCards = 0;
    foreach ($toFix as $fix) {
        $r = $fix['row'];
        $item = $fix['item'];
        $expected = $fix['expected'];
        $cardsOnlyRow = $cardsOnly || ! empty($fix['cards_only']);

        if (! $cardsOnlyRow) {
            DB::table('outlet_serial_receive_items')
                ->where('id', $r->id)
                ->update([
                    'cost_small' => $expected,
                    'cost_source' => 'item_prices',
                    'updated_at' => now(),
                ]);
            $fixedItems++;
        }

        if ($withCards) {
            $smallConv = (float) ($item->small_conversion_qty ?: 1);
            $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
            $costMedium = $expected * $smallConv;
            $costLarge = $costMedium * $mediumConv;
            $qty = (float) $r->qty;
            $unitId = (int) $r->unit_id;
            $qtySmall = $qty;
            if ($unitId === (int) $item->medium_unit_id) {
                $qtySmall = $qty * $smallConv;
            } elseif ($unitId === (int) $item->large_unit_id) {
                $qtySmall = $qty * $smallConv * $mediumConv;
            }
            $valueIn = round($qtySmall * $expected, 4);

            $card = DB::table('outlet_food_inventory_cards')
                ->where('reference_type', 'serial_receive')
                ->where('reference_id', $r->header_id)
                ->where('description', 'like', '%' . $r->serial_number . '%')
                ->first();

            if ($card) {
                DB::table('outlet_food_inventory_cards')
                    ->where('id', $card->id)
                    ->update([
                        'cost_per_small' => $expected,
                        'cost_per_medium' => $costMedium,
                        'cost_per_large' => $costLarge,
                        'value_in' => $valueIn,
                        'updated_at' => now(),
                    ]);
                $fixedCards++;

                if (Schema::hasTable('outlet_food_inventory_cost_histories')) {
                    $histQuery = DB::table('outlet_food_inventory_cost_histories')
                        ->where('reference_type', 'serial_receive')
                        ->where('reference_id', $r->header_id)
                        ->where('type', 'serial_receive')
                        ->where('inventory_item_id', $card->inventory_item_id)
                        ->where('date', $card->date);

                    if (Schema::hasColumn('outlet_food_inventory_cost_histories', 'id_outlet')) {
                        $histQuery->where('id_outlet', $card->id_outlet);
                    }
                    if (Schema::hasColumn('outlet_food_inventory_cost_histories', 'warehouse_outlet_id')) {
                        $histQuery->where('warehouse_outlet_id', $card->warehouse_outlet_id);
                    }

                    $oldCardCost = (float) $card->cost_per_small;
                    if (abs($oldCardCost - $expected) >= 0.0001) {
                        $histQuery->whereBetween('new_cost', [$oldCardCost - 0.01, $oldCardCost + 0.01]);
                    }

                    $histQuery->update([
                        'new_cost' => $expected,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    $fixedPayments = 0;
    if (! $cardsOnly) {
        foreach ($paymentUpdates as $pu) {
        if (abs($pu['old_total'] - $pu['new_total']) < 0.01) {
            continue;
        }
        DB::table('outlet_payments')
            ->where('id', $pu['id'])
            ->update([
                'total_amount' => $pu['new_total'],
                'updated_at' => now(),
            ]);
        $fixedPayments++;
        }
    }

    DB::commit();

    echo "APPLY selesai.\n";
    if ($cardsOnly) {
        echo "  Kartu stok GSR (serial_receive) diperbarui: {$fixedCards} baris\n";
        echo "  GSR items & outlet payment tidak diubah (cards-only).\n";
    } else {
        echo "  GSR items diperbaiki: {$fixedItems}\n";
        echo "  Outlet payments diperbarui: {$fixedPayments}\n";
        if ($withCards) {
            echo "  Kartu stok GSR (serial_receive) diperbarui: {$fixedCards} baris\n";
        } else {
            echo "  Kartu stok tidak diubah (pakai --cards-only atau --with-cards).\n";
        }
    }
} catch (\Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
