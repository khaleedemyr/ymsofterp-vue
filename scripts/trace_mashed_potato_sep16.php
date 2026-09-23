<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$whId = 1;
$fromDate = '2026-09-16';

echo "===== Find Mashed Potato Puree =====\n";
$items = DB::table('items')
    ->where('name', 'like', '%Mashed Potato Puree%')
    ->select('id', 'name', 'sku')
    ->get();
foreach ($items as $i) {
    echo "item id={$i->id} name={$i->name} sku={$i->sku}\n";
}

$invItems = DB::table('food_inventory_items as fii')
    ->join('items as it', 'it.id', '=', 'fii.item_id')
    ->where('it.name', 'like', '%Mashed Potato Puree%')
    ->select('fii.id as inv_id', 'fii.item_id', 'it.name')
    ->get();
foreach ($invItems as $r) {
    echo "inv_id={$r->inv_id} item_id={$r->item_id} name={$r->name}\n";
}

if ($invItems->isEmpty()) {
    exit(1);
}

foreach ($invItems as $inv) {
    $invId = (int) $inv->inv_id;
    $itemId = (int) $inv->item_id;
    echo "\n########## {$inv->name} (item={$itemId} inv={$invId}) ##########\n";

    $stock = DB::table('food_inventory_stocks')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->first();
    echo $stock
        ? "stock s/m/l={$fmt($stock->qty_small)}/{$fmt($stock->qty_medium)}/{$fmt($stock->qty_large)}\n"
        : "NO STOCK\n";

    echo "----- Cards DO2609160037 -----\n";
    $doCards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->where('description', 'like', '%DO2609160037%')
        ->orderBy('id')
        ->get();
    foreach ($doCards as $c) {
        echo sprintf(
            "id=%s created=%s out_s=%s out_m=%s saldo_s=%s saldo_m=%s ref=%s/%s\n  %s\n",
            $c->id,
            $c->created_at,
            $fmt($c->out_qty_small),
            $fmt($c->out_qty_medium),
            $fmt($c->saldo_qty_small),
            $fmt($c->saldo_qty_medium),
            $c->reference_type,
            $c->reference_id,
            $c->description
        );
    }

    echo "----- Sep 16 chain (from prev saldo) -----\n";
    $prev = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '<', $fromDate)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    $running = $prev ? (float) $prev->saldo_qty_small : 0.0;
    echo "boot before {$fromDate}={$fmt($running)} (card ".($prev->id ?? 'none').")\n";

    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->whereDate('date', '>=', $fromDate)
        ->orderBy('date')
        ->orderBy('id')
        ->get();

    $bad = 0;
    $cascadePrev = $running;
    foreach ($cards as $c) {
        $shouldFromOut = $running + (float) $c->in_qty_small - (float) $c->out_qty_small;
        $actual = (float) $c->saldo_qty_small;
        $cascadeShould = $cascadePrev + (float) $c->in_qty_small - (float) $c->out_qty_small;
        $ok = abs($shouldFromOut - $actual) < 0.01 ? 'OK' : 'BAD';
        if ($ok === 'BAD') {
            $bad++;
        }
        $mark = (stripos((string) $c->description, 'DO2609160037') !== false) ? ' <<DO37' : '';
        if ($c->date === '2026-09-16' || $ok === 'BAD' || $mark) {
            echo sprintf(
                "  [%s] id=%s in=%s out=%s expected=%s card=%s delta=%s%s\n",
                $ok,
                $c->id,
                $fmt($c->in_qty_small),
                $fmt($c->out_qty_small),
                $fmt($shouldFromOut),
                $fmt($actual),
                $fmt($actual - $shouldFromOut),
                $mark
            );
        }
        $running = $shouldFromOut; // correct chain continues
        $cascadePrev = $actual;
    }
    echo "bad_vs_correct_chain={$bad}\n";
    echo "end_drift(card_last_vs_correct)=".($fmt((float) optional($cards->last())->saldo_qty_small - $running))."\n";

    $last = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)
        ->where('warehouse_id', $whId)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->first();
    if ($stock && $last) {
        echo "last card id={$last->id} saldo={$fmt($last->saldo_qty_small)}\n";
        echo 'stock==last? '.(abs((float) $stock->qty_small - (float) $last->saldo_qty_small) < 0.01 ? 'YES' : 'NO')."\n";
        echo 'diff stock-correct_end='.$fmt((float) $stock->qty_small - $running)."\n";
    }

    // Diagnose lost qty from DO37 pair
    if ($doCards->count() >= 2) {
        $first = $doCards[0];
        $second = $doCards[1];
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
        $before = $boot ? (float) $boot->saldo_qty_small : 0.0;
        $afterFirstExpected = $before - (float) $first->out_qty_small + (float) $first->in_qty_small;
        $afterSecondExpected = $afterFirstExpected - (float) $second->out_qty_small + (float) $second->in_qty_small;
        echo "----- DO37 expected -----\n";
        echo "before={$fmt($before)} after_jawa_expect={$fmt($afterFirstExpected)} after_festival_expect={$fmt($afterSecondExpected)}\n";
        echo "jawa_card_saldo={$fmt($first->saldo_qty_small)} festival_card_saldo={$fmt($second->saldo_qty_small)}\n";
        echo 'festival_shortfall='.$fmt((float) $second->saldo_qty_small - $afterSecondExpected)." (how much too high)\n";
    }
}

echo "\nDONE\n";
