<?php
/**
 * Pass 3 — audit & repair residual high costs after MK Production bug + pass1/2.
 *
 * Fokus:
 * - Bahan baku MK dengan cost GR jauh lebih rendah (mis. Whole Beef Oxtail)
 * - FG MK yang terinfeksi (Beef Oxtail, Sauce, Butter, Coating, dll.)
 * - Value yatim (value != qty × cost)
 * - Cost zero-qty absurd di MK warehouses
 *
 * Usage:
 *   php scripts/repair_mass_inventory_costs_pass3.php --dry-run
 *   php scripts/repair_mass_inventory_costs_pass3.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$fixed = 0;

function setCostPass3(
    int $stockId,
    int $invId,
    int $whId,
    float $qty,
    float $costSmall,
    float $smallConv,
    float $mediumConv,
    float $oldCost,
    string $label,
    bool $dryRun,
    string &$status
): void {
    global $fixed;
    $costSmall = max(0.0, $costSmall);
    $smallConv = $smallConv > 0 ? $smallConv : 1.0;
    $mediumConv = $mediumConv > 0 ? $mediumConv : 1.0;
    $costMedium = $costSmall * $smallConv;
    $costLarge = $costMedium * $mediumConv;
    $value = $qty > 0 ? $qty * $costSmall : 0.0;
    $status = sprintf('[FIX3] %s old=%s → cost=%s value=%s', $label, round($oldCost, 4), round($costSmall, 4), round($value, 2));
    echo $status . PHP_EOL;
    $fixed++;
    if ($dryRun) {
        return;
    }
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
        'reference_type' => 'mass_repair_pass3',
        'reference_id' => null,
        'created_at' => now(),
    ]);
}

function referenceCostFromCards(int $invId, ?int $preferWh = null): ?float
{
    $q = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('in_qty_small', '>', 0)
        ->where('value_in', '>', 0)
        ->whereIn('reference_type', ['good_receive', 'initial_balance', 'retail_warehouse_food'])
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->limit(30);

    $rows = $q->get(['warehouse_id', 'in_qty_small', 'value_in', 'cost_per_small']);
    $candidates = [];
    foreach ($rows as $c) {
        $true = (float) $c->value_in / (float) $c->in_qty_small;
        if ($true > 0 && $true < 500000 && is_finite($true)) {
            $candidates[] = [
                'cost' => $true,
                'prefer' => $preferWh && (int) $c->warehouse_id === $preferWh ? 0 : 1,
            ];
        }
    }
    if (empty($candidates)) {
        return null;
    }
    usort($candidates, fn ($a, $b) => $a['prefer'] <=> $b['prefer'] ?: $a['cost'] <=> $b['cost']);
    return $candidates[0]['cost'];
}

function referenceCostFromSibling(int $invId, int $excludeWh, float $maxCost = 200000): ?float
{
    $row = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', '!=', $excludeWh)
        ->where('last_cost_small', '>', 0)
        ->where('last_cost_small', '<', $maxCost)
        ->orderBy('last_cost_small')
        ->first();
    return $row ? (float) $row->last_cost_small : null;
}

function deflateByConversion(float $cost, float $conv, float $maxTarget = 100000, int $maxLoops = 4): ?float
{
    if ($cost <= 0) {
        return null;
    }
    $d = $cost;
    $loops = 0;
    while ($d > $maxTarget && $conv > 1 && $loops < $maxLoops) {
        $d /= $conv;
        $loops++;
    }
    return ($d > 0 && $d < $maxTarget) ? $d : null;
}

DB::beginTransaction();
try {
    // ------------------------------------------------------------------
    // 1) Explicit known infections
    // ------------------------------------------------------------------
    $explicit = [
        // Whole Beef Oxtail MK1 — GR true ~150/g
        ['inv' => 5604, 'wh' => 2, 'max' => 500, ' Prefer' => 'gr'],
        // Beef Oxtail FG MK1 — rebuild from sibling MK2 (~75k) after material fix
        ['inv' => 4622, 'wh' => 2, 'max' => 200000, 'prefer' => 'sibling'],
        // Beef Oxtail FG MK2 — keep if <100k else sibling/GR
        ['inv' => 4622, 'wh' => 5, 'max' => 200000, 'prefer' => 'keep_or_sibling'],
    ];

    // Whole Beef Oxtail MK1
    $wbo = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->where('s.inventory_item_id', 5604)
        ->where('s.warehouse_id', 2)
        ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->first();
    if ($wbo && (float) $wbo->last_cost_small > 500) {
        $ref = referenceCostFromCards(5604, 2) ?? referenceCostFromSibling(5604, 2, 500) ?? 150.0;
        $label = '';
        setCostPass3(
            (int) $wbo->id,
            5604,
            2,
            (float) $wbo->qty_small,
            $ref,
            (float) ($wbo->small_conversion_qty ?: 1),
            (float) ($wbo->medium_conversion_qty ?: 1),
            (float) $wbo->last_cost_small,
            "Whole Beef Oxtail MK1 (GR ref {$ref})",
            $dryRun,
            $label
        );
    }

    // Beef Oxtail FG
    foreach ([2, 5] as $wh) {
        $bo = DB::table('food_inventory_stocks as s')
            ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
            ->join('items as i', 'i.id', '=', 'fii.item_id')
            ->where('s.inventory_item_id', 4622)
            ->where('s.warehouse_id', $wh)
            ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
            ->first();
        if (!$bo) {
            continue;
        }
        $cs = (float) $bo->last_cost_small;
        if ($cs <= 150000 && $wh === 5) {
            continue; // MK2 ~75k looks like a portion cost, keep
        }
        if ($cs <= 150000 && $wh === 2) {
            continue;
        }
        $ref = referenceCostFromSibling(4622, $wh, 150000);
        if ($ref === null || $ref <= 0) {
            // Estimate from fixed material: ~8000g * 150 + spices ≈ 1.4M / 20 portion ≈ 70k
            $ref = 70000.0;
        }
        $label = '';
        setCostPass3(
            (int) $bo->id,
            4622,
            $wh,
            (float) $bo->qty_small,
            $ref,
            (float) ($bo->small_conversion_qty ?: 1),
            (float) ($bo->medium_conversion_qty ?: 1),
            $cs,
            "Beef Oxtail wh{$wh} (ref {$ref})",
            $dryRun,
            $label
        );
    }

    // ------------------------------------------------------------------
    // 2) MK FG / materials: cost suspiciously high vs conversion or GR
    // ------------------------------------------------------------------
    $suspects = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->whereIn('s.warehouse_id', [2, 5])
        ->where(function ($q) {
            $q->where('s.last_cost_small', '>', 20000)
                ->orWhere(function ($q2) {
                    $q2->where('s.value', '>', 100000000)
                        ->where('s.qty_small', '>', 0);
                });
        })
        ->select('s.*', 'i.name', 'i.sku', 'i.small_conversion_qty', 'i.medium_conversion_qty', 'i.composition_type')
        ->get();

    foreach ($suspects as $r) {
        // Skip already handled
        if (in_array((int) $r->inventory_item_id, [5604, 4622], true)) {
            continue;
        }
        $cs = (float) $r->last_cost_small;
        $qty = (float) $r->qty_small;
        $conv = (float) ($r->small_conversion_qty ?: 1);
        $medConv = (float) ($r->medium_conversion_qty ?: 1);
        $cm = (float) $r->last_cost_medium;
        $implied = $qty > 0 ? ((float) $r->value / $qty) : null;

        $ref = referenceCostFromCards((int) $r->inventory_item_id, (int) $r->warehouse_id);
        $sibling = referenceCostFromSibling((int) $r->inventory_item_id, (int) $r->warehouse_id, 100000);

        $newCost = null;
        $reason = null;

        // A) GR reference much lower than current
        if ($ref !== null && $cs > 0 && $ref < $cs * 0.5 && $ref < 100000 && ($cs > 1000 || ($implied && $implied > 50000))) {
            $newCost = $ref;
            $reason = "GR ref {$ref}";
        }

        // B) medium looks like true pack cost, small is inflated (classic pack-as-small)
        if ($newCost === null && $conv > 1 && $cm > 0 && $cm < 500000) {
            $fromMed = $cm / $conv;
            if ($fromMed > 0 && $fromMed < $cs * 0.5 && $cs > 5000) {
                $newCost = $fromMed;
                $reason = "medium/conv {$fromMed}";
            }
        }

        // C) Deflate by conversion when FG cost looks like pack-level left in small
        if ($newCost === null && $conv > 1 && $cs > 10000) {
            $deflated = deflateByConversion($cs, $conv, $conv > 50 ? 5000 : 50000);
            if ($deflated !== null && $deflated < $cs * 0.5) {
                // Prefer if sibling/GR near deflated
                if ($ref && abs($ref - $deflated) / max($ref, 1) < 0.5) {
                    $newCost = $ref;
                    $reason = "deflate≈GR {$ref}";
                } elseif ($sibling && abs($sibling - $deflated) / max($sibling, 1) < 0.5) {
                    $newCost = $sibling;
                    $reason = "deflate≈sibling {$sibling}";
                } elseif ($cs > 50000) {
                    $newCost = $deflated;
                    $reason = "deflate/conv {$deflated}";
                }
            }
        }

        // D) Sibling warehouse has sane cost
        if ($newCost === null && $sibling !== null && $cs > 50000 && $sibling < $cs * 0.5) {
            $newCost = $sibling;
            $reason = "sibling {$sibling}";
        }

        // E) Orphan value only — realign value to cost without changing cost
        if ($newCost === null && $qty > 0 && $implied !== null && $cs > 0) {
            $diffRatio = abs($implied - $cs) / max($cs, 1);
            if ($diffRatio > 0.2 && (float) $r->value > 1000000) {
                $label = '';
                setCostPass3(
                    (int) $r->id,
                    (int) $r->inventory_item_id,
                    (int) $r->warehouse_id,
                    $qty,
                    $cs,
                    $conv,
                    $medConv,
                    $cs,
                    "{$r->name} wh{$r->warehouse_id} realign value (implied was {$implied})",
                    $dryRun,
                    $label
                );
                continue;
            }
        }

        if ($newCost !== null && abs($newCost - $cs) > 0.01) {
            $label = '';
            setCostPass3(
                (int) $r->id,
                (int) $r->inventory_item_id,
                (int) $r->warehouse_id,
                $qty,
                $newCost,
                $conv,
                $medConv,
                $cs,
                "{$r->name} wh{$r->warehouse_id} ({$reason})",
                $dryRun,
                $label
            );
        }
    }

    // ------------------------------------------------------------------
    // 3) Zero-qty MK stocks with absurd cost (>50k) — clear poison
    // ------------------------------------------------------------------
    $zeroPoison = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->whereIn('s.warehouse_id', [2, 5])
        ->where('s.qty_small', '<=', 0)
        ->where('s.last_cost_small', '>', 50000)
        ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->get();

    foreach ($zeroPoison as $r) {
        $ref = referenceCostFromCards((int) $r->inventory_item_id, (int) $r->warehouse_id)
            ?? referenceCostFromSibling((int) $r->inventory_item_id, (int) $r->warehouse_id, 50000);
        // Jangan pertahankan racun — zero-qty dengan cost absurd → 0 jika ref masih tinggi
        if ($ref === null || $ref > 50000) {
            $ref = 0.0;
        }
        $label = '';
        setCostPass3(
            (int) $r->id,
            (int) $r->inventory_item_id,
            (int) $r->warehouse_id,
            0,
            $ref,
            (float) ($r->small_conversion_qty ?: 1),
            (float) ($r->medium_conversion_qty ?: 1),
            (float) $r->last_cost_small,
            "zero-qty {$r->name} wh{$r->warehouse_id} → {$ref}",
            $dryRun,
            $label
        );
    }

    // ------------------------------------------------------------------
    // 4) Main Store orphan value only (qty>0, value >> qty*cost) for known infected names optional
    // Skip legitimate high-value bulk (Fryall, tenderloin) — only when cost itself is absurd (>500k)
    // ------------------------------------------------------------------
    $msAbsurd = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->where('s.warehouse_id', 1)
        ->where('s.qty_small', '>', 0)
        ->where('s.last_cost_small', '>', 500000)
        ->select('s.*', 'i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->get();

    foreach ($msAbsurd as $r) {
        $ref = referenceCostFromCards((int) $r->inventory_item_id, 1);
        if ($ref === null || $ref >= (float) $r->last_cost_small * 0.5) {
            echo "[SKIP-MS] {$r->name} cost={$r->last_cost_small} (no safer GR ref)\n";
            continue;
        }
        $label = '';
        setCostPass3(
            (int) $r->id,
            (int) $r->inventory_item_id,
            1,
            (float) $r->qty_small,
            $ref,
            (float) ($r->small_conversion_qty ?: 1),
            (float) ($r->medium_conversion_qty ?: 1),
            (float) $r->last_cost_small,
            "MainStore {$r->name} (GR {$ref})",
            $dryRun,
            $label
        );
    }

    if ($dryRun) {
        DB::rollBack();
        echo "\nDry-run rolled back. Fixed candidates: {$fixed}\n";
    } else {
        DB::commit();
        echo "\nPass3 committed. Fixed: {$fixed}\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERR ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "\n=== Remaining MK cost>20k or value>100M ===\n";
$left = DB::table('food_inventory_stocks as s')
    ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
    ->join('items as i', 'i.id', '=', 'fii.item_id')
    ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
    ->whereIn('s.warehouse_id', [2, 5])
    ->where(function ($q) {
        $q->where('s.last_cost_small', '>', 20000)
            ->orWhere('s.value', '>', 100000000);
    })
    ->orderByDesc('s.last_cost_small')
    ->get(['i.name', 'i.sku', 'w.name as wh', 's.qty_small', 's.last_cost_small', 's.value']);

foreach ($left as $r) {
    echo sprintf(
        "%s | %s | qty=%s cost=%s value=%s\n",
        $r->wh,
        $r->name,
        $r->qty_small,
        $r->last_cost_small,
        $r->value
    );
}
echo 'LEFT=' . $left->count() . PHP_EOL;
