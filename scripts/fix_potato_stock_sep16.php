<?php

/**
 * Fix Potato Waffle & Potato Wedges Main Store stock + card chain
 * after race on DO2609160037 (Festival Citylink lost update).
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$whId = 1;
$fromDate = '2026-09-16';
$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

$targets = [
    [
        'name' => 'Potato Waffle',
        'item_id' => 53243,
        'inv_id' => 5301,
        'lost_small' => 4000.0,
        'lost_medium' => 2.0,
        'lost_large' => 2.0,
        'festival_card_id' => 777034,
    ],
    [
        'name' => 'Potato Wedges',
        'item_id' => 53225,
        'inv_id' => 5302,
        'lost_small' => 4540.0,
        'lost_medium' => 2.0,
        'lost_large' => 2.0,
        'festival_card_id' => 777035,
    ],
];

foreach ($targets as $t) {
    echo "\n========== {$t['name']} ==========\n";

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->first();
    $bad = DB::table('food_inventory_cards')->where('id', $t['festival_card_id'])->first();
    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    echo "BEFORE stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
    echo "BEFORE festival card {$t['festival_card_id']} out={$fmt($bad->out_qty_small)} saldo={$fmt($bad->saldo_qty_small)}\n";
    echo "BEFORE last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";

    // Idempotent: if festival card saldo already reflects deduction, skip stock cut
    $jawaCards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->where('description', 'like', '%DO2609160037%')
        ->orderBy('id')
        ->get();
    $alreadyFixed = false;
    if ($jawaCards->count() >= 2) {
        $a = $jawaCards[0];
        $b = $jawaCards[1];
        $expectedSecond = (float) $a->saldo_qty_small - (float) $b->out_qty_small;
        if (abs((float) $b->saldo_qty_small - $expectedSecond) < 0.01) {
            $alreadyFixed = true;
            echo "Card chain already looks fixed for DO37 pair — skip stock decrement.\n";
        }
    }

    if (! $alreadyFixed) {
        DB::beginTransaction();
        try {
            $locked = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $t['inv_id'])
                ->where('warehouse_id', $whId)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new RuntimeException('Stock row missing');
            }

            $newSmall = (float) $locked->qty_small - $t['lost_small'];
            $newMedium = (float) $locked->qty_medium - $t['lost_medium'];
            $newLarge = (float) $locked->qty_large - $t['lost_large'];
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

            echo "STOCK DECREMENTED {$fmt($t['lost_small'])}g: {$fmt($locked->qty_small)} -> {$fmt($newSmall)}\n";
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            echo 'FAILED stock fix: '.$e->getMessage()."\n";
            exit(1);
        }
    }

    echo "RECONCILE CARD CHAIN...\n";
    Artisan::call('inventory:reconcile-warehouse-card-saldo', [
        '--item-id' => $t['item_id'],
        '--warehouse-id' => $whId,
        '--from-date' => $fromDate,
    ]);
    echo Artisan::output();

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->first();
    $bad = DB::table('food_inventory_cards')->where('id', $t['festival_card_id'])->first();
    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    echo "AFTER festival card {$t['festival_card_id']} saldo={$fmt($bad->saldo_qty_small)}\n";
    echo "AFTER stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
    echo "AFTER last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
    echo 'stock==last? '.(abs((float) $stock->qty_small - (float) $last->saldo_qty_small) < 0.01 ? 'YES' : 'NO')."\n";

    $prev = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->whereDate('date', '<', $fromDate)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    $running = $prev ? (float) $prev->saldo_qty_small : 0.0;
    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $t['inv_id'])
        ->where('warehouse_id', $whId)
        ->whereDate('date', '>=', $fromDate)
        ->orderBy('date')
        ->orderBy('id')
        ->get();
    $badCount = 0;
    foreach ($cards as $c) {
        $running += (float) $c->in_qty_small - (float) $c->out_qty_small;
        // last card is forced to stock by reconcile; intermediate must match running
        if ($c->id != $last->id && abs($running - (float) $c->saldo_qty_small) > 0.01) {
            $badCount++;
            if ($badCount <= 3) {
                echo "CHAIN BAD id={$c->id} expected={$fmt($running)} card={$fmt($c->saldo_qty_small)}\n";
            }
        }
    }
    // Final: last card should equal stock
    if (abs((float) $stock->qty_small - (float) $last->saldo_qty_small) > 0.01) {
        $badCount++;
    }
    echo $badCount === 0 ? "Chain OK from {$fromDate}\n" : "Chain still has {$badCount} errors\n";
}

echo "\nALL DONE\n";
