<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$invId = 4674;
$whId = 1;

// Simulate reconcile from Sep16 using rejection as prev (date order)
$running = 76800.0;
$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '>=', '2026-09-16')
    ->orderBy('date')->orderBy('created_at')->orderBy('id')
    ->get();
foreach ($cards as $c) {
    $running += (float) $c->in_qty_small - (float) $c->out_qty_small;
}
$stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $invId)->where('warehouse_id', $whId)->first();
echo "If reconcile from rejection 76800: end={$fmt($running)} stock={$fmt($stock->qty_small)} diff={$fmt((float)$stock->qty_small - $running)}\n";

// Simulate from pre-rejection Sep15 last real DO (8000), created_at order including rejection when it happened
$all = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->where('created_at', '>=', '2026-09-15')
    ->orderBy('created_at')->orderBy('id')
    ->get();
$boot = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
    ->where('created_at', '<', '2026-09-15')
    ->orderByDesc('created_at')->orderByDesc('id')->first();
$r = $boot ? (float) $boot->saldo_qty_small : 0;
echo "boot before created Sep15: {$fmt($r)} id=".($boot->id ?? 'n')."\n";
$bad = 0;
foreach ($all as $c) {
    $exp = $r + (float)$c->in_qty_small - (float)$c->out_qty_small;
    $delta = (float)$c->saldo_qty_small - $exp;
    if (abs($delta) > 0.01) {
        $bad++;
        if ($bad <= 8 || $c->id == 777924 || stripos($c->description, 'DO2609160037') !== false) {
            echo sprintf("BAD id=%s created=%s in=%s out=%s card=%s exp=%s d=%s | %s\n",
                $c->id, $c->created_at, $fmt($c->in_qty_small), $fmt($c->out_qty_small),
                $fmt($c->saldo_qty_small), $fmt($exp), $fmt($delta), substr($c->description, 0, 50));
        }
    }
    $r = $exp;
}
echo "created_at-chain end={$fmt($r)} stock={$fmt($stock->qty_small)} drift={$fmt((float)$stock->qty_small - $r)} bad={$bad}\n";

// What if rejection in was 68800?
$r2 = $boot ? (float) $boot->saldo_qty_small : 0;
foreach ($all as $c) {
    $in = (float) $c->in_qty_small;
    if ((int)$c->id === 777924) {
        $in = 68800.0; // corrected
    }
    $r2 += $in - (float) $c->out_qty_small;
}
echo "if rejection in=68800: end={$fmt($r2)} stock={$fmt($stock->qty_small)} drift={$fmt((float)$stock->qty_small - $r2)}\n";
