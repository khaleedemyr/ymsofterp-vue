<?php
/**
 * Trace + repair outlet inventory costs that clearly exploded via transfer / pack-as-small.
 *
 * Conservative rules — ONLY repair when:
 *  1) last_cost_small > 500_000 (soft cap), OR
 *  2) last_cost_small > 10_000 AND > 5× trusted same-WH GR/IB/serial, OR
 *  3) transfer card cost_per_small > 10_000 AND > 5× trusted, OR
 *  4) orphan value (qty≈0, |value| > 1)
 *
 * Trusted anchor = most recent same-warehouse GR/serial/IB (history new_cost or card value_in/qty).
 * Fallback: other WH recent GR, then sibling stock if still none.
 *
 * Usage:
 *   php scripts/repair_outlet_transfer_exploded_costs.php --dry-run
 *   php scripts/repair_outlet_transfer_exploded_costs.php --outlet=20 --dry-run
 *   php scripts/repair_outlet_transfer_exploded_costs.php --outlet=20
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\OutletInventoryCostResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$outletOpt = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--outlet=')) {
        $outletOpt = (int) substr($arg, 9);
    }
}

const SOFT_CAP = 500_000.0;
const MIN_ABS_SPIKE = 1_000.0; // abaikan spike kecil (hindari false-positive sauce ~50)
const SPIKE_MULT = 5.0;
const TRUSTED_REFS = [
    'serial_receive',
    'good_receive_outlet',
    'outlet_food_good_receive',
    'initial_balance',
];
const TRANSFER_REFS = [
    'internal_warehouse_transfer',
    'outlet_transfer',
    'warehouse_transfer',
    'outlet_internal_transfer',
    'stock_transfer',
];

$fmt = static fn (float $n): string => number_format($n, 2, '.', ',');

echo "=== Outlet Transfer Cost Explosion Trace+Repair (conservative) ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'Outlet filter: ' . ($outletOpt ?: 'ALL') . "\n\n";

$hasCostHist = Schema::hasTable('outlet_food_inventory_cost_histories');

function trustedCostSameWh(int $outletId, int $whId, int $invId): ?float
{
    $hist = DB::table('outlet_food_inventory_cost_histories')
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('inventory_item_id', $invId)
        ->whereIn('reference_type', TRUSTED_REFS)
        ->where('new_cost', '>', 0)
        ->where('new_cost', '<=', SOFT_CAP)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first(['new_cost']);
    if ($hist) {
        return (float) $hist->new_cost;
    }

    $card = DB::table('outlet_food_inventory_cards')
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('inventory_item_id', $invId)
        ->whereIn('reference_type', array_merge(TRUSTED_REFS, ['retail_food', 'retail_warehouse_food']))
        ->where('in_qty_small', '>', 0)
        ->where('value_in', '>', 0)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first(['in_qty_small', 'value_in', 'cost_per_small']);

    if ($card) {
        $true = (float) $card->value_in / (float) $card->in_qty_small;
        if ($true > 0 && $true <= SOFT_CAP && is_finite($true)) {
            return $true;
        }
        $cps = (float) ($card->cost_per_small ?? 0);
        if ($cps > 0 && $cps <= SOFT_CAP) {
            return $cps;
        }
    }

    return null;
}

function trustedCostAnyWh(int $outletId, int $invId, int $preferWh): ?float
{
    $same = trustedCostSameWh($outletId, $preferWh, $invId);
    if ($same !== null) {
        return $same;
    }

    $hist = DB::table('outlet_food_inventory_cost_histories')
        ->where('id_outlet', $outletId)
        ->where('inventory_item_id', $invId)
        ->whereIn('reference_type', TRUSTED_REFS)
        ->where('new_cost', '>', 0)
        ->where('new_cost', '<=', SOFT_CAP)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first(['new_cost']);
    if ($hist) {
        return (float) $hist->new_cost;
    }

    $card = DB::table('outlet_food_inventory_cards')
        ->where('id_outlet', $outletId)
        ->where('inventory_item_id', $invId)
        ->whereIn('reference_type', array_merge(TRUSTED_REFS, ['retail_food', 'retail_warehouse_food']))
        ->where('in_qty_small', '>', 0)
        ->where('value_in', '>', 0)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first(['in_qty_small', 'value_in', 'cost_per_small']);
    if ($card) {
        $true = (float) $card->value_in / (float) $card->in_qty_small;
        if ($true > 0 && $true <= SOFT_CAP && is_finite($true)) {
            return $true;
        }
    }

    $sib = DB::table('outlet_food_inventory_stocks')
        ->where('id_outlet', $outletId)
        ->where('inventory_item_id', $invId)
        ->where('last_cost_small', '>', 0)
        ->where('last_cost_small', '<=', MIN_ABS_SPIKE)
        ->orderBy('last_cost_small')
        ->first(['last_cost_small']);

    return $sib ? (float) $sib->last_cost_small : null;
}

function shouldRepair(float $cost, ?float $trusted, bool $hasXferSpike): bool
{
    if ($cost <= 0) {
        return false;
    }
    if ($cost > SOFT_CAP) {
        return true;
    }
    if ($trusted !== null && $trusted > 0 && ($cost / $trusted) > SPIKE_MULT && $cost >= MIN_ABS_SPIKE) {
        return true;
    }
    if ($hasXferSpike && $trusted !== null && $trusted > 0 && ($cost / $trusted) > SPIKE_MULT) {
        return true;
    }

    return false;
}

$stockQuery = DB::table('outlet_food_inventory_stocks as s')
    ->join('outlet_food_inventory_items as fi', 's.inventory_item_id', '=', 'fi.id')
    ->join('items as i', 'fi.item_id', '=', 'i.id')
    ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
    ->leftJoin('tbl_data_outlet as o', 's.id_outlet', '=', 'o.id_outlet')
    ->select([
        's.id',
        's.id_outlet',
        's.warehouse_outlet_id',
        's.inventory_item_id',
        's.qty_small',
        's.value',
        's.last_cost_small',
        's.last_cost_medium',
        's.last_cost_large',
        'i.name as item_name',
        'i.sku',
        'i.small_conversion_qty',
        'i.medium_conversion_qty',
        'wo.name as warehouse_name',
        'o.nama_outlet',
    ]);

if ($outletOpt) {
    $stockQuery->where('s.id_outlet', $outletOpt);
}

$stocks = $stockQuery->get();
$fixStocks = 0;
$fixCards = 0;
$fixOrphans = 0;
$skippedNoAnchor = 0;

echo "--- STOCK SCAN ({$stocks->count()} rows) ---\n";

foreach ($stocks as $s) {
    $outletId = (int) $s->id_outlet;
    $whId = (int) $s->warehouse_outlet_id;
    $invId = (int) $s->inventory_item_id;
    $qty = (float) $s->qty_small;
    $value = (float) $s->value;
    $lastCost = (float) $s->last_cost_small;
    $implied = $qty > 0.0001 ? ($value / $qty) : 0.0;

    $orphan = abs($qty) < 0.0001 && abs($value) > 1;

    // Pre-filter: skip quiet cheap stocks
    if (! $orphan && $lastCost < MIN_ABS_SPIKE && $implied < MIN_ABS_SPIKE) {
        continue;
    }

    $trusted = trustedCostAnyWh($outletId, $invId, $whId);

    $xferSpike = null;
    if ($lastCost >= MIN_ABS_SPIKE || $implied >= MIN_ABS_SPIKE || $orphan) {
        $xferQ = DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $whId)
            ->where('inventory_item_id', $invId)
            ->where(function ($q) {
                $q->whereIn('reference_type', TRANSFER_REFS)
                    ->orWhere('reference_type', 'like', '%transfer%');
            })
            ->where('cost_per_small', '>=', MIN_ABS_SPIKE)
            ->orderByDesc('cost_per_small')
            ->first(['id', 'date', 'reference_type', 'cost_per_small', 'value_in', 'value_out']);
        $xferSpike = $xferQ;
    }

    $hasXfer = $xferSpike !== null
        && $trusted !== null
        && $trusted > 0
        && ((float) $xferSpike->cost_per_small / $trusted) > SPIKE_MULT;

    $costBad = shouldRepair($lastCost, $trusted, $hasXfer) || shouldRepair($implied, $trusted, $hasXfer);

    if (! $orphan && ! $costBad) {
        continue;
    }

    if ($trusted === null || $trusted <= 0) {
        if ($orphan) {
            $trusted = 0.0;
        } else {
            $skippedNoAnchor++;
            echo sprintf(
                "[SKIP] %s | %s | %s [%s] last=%s implied=%s trusted=NONE\n",
                $s->nama_outlet ?? $outletId,
                $s->warehouse_name ?? $whId,
                $s->item_name,
                $s->sku,
                $fmt($lastCost),
                $fmt($implied)
            );
            continue;
        }
    }

    $newLast = (float) $trusted;
    $newValue = $orphan ? 0.0 : OutletInventoryCostResolver::stockTotalValue($qty, $newLast);

    $smallConv = (float) ($s->small_conversion_qty ?: 1);
    $mediumConv = (float) ($s->medium_conversion_qty ?: 1);
    if ($smallConv <= 0) {
        $smallConv = 1.0;
    }
    if ($mediumConv <= 0) {
        $mediumConv = 1.0;
    }
    $costMed = $newLast * $smallConv;
    $costLarge = $costMed * $mediumConv;

    $reasons = [];
    if ($orphan) {
        $reasons[] = 'orphan_value';
    }
    if ($costBad) {
        $reasons[] = 'cost_exploded';
    }
    if ($hasXfer) {
        $reasons[] = 'transfer_spike';
    }

    echo sprintf(
        "[%s] %s | %s | %s [%s] qty=%s last=%s→%s value=%s→%s trusted=%s xfer=%s reasons=%s\n",
        $dryRun ? 'DRY' : 'FIX',
        $s->nama_outlet ?? $outletId,
        $s->warehouse_name ?? $whId,
        $s->item_name,
        $s->sku,
        $fmt($qty),
        $fmt($lastCost),
        $fmt($newLast),
        $fmt($value),
        $fmt($newValue),
        $fmt($newLast),
        $xferSpike
            ? sprintf('%s#%s@%s cost=%s', $xferSpike->reference_type, $xferSpike->id, $xferSpike->date, $fmt((float) $xferSpike->cost_per_small))
            : '-',
        implode(',', $reasons)
    );

    if ($dryRun) {
        if ($orphan) {
            $fixOrphans++;
        } else {
            $fixStocks++;
        }
        continue;
    }

    DB::table('outlet_food_inventory_stocks')->where('id', $s->id)->update([
        'last_cost_small' => $newLast,
        'last_cost_medium' => $costMed,
        'last_cost_large' => $costLarge,
        'value' => $newValue,
        'updated_at' => now(),
    ]);

    if ($hasCostHist && $newLast > 0 && abs($newLast - $lastCost) > 0.0001) {
        DB::table('outlet_food_inventory_cost_histories')->insert([
            'id_outlet' => $outletId,
            'warehouse_outlet_id' => $whId,
            'inventory_item_id' => $invId,
            'date' => now()->toDateString(),
            'old_cost' => $lastCost,
            'new_cost' => $newLast,
            'mac' => $newLast,
            'type' => 'mac_correction',
            'reference_type' => 'mac_correction',
            'reference_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    if ($orphan) {
        $fixOrphans++;
    } else {
        $fixStocks++;
    }

    $latestKey = DB::table('outlet_food_inventory_cards')
        ->where('id_outlet', $outletId)
        ->where('warehouse_outlet_id', $whId)
        ->where('inventory_item_id', $invId)
        ->selectRaw("MAX(CONCAT(DATE(date), ' ', LPAD(id, 20, '0'))) as latest_key")
        ->value('latest_key');

    if ($latestKey) {
        $latest = DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $outletId)
            ->where('warehouse_outlet_id', $whId)
            ->where('inventory_item_id', $invId)
            ->whereRaw("CONCAT(DATE(date), ' ', LPAD(id, 20, '0')) = ?", [$latestKey])
            ->first();
        if ($latest) {
            $lq = (float) $latest->saldo_qty_small;
            $lv = (float) $latest->saldo_value;
            $lc = (float) ($latest->cost_per_small ?? 0);
            $upd = [];
            if (abs($lq) < 0.0001 && abs($lv) > 1) {
                $upd['saldo_value'] = 0;
            } elseif ($lq > 0.0001 && shouldRepair($lc, $trusted, $hasXfer)) {
                $upd['cost_per_small'] = $newLast;
                $upd['saldo_value'] = OutletInventoryCostResolver::stockTotalValue($lq, $newLast);
            }
            if ($upd !== []) {
                $upd['updated_at'] = now();
                DB::table('outlet_food_inventory_cards')->where('id', $latest->id)->update($upd);
                $fixCards++;
            }
        }
    }
}

echo "\n--- TRANSFER CARDS cost>10k (top 30, Cipete-focused) ---\n";
$xferCardsQ = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
    ->join('items as i', 'fi.item_id', '=', 'i.id')
    ->leftJoin('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
    ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
    ->where(function ($q) {
        $q->whereIn('c.reference_type', TRANSFER_REFS)
            ->orWhere('c.reference_type', 'like', '%transfer%');
    })
    ->where('c.cost_per_small', '>', 10_000)
    ->orderByDesc('c.cost_per_small')
    ->limit(30)
    ->select([
        'c.id', 'c.date', 'c.id_outlet', 'c.warehouse_outlet_id', 'c.inventory_item_id',
        'c.reference_type', 'c.reference_id', 'c.cost_per_small', 'c.value_in', 'c.value_out',
        'i.name as item_name', 'i.sku', 'wo.name as warehouse_name', 'o.nama_outlet',
    ]);
if ($outletOpt) {
    $xferCardsQ->where('c.id_outlet', $outletOpt);
}
foreach ($xferCardsQ->get() as $c) {
    $t = trustedCostAnyWh((int) $c->id_outlet, (int) $c->inventory_item_id, (int) $c->warehouse_outlet_id);
    echo sprintf(
        "card#%d %s %s|%s %s [%s] ref=%s/%s cost=%s trusted=%s vin=%s vout=%s\n",
        $c->id,
        $c->date,
        $c->nama_outlet ?? $c->id_outlet,
        $c->warehouse_name ?? $c->warehouse_outlet_id,
        $c->item_name,
        $c->sku,
        $c->reference_type,
        $c->reference_id,
        $fmt((float) $c->cost_per_small),
        $t !== null ? $fmt($t) : 'n/a',
        $fmt((float) ($c->value_in ?? 0)),
        $fmt((float) ($c->value_out ?? 0))
    );
}

echo "\n=== SUMMARY ===\n";
echo 'Stocks repaired: ' . $fixStocks . "\n";
echo 'Orphans cleared: ' . $fixOrphans . "\n";
echo 'Latest cards realigned: ' . $fixCards . "\n";
echo 'Skipped (no anchor): ' . $skippedNoAnchor . "\n";
if ($dryRun) {
    echo "\nRe-run without --dry-run to apply.\n";
}
