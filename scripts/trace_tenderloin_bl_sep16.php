<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fmt = fn ($n) => number_format((float) $n, 3, '.', '');
$invId = 4674; // Beef Tenderloin Blue Label
$whId = 1;

echo "===== Tenderloin Blue Label cards around DO37 =====\n";
$cards = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '>=', '2026-09-15')
    ->whereDate('date', '<=', '2026-09-17')
    ->orderBy('date')
    ->orderBy('id')
    ->get();

$prev = DB::table('food_inventory_cards')
    ->where('inventory_item_id', $invId)
    ->where('warehouse_id', $whId)
    ->whereDate('date', '<', '2026-09-15')
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();
$running = $prev ? (float) $prev->saldo_qty_small : null;
echo "boot before 15 Sep: ".($prev ? "id={$prev->id} saldo={$fmt($prev->saldo_qty_small)}" : 'none')."\n";

foreach ($cards as $c) {
    if ($running === null) {
        $running = (float) $c->saldo_qty_small + (float) $c->out_qty_small - (float) $c->in_qty_small;
    }
    $expect = $running + (float) $c->in_qty_small - (float) $c->out_qty_small;
    $ok = abs($expect - (float) $c->saldo_qty_small) < 0.01 ? 'OK' : 'BAD';
    echo sprintf(
        "[%s] id=%s date=%s in=%s out=%s card=%s expect=%s delta=%s | %s\n",
        $ok,
        $c->id,
        $c->date,
        $fmt($c->in_qty_small),
        $fmt($c->out_qty_small),
        $fmt($c->saldo_qty_small),
        $fmt($expect),
        $fmt((float)$c->saldo_qty_small - $expect),
        substr((string)$c->description, 0, 80)
    );
    $running = $expect;
}

$stock = DB::table('food_inventory_stocks')->where('inventory_item_id', $invId)->where('warehouse_id', $whId)->first();
$last = DB::table('food_inventory_cards')->where('inventory_item_id', $invId)->where('warehouse_id', $whId)->orderByDesc('date')->orderByDesc('id')->first();
echo "\nstock={$fmt($stock->qty_small)} last={$fmt($last->saldo_qty_small)} id={$last->id}\n";

// unit info
$item = DB::table('items')->where('id', 52994)->first();
echo "item units small={$item->small_unit_id} med={$item->medium_unit_id} large={$item->large_unit_id} conv s={$item->small_conversion_qty} m={$item->medium_conversion_qty}\n";
foreach ([$item->small_unit_id, $item->medium_unit_id, $item->large_unit_id] as $uid) {
    $u = DB::table('units')->where('id', $uid)->value('name');
    echo "  unit {$uid}={$u}\n";
}
