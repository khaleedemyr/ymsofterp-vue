<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);

function setCost($stockId, $invId, $whId, $qty, $costSmall, $smallConv, $mediumConv, $oldCost, $label, $dryRun) {
    $costMedium = $costSmall * $smallConv;
    $costLarge = $costMedium * $mediumConv;
    $value = $qty > 0 ? $qty * $costSmall : 0;
    echo "[FIX2] {$label} cost={$costSmall} value={$value}\n";
    if ($dryRun) return;
    DB::table('food_inventory_stocks')->where('id', $stockId)->update([
        'last_cost_small' => $costSmall,
        'last_cost_medium' => $costMedium,
        'last_cost_large' => $costLarge,
        'value' => $value,
        'updated_at' => now(),
    ]);
    DB::table('food_inventory_cost_histories')->insert([
        'inventory_item_id' => $invId,
        'warehouse_id' => $whId,
        'warehouse_division_id' => null,
        'date' => now()->toDateString(),
        'old_cost' => $oldCost,
        'new_cost' => $costSmall,
        'mac' => $costSmall,
        'type' => 'cost_repair',
        'reference_type' => 'mass_repair_pass2',
        'reference_id' => null,
        'created_at' => now(),
    ]);
}

DB::beginTransaction();
try {
    // 1) Zero-qty: reset absurd costs using medium-as-small or main store / leave low
    $orphans = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->whereIn('s.warehouse_id', [1, 2, 5])
        ->where('s.qty_small', '<=', 0)
        ->where('s.last_cost_small', '>', 100000)
        ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->get();

    foreach ($orphans as $r) {
        $smallConv = (float) ($r->small_conversion_qty ?: 1);
        $mediumConv = (float) ($r->medium_conversion_qty ?: 1);
        $cm = (float) $r->last_cost_medium;
        $cost = null;
        // Prefer main store
        $ms = DB::table('food_inventory_stocks')->where('inventory_item_id', $r->inventory_item_id)->where('warehouse_id', 1)->first();
        if ($ms && (float)$ms->last_cost_small > 0 && (float)$ms->last_cost_small < 100000) {
            $cost = (float) $ms->last_cost_small;
        } elseif ($cm > 0 && $cm < 100000) {
            $cost = $cm;
        } elseif ($smallConv > 1 && $cm > 0 && $cm / $smallConv < 100000) {
            $cost = $cm / $smallConv;
        } else {
            $cost = 0; // qty 0 — clear cost to avoid poisoning next IN readers that use last_cost
        }
        setCost($r->id, $r->inventory_item_id, $r->warehouse_id, 0, $cost, $smallConv, $mediumConv, $r->last_cost_small, "orphan {$r->name} wh{$r->warehouse_id}", $dryRun);
    }

    // 2) Plastik Wrap Main Store — re-apply from GR
    $pw = DB::table('food_inventory_stocks')->where('inventory_item_id', 5293)->where('warehouse_id', 1)->first();
    if ($pw && (float)$pw->last_cost_small > 200000) {
        $item = DB::table('food_inventory_items as fii')->join('items as i', 'i.id', '=', 'fii.item_id')->where('fii.id', 5293)->first();
        setCost($pw->id, 5293, 1, (float)$pw->qty_small, 175000, (float)($item->small_conversion_qty ?: 1), (float)($item->medium_conversion_qty ?: 1), $pw->last_cost_small, 'Plastik Wrap MS', $dryRun);
    }

    // 3) Chicken Gomatare MK2 — multi-deflate by conv until sane, or use 0-qty reset from initial
    $cg = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->where('s.inventory_item_id', 5746)
        ->where('s.warehouse_id', 5)
        ->select('s.*', 'i.small_conversion_qty', 'i.medium_conversion_qty', 'i.name')
        ->first();
    if ($cg && (float)$cg->last_cost_small > 100000) {
        $conv = (float) ($cg->small_conversion_qty ?: 1);
        // From latest prod card: deflated once still ~68M; materials infected.
        // Use Marinate Gomatare repaired cost as proxy floor from MK2 if available
        $mar = DB::table('food_inventory_stocks')->where('inventory_item_id', 5151)->where('warehouse_id', 5)->first();
        // Find gomatare-related — inv for Marinate Gomatare
        $marRow = DB::table('food_inventory_items as fii')
            ->join('items as i', 'i.id', '=', 'fii.item_id')
            ->join('food_inventory_stocks as s', 's.inventory_item_id', '=', 'fii.id')
            ->where('i.name', 'like', '%Gomatare%')
            ->where('s.warehouse_id', 5)
            ->where('s.last_cost_small', '>', 0)
            ->where('s.last_cost_small', '<', 100000)
            ->select('s.last_cost_small', 'i.name')
            ->first();
        $cost = $marRow ? (float) $marRow->last_cost_small : 100.0;
        // If still have initial balance anywhere
        $ib = DB::table('food_inventory_cards')
            ->where('inventory_item_id', 5746)
            ->where('reference_type', 'initial_balance')
            ->where('cost_per_small', '>', 0)
            ->where('cost_per_small', '<', 100000)
            ->orderByDesc('id')
            ->first();
        if ($ib) {
            $cost = (float) $ib->cost_per_small;
        }
        setCost($cg->id, 5746, 5, (float)$cg->qty_small, $cost, $conv, (float)($cg->medium_conversion_qty ?: 1), $cg->last_cost_small, "Chicken Gomatare (proxy {$cost})", $dryRun);
    }

    // 4) Re-deflate sauces still with high value: if cost from deflate_mk still leaves value>500M and cost>20000, try /conv again
    $sauces = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->whereIn('s.warehouse_id', [2, 5])
        ->where('s.qty_small', '>', 0)
        ->where('s.value', '>', 500000000)
        ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->get();

    foreach ($sauces as $r) {
        $conv = (float) ($r->small_conversion_qty ?: 1);
        $cs = (float) $r->last_cost_small;
        // Prefer initial_balance true cost
        $ib = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $r->inventory_item_id)
            ->where('warehouse_id', $r->warehouse_id)
            ->whereIn('reference_type', ['initial_balance', 'good_receive', 'warehouse_transfer'])
            ->where('in_qty_small', '>', 0)
            ->where('value_in', '>', 0)
            ->orderByDesc('date')
            ->limit(20)
            ->get();
        $cost = null;
        foreach ($ib as $c) {
            $true = (float)$c->value_in / (float)$c->in_qty_small;
            if ($true > 0 && $true < 100000) {
                $cost = $true;
                break;
            }
        }
        if ($cost === null && $conv > 1 && $cs / $conv < 100000 && $cs / $conv > 0) {
            $cost = $cs / $conv;
        }
        if ($cost === null) {
            // recursive deflate latest mk prod
            $card = DB::table('food_inventory_cards')
                ->where('inventory_item_id', $r->inventory_item_id)
                ->where('warehouse_id', $r->warehouse_id)
                ->where('reference_type', 'mk_production')
                ->where('in_qty_small', '>', 0)
                ->whereNotNull('reference_id')
                ->orderByDesc('id')
                ->first();
            if ($card) {
                $prod = DB::table('mk_productions')->where('id', $card->reference_id)->first();
                if ($prod && $prod->qty_jadi > 0) {
                    $ratio = (float)$card->in_qty_small / (float)$prod->qty_jadi;
                    $d = ((float)$card->value_in / max($ratio, 1)) / (float)$card->in_qty_small;
                    // if still high, divide by conv (second layer infection from materials)
                    $guard = 0;
                    while ($d > 100000 && $conv > 1 && $guard < 3) {
                        $d /= $conv;
                        $guard++;
                    }
                    if ($d > 0 && $d < 100000) {
                        $cost = $d;
                    }
                }
            }
        }
        if ($cost !== null && abs($cost - $cs) > 0.01) {
            setCost($r->id, $r->inventory_item_id, $r->warehouse_id, (float)$r->qty_small, $cost, $conv, (float)($r->medium_conversion_qty ?: 1), $cs, $r->name, $dryRun);
        } else {
            echo "[KEEP] {$r->name} cost={$cs} value={$r->value}\n";
        }
    }

    if ($dryRun) {
        DB::rollBack();
        echo "Dry-run rolled back\n";
    } else {
        DB::commit();
        echo "Pass2 committed\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERR ".$e->getMessage()."\n";
    exit(1);
}

echo "\nRemaining absurd/high:\n";
foreach (DB::table('food_inventory_stocks as s')
    ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'fii.item_id')
    ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
    ->whereIn('s.warehouse_id', [1, 2, 5])
    ->where(function ($q) {
        $q->where('s.last_cost_small', '>', 1000000)
            ->orWhere(function ($q2) {
                $q2->where('s.value', '>', 500000000)->where('s.qty_small', '>', 0);
            });
    })
    ->select('w.name as wh', 'i.name', 's.qty_small', 's.last_cost_small', 's.value')
    ->orderByDesc('s.last_cost_small')
    ->get() as $r) {
    echo "  {$r->wh} {$r->name} qty={$r->qty_small} cost={$r->last_cost_small} value={$r->value}\n";
}
