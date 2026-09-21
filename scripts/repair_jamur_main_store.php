<?php
/**
 * Repair Jamur Main Store (dan item sejenis) yang cost-nya masih tinggi
 * padahal GR/MK sibling sudah ~38/g.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);

DB::beginTransaction();
try {
    // Jamur inv 5049 Main Store wh1
    $row = DB::table('food_inventory_stocks as s')
        ->join('food_inventory_items as fii', 'fii.id', '=', 's.inventory_item_id')
        ->join('items as i', 'i.id', '=', 'fii.item_id')
        ->where('s.inventory_item_id', 5049)
        ->where('s.warehouse_id', 1)
        ->select('s.*', 'i.small_conversion_qty', 'i.medium_conversion_qty', 'i.name')
        ->first();

    if ($row && (float) $row->last_cost_small > 100) {
        $ref = 38.0;
        $gr = DB::table('food_inventory_cards')
            ->where('inventory_item_id', 5049)
            ->where('in_qty_small', '>', 0)
            ->where('value_in', '>', 0)
            ->where('reference_type', 'good_receive')
            ->orderByDesc('id')
            ->first();
        if ($gr) {
            $ref = (float) $gr->value_in / (float) $gr->in_qty_small;
        }
        $conv = (float) ($row->small_conversion_qty ?: 1);
        $med = (float) ($row->medium_conversion_qty ?: 1);
        $qty = (float) $row->qty_small;
        $value = $qty > 0 ? $qty * $ref : 0;
        echo "[FIX] Jamur Main Store {$row->last_cost_small} → {$ref}, value={$value}\n";
        if (!$dryRun) {
            DB::table('food_inventory_stocks')->where('id', $row->id)->update([
                'last_cost_small' => $ref,
                'last_cost_medium' => $ref * $conv,
                'last_cost_large' => $ref * $conv * $med,
                'value' => $value,
                'updated_at' => now(),
            ]);
            DB::table('food_inventory_cost_histories')->insert([
                'inventory_item_id' => 5049,
                'warehouse_id' => 1,
                'warehouse_division_id' => null,
                'date' => now()->toDateString(),
                'old_cost' => $row->last_cost_small,
                'new_cost' => $ref,
                'mac' => $ref,
                'type' => 'cost_repair',
                'reference_type' => 'mass_repair_pass3',
                'reference_id' => null,
                'created_at' => now(),
            ]);
        }
    } else {
        echo "[SKIP] Jamur Main Store already ok or missing\n";
    }

    // Generic: Main Store items where sibling MK warehouse has GR-like low cost and MS is >20x higher
    $suspects = DB::select(
        "SELECT s1.id, s1.inventory_item_id, s1.qty_small, s1.last_cost_small as ms_cost, s1.value,
                s2.last_cost_small as mk_cost, i.name, i.small_conversion_qty, i.medium_conversion_qty
         FROM food_inventory_stocks s1
         JOIN food_inventory_stocks s2
           ON s2.inventory_item_id = s1.inventory_item_id AND s2.warehouse_id = 2
         JOIN food_inventory_items fii ON fii.id = s1.inventory_item_id
         JOIN items i ON i.id = fii.item_id
         WHERE s1.warehouse_id = 1
           AND s1.last_cost_small > 500
           AND s2.last_cost_small > 0
           AND s2.last_cost_small < 500
           AND s1.last_cost_small > s2.last_cost_small * 20
         LIMIT 50"
    );
    foreach ($suspects as $s) {
        if ((int) $s->inventory_item_id === 5049) {
            continue; // already handled
        }
        $ref = (float) $s->mk_cost;
        $conv = (float) ($s->small_conversion_qty ?: 1);
        $med = (float) ($s->medium_conversion_qty ?: 1);
        $qty = (float) $s->qty_small;
        $value = $qty > 0 ? $qty * $ref : 0;
        echo "[FIX] {$s->name} MS {$s->ms_cost} → MK ref {$ref}\n";
        if (!$dryRun) {
            DB::table('food_inventory_stocks')->where('id', $s->id)->update([
                'last_cost_small' => $ref,
                'last_cost_medium' => $ref * $conv,
                'last_cost_large' => $ref * $conv * $med,
                'value' => $value,
                'updated_at' => now(),
            ]);
            DB::table('food_inventory_cost_histories')->insert([
                'inventory_item_id' => $s->inventory_item_id,
                'warehouse_id' => 1,
                'warehouse_division_id' => null,
                'date' => now()->toDateString(),
                'old_cost' => $s->ms_cost,
                'new_cost' => $ref,
                'mac' => $ref,
                'type' => 'cost_repair',
                'reference_type' => 'mass_repair_pass3',
                'reference_id' => null,
                'created_at' => now(),
            ]);
        }
    }

    if ($dryRun) {
        DB::rollBack();
        echo "Dry-run rolled back\n";
    } else {
        DB::commit();
        echo "Committed\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERR '.$e->getMessage()."\n";
    exit(1);
}
