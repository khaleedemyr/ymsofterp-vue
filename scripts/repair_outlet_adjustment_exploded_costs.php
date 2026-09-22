<?php
/**
 * Repair outlet_stock_adjustment cards that copied exploded IB/stock MAC
 * (Kuah Garang Asam / Kuah Buntut — Sep 2026 "adjust revisi ending").
 *
 * Root cause: Adjustment IN used stock MAC when >0 without sanitizing against
 * GR/serial receive. IB itself carried MK-poisoned cost (~15048 / ~7558 vs ~43 / ~38).
 *
 * Usage:
 *   php scripts/repair_outlet_adjustment_exploded_costs.php --dry-run
 *   php scripts/repair_outlet_adjustment_exploded_costs.php
 *   php scripts/repair_outlet_adjustment_exploded_costs.php --outlet=31
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

$fmt = static fn (float $n): string => number_format($n, 2, '.', ',');

echo "=== Repair exploded outlet_stock_adjustment costs ===\n";
echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . "\n";
echo 'Outlet filter: ' . ($outletOpt ?: 'ALL (known soup adj batch)') . "\n\n";

// Card IDs from investigation (OSA 7136/7137/7138) + any matching pattern
$knownCardIds = [2923398, 2923399, 2923563, 2923564, 2923614];

$trustedByInv = [
    5135 => 43.0050,  // Kuah Garang Asam — serial_receive
    5134 => 38.1100,  // Kuah Buntut — serial_receive
];

$q = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
    ->join('items as i', 'fi.item_id', '=', 'i.id')
    ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
    ->where('c.reference_type', 'outlet_stock_adjustment')
    ->where(function ($w) use ($knownCardIds, $trustedByInv) {
        $w->whereIn('c.id', $knownCardIds)
            ->orWhere(function ($w2) use ($trustedByInv) {
                $w2->whereIn('c.inventory_item_id', array_keys($trustedByInv))
                    ->whereDate('c.date', '2026-09-01')
                    ->where('c.cost_per_small', '>', 1000);
            });
    })
    ->when($outletOpt, fn ($q) => $q->where('c.id_outlet', $outletOpt))
    ->select([
        'c.id', 'c.id_outlet', 'c.warehouse_outlet_id', 'c.inventory_item_id', 'c.reference_id',
        'c.date', 'c.in_qty_small', 'c.cost_per_small', 'c.cost_per_medium', 'c.cost_per_large',
        'c.value_in', 'c.saldo_qty_small', 'c.saldo_value',
        'i.name as item_name', 'i.sku', 'o.nama_outlet',
    ])
    ->orderBy('c.id')
    ->get();

if ($q->isEmpty()) {
    echo "No matching adjustment cards.\n";
    exit(0);
}

$fixedCards = 0;
$fixedHist = 0;
$fixedIb = 0;

DB::beginTransaction();
try {
    foreach ($q as $c) {
        $invId = (int) $c->inventory_item_id;
        $trusted = $trustedByInv[$invId]
            ?? OutletInventoryCostResolver::latestTrustedNewCostPerSmallUnit(
                (int) $c->id_outlet,
                (int) $c->warehouse_outlet_id,
                $invId
            );
        if ($trusted === null || $trusted <= 0) {
            echo "[SKIP] card#{$c->id} no trusted cost\n";
            continue;
        }

        $oldCost = (float) $c->cost_per_small;
        if ($oldCost <= $trusted * 5) {
            echo "[OK] card#{$c->id} cost={$fmt($oldCost)} already near trusted {$fmt($trusted)}\n";
            continue;
        }

        $qtyIn = (float) $c->in_qty_small;
        $newVin = round($qtyIn * $trusted, 2);

        // saldo on adj card: previous saldo_value ≈ old saldo_value - old value_in
        $prevSaldoVal = max(0, (float) $c->saldo_value - (float) $c->value_in);
        $newSaldoVal = round($prevSaldoVal + $newVin, 2);
        // if qty after adj known, prefer qty * trusted for consistency when prev was also poisoned
        $saldoQty = (float) $c->saldo_qty_small;
        if ($saldoQty > 0.0001 && $prevSaldoVal > $trusted * $saldoQty * 5) {
            $newSaldoVal = round($saldoQty * $trusted, 2);
        }

        $med = $trusted; // keep medium/large proportional if old ratio known
        $large = $trusted;
        if ($oldCost > 0) {
            $med = $trusted * ((float) $c->cost_per_medium / $oldCost);
            $large = $trusted * ((float) $c->cost_per_large / $oldCost);
        }

        echo sprintf(
            "[FIX] #%d %s o%d %s qty=%.0f cost %s → %s vin %s → %s saldo_v %s → %s\n",
            $c->id,
            $c->item_name,
            $c->id_outlet,
            $c->nama_outlet,
            $qtyIn,
            $fmt($oldCost),
            $fmt($trusted),
            $fmt((float) $c->value_in),
            $fmt($newVin),
            $fmt((float) $c->saldo_value),
            $fmt($newSaldoVal)
        );

        if (! $dryRun) {
            DB::table('outlet_food_inventory_cards')->where('id', $c->id)->update([
                'cost_per_small' => $trusted,
                'cost_per_medium' => $med,
                'cost_per_large' => $large,
                'value_in' => $newVin,
                'saldo_value' => $newSaldoVal,
                'updated_at' => now(),
            ]);
            $fixedCards++;

            if (Schema::hasTable('outlet_food_inventory_cost_histories')) {
                $n = DB::table('outlet_food_inventory_cost_histories')
                    ->where('reference_type', 'outlet_stock_adjustment')
                    ->where('reference_id', $c->reference_id)
                    ->where('inventory_item_id', $invId)
                    ->where('id_outlet', $c->id_outlet)
                    ->update([
                        'old_cost' => $oldCost,
                        'new_cost' => $trusted,
                        'mac' => $trusted,
                        'updated_at' => now(),
                    ]);
                $fixedHist += $n;

                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $invId,
                    'id_outlet' => $c->id_outlet,
                    'warehouse_outlet_id' => $c->warehouse_outlet_id,
                    'date' => now()->toDateString(),
                    'old_cost' => $oldCost,
                    'new_cost' => $trusted,
                    'mac' => $trusted,
                    'type' => 'mac_correction',
                    'reference_type' => 'mac_correction',
                    'reference_id' => $c->reference_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            $fixedCards++;
        }
    }

    // Repair matching initial_balance cards (same items, exploded cost) — begin inventory
    $ibRows = DB::table('outlet_food_inventory_cards as c')
        ->where('c.reference_type', 'initial_balance')
        ->whereIn('c.inventory_item_id', array_keys($trustedByInv))
        ->whereDate('c.date', '2026-09-01')
        ->where('c.cost_per_small', '>', 1000)
        ->when($outletOpt, fn ($q) => $q->where('c.id_outlet', $outletOpt))
        ->get(['c.id', 'c.id_outlet', 'c.inventory_item_id', 'c.cost_per_small', 'c.saldo_qty_small', 'c.saldo_value', 'c.value_in']);

    foreach ($ibRows as $ib) {
        $trusted = $trustedByInv[(int) $ib->inventory_item_id];
        $old = (float) $ib->cost_per_small;
        if ($old <= $trusted * 5) {
            continue;
        }
        $qty = (float) $ib->saldo_qty_small;
        $newVal = round($qty * $trusted, 2);
        echo sprintf(
            "[FIX-IB] #%d o%d inv%d cost %s → %s saldo_v %s → %s\n",
            $ib->id,
            $ib->id_outlet,
            $ib->inventory_item_id,
            $fmt($old),
            $fmt($trusted),
            $fmt((float) $ib->saldo_value),
            $fmt($newVal)
        );
        if (! $dryRun) {
            DB::table('outlet_food_inventory_cards')->where('id', $ib->id)->update([
                'cost_per_small' => $trusted,
                'cost_per_medium' => $trusted,
                'cost_per_large' => $trusted,
                'value_in' => $newVal,
                'saldo_value' => $newVal,
                'updated_at' => now(),
            ]);
            $fixedIb++;
        } else {
            $fixedIb++;
        }
    }

    if ($dryRun) {
        DB::rollBack();
        echo "\nDRY-RUN done. Would fix cards={$fixedCards} ib={$fixedIb}\n";
    } else {
        DB::commit();
        echo "\nAPPLIED. cards={$fixedCards} hist_rows={$fixedHist} ib={$fixedIb}\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}
