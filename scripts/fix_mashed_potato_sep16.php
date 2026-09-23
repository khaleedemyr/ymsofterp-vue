<?php

/**
 * Fix Mashed Potato Puree Main Store after DO2609160037 race.
 * Festival card out=6000 but saldo only dropped 3000 vs Jawa (stale read) → stock +3000 too high.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$itemId = 53219;
$invId = 5166;
$whId = 1;
$fromDate = '2026-09-16';
$lostSmall = 3000.0;
$lostMedium = 1.0;
$lostLarge = 1.0;
$festivalCardId = 777030;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

echo "===== BEFORE =====\n";
$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->first();
$fest = DB::table('food_inventory_cards')->where('id', $festivalCardId)->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();

echo "stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
echo "festival card {$festivalCardId} out={$fmt($fest->out_qty_small)} saldo={$fmt($fest->saldo_qty_small)} (expect 237000)\n";
echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";

// Idempotent: if festival already at expected relative to Jawa
$jawa = DB::table('food_inventory_cards')->where('id', 776998)->first();
$expectedFest = (float) $jawa->saldo_qty_small - (float) $fest->out_qty_small;
$alreadyOk = abs((float) $fest->saldo_qty_small - $expectedFest) < 0.01;

if ($alreadyOk) {
    echo "Already fixed — skip stock decrement.\n";
} else {
    DB::beginTransaction();
    try {
        $locked = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $invId)
            ->where('warehouse_id', $whId)
            ->lockForUpdate()
            ->first();
        if (! $locked) {
            throw new RuntimeException('Stock row missing');
        }

        $newSmall = (float) $locked->qty_small - $lostSmall;
        $newMedium = (float) $locked->qty_medium - $lostMedium;
        $newLarge = (float) $locked->qty_large - $lostLarge;
        $mac = (float) ($locked->last_cost_small ?: 0);

        if ($newSmall < -0.001) {
            throw new RuntimeException('Stock would go negative: '.$newSmall);
        }

        DB::table('food_inventory_stocks')
            ->where('id', $locked->id)
            ->update([
                'qty_small' => $newSmall,
                'qty_medium' => $newMedium,
                'qty_large' => $newLarge,
                'value' => max(0, $newSmall * $mac),
                'updated_at' => now(),
            ]);

        echo "STOCK DECREMENTED {$fmt($lostSmall)}g: {$fmt($locked->qty_small)} -> {$fmt($newSmall)}\n";
        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        echo 'FAILED: '.$e->getMessage()."\n";
        exit(1);
    }
}

echo "\n===== RECONCILE =====\n";
Artisan::call('inventory:reconcile-warehouse-card-saldo', [
    '--item-id' => $itemId,
    '--warehouse-id' => $whId,
    '--from-date' => $fromDate,
]);
echo Artisan::output();

echo "===== AFTER =====\n";
$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->first();
$fest = DB::table('food_inventory_cards')->where('id', $festivalCardId)->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();

echo "festival saldo={$fmt($fest->saldo_qty_small)} (expect 237000)\n";
echo "stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
echo 'stock==last? '.(abs((float) $stock->qty_small - (float) $last->saldo_qty_small) < 0.01 ? 'YES' : 'NO')."\n";

$prev = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '<', $fromDate)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();
$running = $prev ? (float) $prev->saldo_qty_small : 0.0;
$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '>=', $fromDate)
    ->orderBy('date')
    ->orderBy('id')
    ->get();
$badCount = 0;
foreach ($cards as $c) {
    $running += (float) $c->in_qty_small - (float) $c->out_qty_small;
    if ($c->id != $last->id && abs($running - (float) $c->saldo_qty_small) > 0.01) {
        $badCount++;
        if ($badCount <= 3) {
            echo "CHAIN BAD id={$c->id} expected={$fmt($running)} card={$fmt($c->saldo_qty_small)}\n";
        }
    }
}
if (abs((float) $stock->qty_small - (float) $last->saldo_qty_small) > 0.01) {
    $badCount++;
}
echo $badCount === 0 ? "Chain OK from {$fromDate}\n" : "Chain still has {$badCount} errors\n";
