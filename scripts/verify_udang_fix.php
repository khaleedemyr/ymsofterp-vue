<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$invId = 5572;
$whId = 1;
$fmt = fn ($n) => number_format((float) $n, 3, '.', '');

$stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $invId)->where('warehouse_id', $whId)->first();
$jawa = DB::table('food_inventory_cards')->where('id', 777003)->first();
$fest = DB::table('food_inventory_cards')->where('id', 777038)->first();
$last = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
    ->orderByDesc('date')->orderByDesc('id')->first();

echo "Jawa saldo={$fmt($jawa->saldo_qty_small)}\n";
echo "Festival out={$fmt($fest->out_qty_small)} saldo={$fmt($fest->saldo_qty_small)}\n";
echo "stock={$fmt($stock->qty_small)} last_card={$fmt($last->saldo_qty_small)} id={$last->id}\n";

$prev = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
    ->whereDate('date', '<', '2026-09-16')
    ->orderByDesc('date')->orderByDesc('id')->first();
$running = (float) $prev->saldo_qty_small;
$bad = 0;
foreach (DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)->where('warehouse_id', $whId)
    ->whereDate('date', '>=', '2026-09-16')
    ->orderBy('date')->orderBy('id')->get() as $c) {
    $running += (float) $c->in_qty_small - (float) $c->out_qty_small;
    if (abs($running - (float) $c->saldo_qty_small) > 0.01) {
        $bad++;
        echo "BAD id={$c->id}\n";
    }
}
echo $bad === 0 ? "VERIFY OK\n" : "VERIFY FAIL {$bad}\n";
