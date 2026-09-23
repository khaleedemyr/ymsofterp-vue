<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

function analyze(int $itemId, int $invId, int $whId = 1, string $fromDate = '2026-09-16'): void
{
    global $fmt;
    $item = DB::table('items')->where('id', $itemId)->first();
    echo "\n########## {$item->name} (item={$itemId} inv={$invId}) ##########\n";
    echo "conv small={$item->small_conversion_qty} med={$item->medium_conversion_qty}\n";

    $stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $invId)->where('warehouse_id', $whId)->first();
    $prev = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
        ->whereDate('date', '<', $fromDate)
        ->orderByDesc('date')->orderByDesc('id')->first();
    $running = $prev ? (float) $prev->saldo_qty_small : 0.0;

    $cards = DB::table('food_inventory_cards')
        ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
        ->whereDate('date', '>=', $fromDate)
        ->orderBy('date')->orderBy('id')->get();

    $do37 = [];
    foreach ($cards as $c) {
        $expected = $running + (float) $c->in_qty_small - (float) $c->out_qty_small;
        $delta = (float) $c->saldo_qty_small - $expected;
        if (stripos((string) $c->description, 'DO2609160037') !== false || abs($delta) > 0.01 && count($do37) < 5) {
            // show DO37 always
        }
        if (stripos((string) $c->description, 'DO2609160037') !== false) {
            $do37[] = $c;
            echo sprintf(
                "DO37 id=%s created=%s out_s=%s out_m=%s saldo_s=%s saldo_m=%s expect=%s delta=%s\n  %s\n",
                $c->id, $c->created_at, $fmt($c->out_qty_small), $fmt($c->out_qty_medium),
                $fmt($c->saldo_qty_small), $fmt($c->saldo_qty_medium), $fmt($expected), $fmt($delta),
                $c->description
            );
        }
        $running = $expected;
    }
    $drift = (float) $stock->qty_small - $running;
    echo "stock={$fmt($stock->qty_small)} med={$fmt($stock->qty_medium)} correct_end={$fmt($running)} drift={$fmt($drift)}\n";

    if (count($do37) >= 2) {
        $a = $do37[0];
        $b = $do37[1];
        $should = (float) $a->saldo_qty_small - (float) $b->out_qty_small;
        $shortfall = (float) $b->saldo_qty_small - $should;
        echo "DO37 shortfall_s={$fmt($shortfall)} out_m jawa={$fmt($a->out_qty_medium)} fest={$fmt($b->out_qty_medium)}\n";
        // lost medium/large for fix: prefer matching drift if positive
        echo "suggested lost_small=".($drift > 0 ? $fmt($drift) : $fmt(max(0, $shortfall)))."\n";
    }
}

analyze(52993, 4631); // Rib Eye Blue Label
analyze(53274, 4958); // Fresh Milk Greenfields
analyze(53273, 4957); // Fresh Milk

// Tenderloin BL rejection card
echo "\n===== Rejection card tenderloin BL =====\n";
$rej = DB::table('food_inventory_cards')->where('id', 777924)->first();
echo json_encode($rej, JSON_PRETTY_PRINT)."\n";
$around = DB::table('food_inventory_cards')
    ->where('inventory_item_id', 4674)
    ->where('warehouse_id', 1)
    ->whereIn('id', [776684, 777924, 776981, 777016])
    ->orderBy('created_at')
    ->get(['id', 'date', 'created_at', 'in_qty_small', 'out_qty_small', 'saldo_qty_small', 'description']);
foreach ($around as $c) {
    echo "id={$c->id} date={$c->date} created={$c->created_at} in={$fmt($c->in_qty_small)} out={$fmt($c->out_qty_small)} saldo={$fmt($c->saldo_qty_small)}\n";
}
