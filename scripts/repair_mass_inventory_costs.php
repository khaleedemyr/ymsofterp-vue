<?php
/**
 * Mass repair corrupted food_inventory_stocks costs.
 * See docs/cost_explosion_features.md
 *
 * Usage:
 *   php scripts/repair_mass_inventory_costs.php --dry-run
 *   php scripts/repair_mass_inventory_costs.php
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$whIds = [1, 2, 5]; // Main Store, MK1, MK2
$HIGH_COST = 100000;      // cost_small di atas ini dicurigai (kecuali ada bukti GR/retail valid)
$ABSURD_COST = 1000000;   // pasti rusak
$HIGH_VALUE = 500000000;  // value > 500jt dicurigai

$whNames = DB::table('warehouses')->whereIn('id', $whIds)->pluck('name', 'id');

function isSaneCost(float $c, float $absurd): bool
{
    return $c > 0 && $c < $absurd;
}

function resolveSaneCost(int $invId, int $whId, float $smallConv, float $absurd, float $highCost): ?array
{
    // 1) Main Store reference (wh=1) if sane
    if ($whId !== 1) {
        $ms = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', 1)
            ->first();
        if ($ms && isSaneCost((float) $ms->last_cost_small, $highCost)) {
            return [(float) $ms->last_cost_small, 'main_store'];
        }
        if ($ms && $smallConv > 1 && isSaneCost((float) $ms->last_cost_medium / $smallConv, $highCost)
            && (float) $ms->last_cost_medium > 0 && (float) $ms->last_cost_medium < 5000000) {
            return [(float) $ms->last_cost_medium / $smallConv, 'main_store_medium'];
        }
    }

    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('in_qty_small', '>', 0)
        ->where('value_in', '>', 0)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->limit(50)
        ->get();

    // 2) Prefer non-mk_production IN cards (GR / retail / transfer / initial)
    foreach ($cards as $c) {
        if ($c->reference_type === 'mk_production') {
            continue;
        }
        $true = (float) $c->value_in / (float) $c->in_qty_small;
        if (isSaneCost($true, $highCost)) {
            return [$true, 'card_value_in:'.$c->reference_type.':'.$c->id];
        }
    }

    // 3) mk_production: utamakan deflate (bug × conversion), baru terima raw jika sangat wajar
    foreach ($cards as $c) {
        if ($c->reference_type !== 'mk_production') {
            continue;
        }
        $inQty = (float) $c->in_qty_small;
        if ($inQty <= 0) {
            continue;
        }
        if ($c->reference_id) {
            $prod = DB::table('mk_productions')->where('id', $c->reference_id)->first();
            if ($prod && (float) $prod->qty_jadi > 0) {
                $ratio = $inQty / (float) $prod->qty_jadi;
                if ($ratio > 1.01) {
                    $deflated = ((float) $c->value_in / $ratio) / $inQty;
                    if (isSaneCost($deflated, $highCost)) {
                        return [$deflated, 'deflate_mk:'.$c->reference_id.':x'.$ratio];
                    }
                }
            }
        }
        $true = (float) $c->value_in / $inQty;
        // Raw mk_production hanya diterima jika sudah rendah (hindari setengah-korup)
        if (isSaneCost($true, min(10000.0, $highCost))) {
            return [$true, 'card_value_in:mk_production:'.$c->id];
        }
    }

    // 4) Any warehouse sane stock
    $any = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('last_cost_small', '>', 0)
        ->where('last_cost_small', '<', $highCost)
        ->orderBy('warehouse_id')
        ->first();
    if ($any) {
        return [(float) $any->last_cost_small, 'other_wh:'.$any->warehouse_id];
    }

    // 5) Sane cost_per_medium on recent non-mk card
    foreach ($cards as $c) {
        if ($c->reference_type === 'mk_production') {
            continue;
        }
        $cpm = (float) $c->cost_per_medium;
        if ($smallConv > 1 && $cpm > 0 && $cpm < 5000000) {
            $cand = $cpm / $smallConv;
            if (isSaneCost($cand, $highCost)) {
                return [$cand, 'card_medium:'.$c->id];
            }
        }
    }

    return null;
}

function needsRepair($row, float $highCost, float $absurd, float $highValue): bool
{
    $qty = (float) $row->qty_small;
    $cs = (float) $row->last_cost_small;
    $cm = (float) $row->last_cost_medium;
    $val = (float) $row->value;

    if ($qty <= 0 && $val > 1) {
        return true;
    }
    if ($cs >= $absurd) {
        return true;
    }
    if ($val >= $highValue && $qty > 0) {
        $implied = $val / $qty;
        if ($implied >= $highCost) {
            return true;
        }
    }
    if ($cs >= $highCost && $cm > 0 && $cs > $cm * 2) {
        // cost_small >> cost_medium = corruption fingerprint (Main Store)
        return true;
    }
    if ($cs >= $highCost * 10) {
        return true;
    }
    return false;
}

echo $dryRun ? "=== DRY RUN mass repair ===\n" : "=== APPLYING mass repair ===\n";

$candidates = DB::table('food_inventory_stocks as s')
    ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'fii.item_id')
    ->whereIn('s.warehouse_id', $whIds)
    ->select(
        's.id as stock_id',
        's.warehouse_id',
        's.inventory_item_id',
        'i.name',
        'i.small_conversion_qty',
        'i.medium_conversion_qty',
        's.qty_small',
        's.last_cost_small',
        's.last_cost_medium',
        's.last_cost_large',
        's.value'
    )
    ->get()
    ->filter(fn ($r) => needsRepair($r, $HIGH_COST, $ABSURD_COST, $HIGH_VALUE));

echo "Candidates: ".$candidates->count()."\n\n";

$repaired = 0;
$orphansCleared = 0;
$skipped = 0;
$log = [];

DB::beginTransaction();
try {
    foreach ($candidates as $r) {
        $qty = (float) $r->qty_small;
        $smallConv = (float) ($r->small_conversion_qty ?: 1);
        $mediumConv = (float) ($r->medium_conversion_qty ?: 1);
        $wh = $whNames[$r->warehouse_id] ?? $r->warehouse_id;

        // Case A: orphan value only (qty<=0)
        if ($qty <= 0) {
            $msg = sprintf(
                "[ORPHAN] wh=%s %-30s value %s -> 0",
                $wh,
                mb_substr($r->name, 0, 30),
                $r->value
            );
            echo $msg."\n";
            $log[] = $msg;
            if (!$dryRun) {
                DB::table('food_inventory_stocks')->where('id', $r->stock_id)->update([
                    'value' => 0,
                    'updated_at' => now(),
                ]);
            }
            $orphansCleared++;
            continue;
        }

        // Case B: need new cost
        $resolved = resolveSaneCost((int) $r->inventory_item_id, (int) $r->warehouse_id, $smallConv, $ABSURD_COST, $HIGH_COST);

        // Fallback: deflate current stock cost by small_conversion (bug fingerprint)
        if (!$resolved && $smallConv > 1) {
            $cand = (float) $r->last_cost_small / $smallConv;
            if (isSaneCost($cand, $HIGH_COST)) {
                $resolved = [$cand, 'deflate_stock_conv:x'.$smallConv];
            } elseif (isSaneCost($cand / $smallConv, $HIGH_COST)) {
                // double infection
                $resolved = [$cand / $smallConv, 'deflate_stock_conv:x'.($smallConv * $smallConv)];
            }
        }

        // Fallback: sibling item name for portioned SKUs (e.g. Galbi 300gr → Galbi)
        if (!$resolved && preg_match('/^(.+?)\s+\d+\s*gr$/i', $r->name, $m)) {
            $parent = DB::table('food_inventory_items as fii')
                ->join('items as i', 'i.id', '=', 'fii.item_id')
                ->join('food_inventory_stocks as s', 's.inventory_item_id', '=', 'fii.id')
                ->where('i.name', $m[1])
                ->where('s.warehouse_id', $r->warehouse_id)
                ->where('s.last_cost_small', '>', 0)
                ->where('s.last_cost_small', '<', $HIGH_COST)
                ->select('s.last_cost_small')
                ->first();
            if ($parent) {
                $resolved = [(float) $parent->last_cost_small, 'sibling:'.$m[1]];
            }
        }

        // Fallback: if cost_medium looks like pack price and cost_small is absurd
        if (!$resolved) {
            $cm = (float) $r->last_cost_medium;
            if ($smallConv > 1 && $cm > 0 && $cm < 5000000) {
                $cand = $cm / $smallConv;
                if (isSaneCost($cand, $HIGH_COST) && (float) $r->last_cost_small > $cm) {
                    $resolved = [$cand, 'stock_medium_div_conv'];
                }
            }
        }

        // Fallback: use cost_medium as small when medium << small (purchase price stuck in medium)
        if (!$resolved) {
            $cm = (float) $r->last_cost_medium;
            $cs = (float) $r->last_cost_small;
            if ($cm > 0 && $cm < $HIGH_COST && $cs > $cm * 2) {
                $resolved = [$cm, 'stock_medium_as_small'];
            }
        }

        if (!$resolved) {
            $msg = sprintf(
                "[SKIP] wh=%s %-30s inv=%d cost_s=%s — no sane reference",
                $wh,
                mb_substr($r->name, 0, 30),
                $r->inventory_item_id,
                $r->last_cost_small
            );
            echo $msg."\n";
            $log[] = $msg;
            $skipped++;
            continue;
        }

        [$costSmall, $source] = $resolved;
        $costMedium = $costSmall * $smallConv;
        $costLarge = $costMedium * $mediumConv;
        $value = $qty * $costSmall;

        $msg = sprintf(
            "[FIX] wh=%s %-30s cost %s -> %s value %s -> %s (%s)",
            $wh,
            mb_substr($r->name, 0, 30),
            $r->last_cost_small,
            round($costSmall, 4),
            $r->value,
            round($value, 2),
            $source
        );
        echo $msg."\n";
        $log[] = $msg;

        if (!$dryRun) {
            DB::table('food_inventory_stocks')->where('id', $r->stock_id)->update([
                'last_cost_small' => $costSmall,
                'last_cost_medium' => $costMedium,
                'last_cost_large' => $costLarge,
                'value' => $value,
                'updated_at' => now(),
            ]);
            DB::table('food_inventory_cost_histories')->insert([
                'inventory_item_id' => $r->inventory_item_id,
                'warehouse_id' => $r->warehouse_id,
                'warehouse_division_id' => null,
                'date' => now()->toDateString(),
                'old_cost' => $r->last_cost_small,
                'new_cost' => $costSmall,
                'mac' => $costSmall,
                'type' => 'cost_repair',
                'reference_type' => 'mass_repair_mk_cost_bug',
                'reference_id' => null,
                'created_at' => now(),
            ]);
        }
        $repaired++;
    }

    if ($dryRun) {
        DB::rollBack();
        echo "\nDry-run rolled back.\n";
    } else {
        DB::commit();
        echo "\nCommitted.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}

echo "\n=== SUMMARY ===\n";
echo "repaired={$repaired} orphans_cleared={$orphansCleared} skipped={$skipped}\n";

if (!$dryRun) {
    echo "\n=== VERIFY remaining anomalies ===\n";
    foreach ($whIds as $whId) {
        $high = DB::table('food_inventory_stocks')
            ->where('warehouse_id', $whId)
            ->where('last_cost_small', '>', $ABSURD_COST)
            ->count();
        $orphan = DB::table('food_inventory_stocks')
            ->where('warehouse_id', $whId)
            ->where('qty_small', '<=', 0)
            ->where('value', '>', 1)
            ->count();
        $highVal = DB::table('food_inventory_stocks')
            ->where('warehouse_id', $whId)
            ->where('value', '>', $HIGH_VALUE)
            ->count();
        echo "  {$whNames[$whId]}: absurd_cost={$high} orphan_value={$orphan} high_value={$highVal}\n";
    }
}
