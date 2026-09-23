<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$whId = 1;
$targets = [
    ['name' => 'Potato Waffle', 'item_id' => 53243, 'inv_id' => 5301],
    ['name' => 'Potato Wedges', 'item_id' => 53225, 'inv_id' => 5302],
];

foreach ($targets as $t) {
    $invId = $t['inv_id'];
    echo "\n########## {$t['name']} (inv={$invId}) ##########\n";

    $doCards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('description', 'like', '%DO2609160037%')
        ->orderBy('id')
        ->get();

    foreach ($doCards as $c) {
        echo sprintf(
            "card id=%s ref=%s/%s out_s=%s out_m=%s out_l=%s saldo_s=%s saldo_m=%s saldo_l=%s created=%s\n  %s\n",
            $c->id,
            $c->reference_type,
            $c->reference_id,
            $fmt($c->out_qty_small),
            $fmt($c->out_qty_medium),
            $fmt($c->out_qty_large),
            $fmt($c->saldo_qty_small),
            $fmt($c->saldo_qty_medium),
            $fmt($c->saldo_qty_large),
            $c->created_at,
            $c->description
        );
    }

    if ($doCards->count() < 2) {
        echo "Need 2 DO cards; found {$doCards->count()}\n";
        continue;
    }

    $first = $doCards[0];
    $second = $doCards[1];
    $sameSaldo = abs((float) $first->saldo_qty_small - (float) $second->saldo_qty_small) < 0.01;
    $lostSmall = (float) $second->out_qty_small;
    $lostMed = (float) $second->out_qty_medium;
    $lostLarge = (float) $second->out_qty_large;

    echo "same_saldo_race=".($sameSaldo ? 'YES' : 'NO')."\n";
    echo "lost_qty (Festival out): small={$fmt($lostSmall)} med={$fmt($lostMed)} large={$fmt($lostLarge)}\n";

    // Correct chain from before first DO card
    $boot = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where(function ($q) use ($first) {
            $q->where('date', '<', $first->date)
                ->orWhere(function ($q2) use ($first) {
                    $q2->where('date', $first->date)->where('id', '<', $first->id);
                });
        })
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    $running = $boot ? (float) $boot->saldo_qty_small : 0.0;
    echo "boot saldo before Jawa={$fmt($running)} (card ".($boot->id ?? 'none').")\n";

    $fromDate = '2026-09-16';
    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '>=', $fromDate)
        ->orderBy('date')
        ->orderBy('id')
        ->get();

    // Use saldo before fromDate as start
    $prev = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '<', $fromDate)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    $correct = $prev ? (float) $prev->saldo_qty_small : 0.0;
    $driftAtEnd = null;
    $bad = 0;
    foreach ($cards as $c) {
        $correct += (float) $c->in_qty_small - (float) $c->out_qty_small;
        $actual = (float) $c->saldo_qty_small;
        if (abs($correct - $actual) > 0.01) {
            $bad++;
            if ($bad <= 5 || stripos((string) $c->description, 'DO2609160037') !== false) {
                echo "  BAD id={$c->id} expected={$fmt($correct)} card={$fmt($actual)} delta={$fmt($actual - $correct)}\n";
            }
        }
        $driftAtEnd = $actual - $correct;
    }
    echo "bad_cards_from_{$fromDate}={$bad}\n";
    echo "end_drift(card-correct)={$fmt($driftAtEnd)} (positive = stock/card too high by this)\n";

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();
    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();

    echo "stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n";
    echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
    echo "stock==last? ".(abs((float)$stock->qty_small - (float)$last->saldo_qty_small) < 0.01 ? 'YES' : 'NO')."\n";
    echo "EXPECTED stock after fix small={$fmt((float)$stock->qty_small - $lostSmall)} med={$fmt((float)$stock->qty_medium - $lostMed)} large={$fmt((float)$stock->qty_large - $lostLarge)}\n";
}

echo "\nDONE\n";
