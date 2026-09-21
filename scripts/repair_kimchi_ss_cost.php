<?php
/**
 * Repair corrupted MK production stock costs for Kimchi + Simple Syrup.
 * Root cause was MKProductionController dividing BOM cost by qty_jadi (Pack)
 * instead of qty_small (Gram/ml), inflating cost by unit conversion.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);

function repairStock(int $invId, int $whId, float $costSmall, string $label, bool $dryRun): void
{
    $item = DB::table('food_inventory_items as fii')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->where('fii.id', $invId)
        ->select('i.name', 'i.small_conversion_qty', 'i.medium_conversion_qty')
        ->first();
    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();
    if (!$stock) {
        echo "[SKIP] {$label} inv={$invId} wh={$whId} — no stock row\n";
        return;
    }

    $smallConv = (float) ($item->small_conversion_qty ?: 1);
    $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
    $qty = (float) $stock->qty_small;
    $costMedium = $costSmall * $smallConv;
    $costLarge = $costMedium * $mediumConv;
    $value = $qty > 0 ? ($qty * $costSmall) : 0;

    echo "[{$label}] {$item->name} wh={$whId}\n";
    echo "  BEFORE qty={$stock->qty_small} cost_s={$stock->last_cost_small} cost_m={$stock->last_cost_medium} value={$stock->value}\n";
    echo "  AFTER  qty={$qty} cost_s={$costSmall} cost_m={$costMedium} value={$value}\n";

    if ($dryRun) {
        echo "  (dry-run, not saved)\n";
        return;
    }

    DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->update([
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
        'old_cost' => $stock->last_cost_small,
        'new_cost' => $costSmall,
        'mac' => $costSmall,
        'type' => 'cost_repair',
        'reference_type' => 'manual_repair_mk_cost_bug',
        'reference_id' => null,
        'created_at' => now(),
    ]);

    echo "  SAVED\n";
}

echo $dryRun ? "=== DRY RUN ===\n" : "=== APPLYING REPAIR ===\n";

DB::beginTransaction();
try {
    // Kimchi MK2 Cold Kitchen — pakai cost Good Receive 21 Sep 2026 (48.7/gram)
    repairStock(5110, 5, 48.7, 'Kimchi MK2', $dryRun);

    // Kimchi Main Store — qty 0 tapi value yatim ~25jt (jangan ubah cost, hanya bersihkan value)
    $ms = DB::table('food_inventory_stocks')->where('inventory_item_id', 5110)->where('warehouse_id', 1)->first();
    if ($ms && (float) $ms->qty_small <= 0 && (float) $ms->value != 0) {
        echo "[Kimchi MainStore] orphan value {$ms->value} -> 0\n";
        if (!$dryRun) {
            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', 5110)
                ->where('warehouse_id', 1)
                ->update(['value' => 0, 'updated_at' => now()]);
            echo "  SAVED\n";
        } else {
            echo "  (dry-run, not saved)\n";
        }
    }

    // Simple Syrup — last sane MK production cost 16 Jul 2026
    $ssSane = 109.5849;
    repairStock(5400, 5, $ssSane, 'Simple Syrup MK2', $dryRun);
    repairStock(5400, 2, $ssSane, 'Simple Syrup MK1', $dryRun);

    if ($dryRun) {
        DB::rollBack();
        echo "\nDry-run complete (rolled back).\n";
    } else {
        DB::commit();
        echo "\nRepair committed.\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}

// Verify
echo "\n=== VERIFY ===\n";
foreach ([[5110, 5], [5110, 1], [5400, 5], [5400, 2]] as [$inv, $wh]) {
    $s = DB::table('food_inventory_stocks')->where('inventory_item_id', $inv)->where('warehouse_id', $wh)->first();
    if ($s) {
        echo "inv={$inv} wh={$wh} qty={$s->qty_small} cost_s={$s->last_cost_small} cost_m={$s->last_cost_medium} value={$s->value}\n";
    }
}

// Sanity: simulate Kimchi production cost with repaired SS
echo "\n=== Simulated Kimchi 1 pack BOM cost (after repair) ===\n";
$bom = [
    [5110, 1000], // Kimchi gram
    [5400, 42],   // SS ml
    [5088, 25],   // Kecap
    [5579, 3],    // Vetcin
    [5582, 12],   // Vinegar
];
$total = 0;
foreach ($bom as [$inv, $qty]) {
    $s = DB::table('food_inventory_stocks')->where('inventory_item_id', $inv)->where('warehouse_id', 5)->first();
    $c = (float) ($s->last_cost_small ?? 0);
    $line = $qty * $c;
    $total += $line;
    echo "  inv={$inv} qty={$qty} cps={$c} line={$line}\n";
}
echo "  total_bom={$total}\n";
echo "  cost_per_gram=".($total / 1000)."\n";
echo "  cost_per_pack={$total}\n";
echo "  fits decimal(18,4) medium=".(($total <= 99999999999999.9999) ? 'YES' : 'NO')."\n";
