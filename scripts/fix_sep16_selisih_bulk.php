<?php

/**
 * Bulk fix Main Store stock + card chain for DO2609160037 race (Sep 16 2026).
 * lost qty = end drift (stock too high vs correct out/in chain).
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$whId = 1;
$fromDate = '2026-09-16';
$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$dryRun = in_array('--dry-run', $argv ?? [], true);

$targets = [
    // name, item_id, inv_id, lost_small (gram/pcs as stored in qty_small)
    ['Beef Rib Eye Blue Label', 52993, 4631],
    ['Beef Sirloin Aussie 150gr', 52976, 4649],
    ['Beef Sirloin Aussie 250gr', 52977, 4651],
    ['Beef Sirloin Blue Label', 52992, 4652],
    ['Beef Stroganoff', 52906, 4663],
    ['Beef Tenderloin Aussie 150gr', 52984, 4671],
    ['Beef Tenderloin Blue Label', 52994, 4674], // special created_at reconcile
    ['Beef Tenderloin Meltique 250gr', 53015, 4676],
    ['Butter Salted', 53265, 4784],
    ['Chicken Breast', 53043, 4820],
    ['Chicken Leg Skin', 53044, 4827],
    ['Cooking Cream', 53271, 4860],
    ['Cooking Cream Gourmet', 53272, 4861], // user marked clear but still drifted
    ['Fresh Milk Greenfields', 53274, 4958],
    ['Ikan Cumi', 53336, 5025],
    ['Ikan Dory', 53335, 5027],
    ['Ikan Salmon Fillet', 53351, 5034],
    ['Ikan Salmon Steak', 53352, 4537],
    ['Potato Straight Cut', 53224, 5300],
    ['Sosis Bratwurst', 53229, 5413],
];

function computeDrift(int $invId, int $whId, string $fromDate): array
{
    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();

    // Prefer created_at chain from day before fromDate for accuracy with backdated cards
    $boot = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('created_at', '<', $fromDate.' 00:00:00')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();

    // Fallback: date-based prev
    if (! $boot) {
        $boot = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', $whId)
            ->whereDate('date', '<', $fromDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    $runningS = $boot ? (float) $boot->saldo_qty_small : 0.0;
    $runningM = $boot ? (float) $boot->saldo_qty_medium : 0.0;
    $runningL = $boot ? (float) $boot->saldo_qty_large : 0.0;

    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('created_at', '>=', $fromDate.' 00:00:00')
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    // If no cards by created_at filter (edge), use date filter
    if ($cards->isEmpty()) {
        $cards = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', $whId)
            ->whereDate('date', '>=', $fromDate)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $boot = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', $whId)
            ->whereDate('date', '<', $fromDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
        $runningS = $boot ? (float) $boot->saldo_qty_small : 0.0;
        $runningM = $boot ? (float) $boot->saldo_qty_medium : 0.0;
        $runningL = $boot ? (float) $boot->saldo_qty_large : 0.0;
    }

    foreach ($cards as $c) {
        $runningS += (float) $c->in_qty_small - (float) $c->out_qty_small;
        $runningM += (float) $c->in_qty_medium - (float) $c->out_qty_medium;
        $runningL += (float) $c->in_qty_large - (float) $c->out_qty_large;
    }

    $driftS = (float) $stock->qty_small - $runningS;
    $driftM = (float) $stock->qty_medium - $runningM;
    $driftL = (float) $stock->qty_large - $runningL;

    return compact('stock', 'driftS', 'driftM', 'driftL', 'runningS', 'runningM', 'runningL', 'boot', 'cards');
}

function reconcileByCreatedAt(int $invId, int $whId, string $fromDate, object $stock, bool $dryRun): void
{
    $boot = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('created_at', '<', $fromDate.' 00:00:00')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();

    $runningS = $boot ? (float) $boot->saldo_qty_small : 0.0;
    $runningM = $boot ? (float) $boot->saldo_qty_medium : 0.0;
    $runningL = $boot ? (float) $boot->saldo_qty_large : 0.0;
    $mac = (float) ($stock->last_cost_small ?: 0);

    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('created_at', '>=', $fromDate.' 00:00:00')
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    echo "  reconcile created_at from {$fromDate}, boot=".($boot->id ?? 'none')." saldo={$runningS}, cards={$cards->count()}\n";

    foreach ($cards as $c) {
        $runningS += (float) $c->in_qty_small - (float) $c->out_qty_small;
        $runningM += (float) $c->in_qty_medium - (float) $c->out_qty_medium;
        $runningL += (float) $c->in_qty_large - (float) $c->out_qty_large;

        if ($dryRun) {
            continue;
        }

        DB::table('food_inventory_cards')->where('id', $c->id)->update([
            'saldo_qty_small' => $runningS,
            'saldo_qty_medium' => $runningM,
            'saldo_qty_large' => $runningL,
            'saldo_value' => max(0, $runningS * $mac),
            'updated_at' => now(),
        ]);
    }

    // Force last card = stock
    $last = $cards->last();
    if ($last && ! $dryRun) {
        DB::table('food_inventory_cards')->where('id', $last->id)->update([
            'saldo_qty_small' => (float) $stock->qty_small,
            'saldo_qty_medium' => (float) $stock->qty_medium,
            'saldo_qty_large' => (float) $stock->qty_large,
            'saldo_value' => (float) $stock->value,
            'updated_at' => now(),
        ]);
    }
}

echo $dryRun ? "===== DRY RUN =====\n" : "===== APPLY FIX =====\n";

$results = [];
foreach ($targets as [$name, $itemId, $invId]) {
    echo "\n## {$name} (item={$itemId} inv={$invId})\n";
    $info = computeDrift($invId, $whId, $fromDate);
    $driftS = $info['driftS'];
    $driftM = $info['driftM'];
    $driftL = $info['driftL'];
    $stock = $info['stock'];

    echo "  before stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
    echo "  correct_end s={$fmt($info['runningS'])} drift s/m/l={$fmt($driftS)}/{$fmt($driftM)}/{$fmt($driftL)}\n";

    if ($driftS <= 0.01) {
        echo "  SKIP (no positive small drift)\n";
        $results[] = [$name, 'SKIP', $driftS];
        continue;
    }

    // Sanity: don't cut more than 50kg equivalent weirdness unless salmon-scale small
    if ($driftS > 100000) {
        echo "  SKIP (drift suspiciously large: {$fmt($driftS)})\n";
        $results[] = [$name, 'SKIP_LARGE', $driftS];
        continue;
    }

    if ($dryRun) {
        echo "  WOULD decrement s/m/l by {$fmt($driftS)}/{$fmt(max(0, $driftM))}/{$fmt(max(0, $driftL))}\n";
        $results[] = [$name, 'DRY', $driftS];
        continue;
    }

    DB::beginTransaction();
    try {
        $locked = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', $whId)
            ->lockForUpdate()
            ->first();
        if (! $locked) {
            throw new RuntimeException('stock missing');
        }

        $newS = (float) $locked->qty_small - $driftS;
        $newM = (float) $locked->qty_medium - max(0, $driftM);
        $newL = (float) $locked->qty_large - max(0, $driftL);
        // If medium drift near 0 but small drift exists, derive from item conversion
        if (abs($driftM) < 0.01 && $driftS > 0.01) {
            $item = DB::table('items')->where('id', $itemId)->first();
            $conv = (float) ($item->small_conversion_qty ?: 1);
            if ($conv > 0) {
                $newM = (float) $locked->qty_medium - ($driftS / $conv);
                $newL = (float) $locked->qty_large - ($driftS / $conv);
            }
        }

        if ($newS < -0.001) {
            throw new RuntimeException("would go negative: {$newS}");
        }

        $mac = (float) ($locked->last_cost_small ?: 0);
        DB::table('food_inventory_stocks')->where('id', $locked->id)->update([
            'qty_small' => $newS,
            'qty_medium' => $newM,
            'qty_large' => $newL,
            'value' => max(0, $newS * $mac),
            'updated_at' => now(),
        ]);
        DB::commit();
        echo "  STOCK {$fmt($locked->qty_small)} -> {$fmt($newS)}\n";
    } catch (Throwable $e) {
        DB::rollBack();
        echo '  FAILED stock: '.$e->getMessage()."\n";
        $results[] = [$name, 'FAIL', $e->getMessage()];
        continue;
    }

    // Refresh stock for reconcile
    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();

    // Tenderloin BL + any item with backdated cards: created_at reconcile
    // Also use created_at for all for consistency with drift computation
    reconcileByCreatedAt($invId, $whId, $fromDate, $stock, false);

    // Verify
    $info2 = computeDrift($invId, $whId, $fromDate);
    $stock2 = $info2['stock'];
    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->first();
    $ok = abs($info2['driftS']) < 0.05
        && abs((float) $stock2->qty_small - (float) $last->saldo_qty_small) < 0.05;
    echo "  AFTER stock={$fmt($stock2->qty_small)} drift={$fmt($info2['driftS'])} last={$fmt($last->saldo_qty_small)} ".($ok ? 'OK' : 'CHECK')."\n";
    $results[] = [$name, $ok ? 'OK' : 'CHECK', $info2['driftS']];
}

echo "\n===== SUMMARY =====\n";
foreach ($results as [$n, $st, $d]) {
    echo sprintf("%-40s %-10s %s\n", $n, $st, is_numeric($d) ? $fmt($d) : $d);
}
echo $dryRun ? "\nDry-run only. Re-run without --dry-run to apply.\n" : "\nDONE\n";
