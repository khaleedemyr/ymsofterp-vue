<?php

/**
 * Fix Udang Main Store stock + card chain after race on DO2609160037 (Festival).
 * 1) Dry-run reconcile preview
 * 2) Decrement stock 1kg (lost update)
 * 3) Recalculate card chain from 2026-09-16
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$itemId = 53330; // Udang
$invId = 5572;
$whId = 1;
$fromDate = '2026-09-16';

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

echo "===== BEFORE =====\n";
$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->first();
$bad = DB::table('food_inventory_cards')->where('id', 777038)->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();

echo "stock small={$fmt($stock->qty_small)} med={$fmt($stock->qty_medium)} large={$fmt($stock->qty_large)}\n";
echo "bad card 777038 out={$fmt($bad->out_qty_small)} saldo={$fmt($bad->saldo_qty_small)}\n";
echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";

echo "\n===== DRY-RUN reconcile (before stock fix) =====\n";
Artisan::call('inventory:reconcile-warehouse-card-saldo', [
    '--item-id' => $itemId,
    '--warehouse-id' => $whId,
    '--from-date' => $fromDate,
    '--dry-run' => true,
]);
echo Artisan::output();

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

    // Lost update from Festival DO: deduct 1 kg (= 1000 gram / 1 medium / 1 large)
    $newSmall = (float) $locked->qty_small - 1000;
    $newMedium = (float) $locked->qty_medium - 1;
    $newLarge = (float) $locked->qty_large - 1;
    $mac = (float) ($locked->last_cost_small ?: 0);
    $newValue = max(0, $newSmall * $mac);

    if ($newSmall < -0.001) {
        throw new RuntimeException('Stock would go negative: '.$newSmall);
    }

    DB::table('food_inventory_stocks')
        ->where('id', $locked->id)
        ->update([
            'qty_small' => $newSmall,
            'qty_medium' => $newMedium,
            'qty_large' => $newLarge,
            'value' => $newValue,
            'updated_at' => now(),
        ]);

    echo "\n===== STOCK DECREMENTED 1kg =====\n";
    echo "{$fmt($locked->qty_small)} -> {$fmt($newSmall)}\n";

    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED stock fix: '.$e->getMessage()."\n";
    exit(1);
}

echo "\n===== RECONCILE CARD CHAIN =====\n";
Artisan::call('inventory:reconcile-warehouse-card-saldo', [
    '--item-id' => $itemId,
    '--warehouse-id' => $whId,
    '--from-date' => $fromDate,
]);
echo Artisan::output();

echo "\n===== AFTER =====\n";
$stock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->first();
$bad = DB::table('food_inventory_cards')->where('id', 777038)->first();
$jawa = DB::table('food_inventory_cards')->where('id', 777003)->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();

echo "Jawa 777003 saldo={$fmt($jawa->saldo_qty_small)}\n";
echo "Festival 777038 saldo={$fmt($bad->saldo_qty_small)} (expect 38000)\n";
echo "stock small={$fmt($stock->qty_small)}\n";
echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
echo "stock==last? ".(abs((float)$stock->qty_small - (float)$last->saldo_qty_small) < 0.01 ? 'YES' : 'NO')."\n";

// verify chain integrity from 15 Sep
$prev = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '<', $fromDate)
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();
$running = (float) $prev->saldo_qty_small;
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
    if (abs($running - (float) $c->saldo_qty_small) > 0.01) {
        $badCount++;
        echo "CHAIN BAD id={$c->id} expected={$fmt($running)} card={$fmt($c->saldo_qty_small)}\n";
    }
}
echo $badCount === 0 ? "Chain OK from {$fromDate}\n" : "Chain still has {$badCount} errors\n";
