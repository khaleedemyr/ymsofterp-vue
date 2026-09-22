<?php
/**
 * Rollback mac_correction yang baru dibuat repair script (hari ini, outlet 20).
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$since = now()->subHours(2);

$hist = DB::table('outlet_food_inventory_cost_histories')
    ->where('id_outlet', 20)
    ->where('reference_type', 'mac_correction')
    ->where('type', 'mac_correction')
    ->where('created_at', '>=', $since)
    ->whereIn('id', [
        962860, 962861, 962867, 962868, 962869, 962870, 962876, 962886,
        962888, 962889, 962890, 962891, 962892, 962893, 962894, 962895, 962896,
    ])
    ->orderBy('id')
    ->get();

echo 'Rollback candidates: '.$hist->count().' dry='.($dryRun ? '1' : '0')."\n";

foreach ($hist as $h) {
    $stock = DB::table('outlet_food_inventory_stocks')
        ->where('id_outlet', 20)
        ->where('warehouse_outlet_id', $h->warehouse_outlet_id)
        ->where('inventory_item_id', $h->inventory_item_id)
        ->first();
    if (! $stock) {
        echo "missing stock inv={$h->inventory_item_id} wh={$h->warehouse_outlet_id}\n";
        continue;
    }

    $old = (float) $h->old_cost;
    $qty = (float) $stock->qty_small;
    $value = $qty > 0.0001 ? $qty * $old : 0.0;

    // Restore medium/large proportionally from old small if possible
    $prevMed = (float) $stock->last_cost_medium;
    $prevSmall = (float) $stock->last_cost_small;
    $ratioMed = ($prevSmall > 0) ? ($prevMed / $prevSmall) : 1.0;
    $prevLarge = (float) $stock->last_cost_large;
    $ratioLarge = ($prevSmall > 0) ? ($prevLarge / $prevSmall) : 1.0;

    echo sprintf(
        "[%s] inv=%d wh=%d cost %s→%s value %s→%s\n",
        $dryRun ? 'DRY' : 'REVERT',
        $h->inventory_item_id,
        $h->warehouse_outlet_id,
        $stock->last_cost_small,
        $old,
        $stock->value,
        $value
    );

    if ($dryRun) {
        continue;
    }

    DB::table('outlet_food_inventory_stocks')->where('id', $stock->id)->update([
        'last_cost_small' => $old,
        'last_cost_medium' => $old * max($ratioMed, 1e-9),
        'last_cost_large' => $old * max($ratioLarge, 1e-9),
        'value' => $value,
        'updated_at' => now(),
    ]);

    DB::table('outlet_food_inventory_cost_histories')->where('id', $h->id)->delete();
}

echo "Done.\n";
