<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$name = 'Beef Chuck Short Ribs Dice';
$outletId = '18';
$from = '2026-07-01';
$to = '2026-07-31';

$item = DB::table('items')->where('name', $name)->first();
if (! $item) {
    echo "Item not found\n";
    exit(1);
}
$itemId = (int) $item->id;

echo "=== {$name} (id={$itemId}) ===\n";
$mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
$largeUnit = DB::table('units')->where('id', $item->large_unit_id)->value('name');
echo "medium_conv={$item->medium_conversion_qty} small_conv={$item->small_conversion_qty}\n";
echo "medium={$mediumUnit} large={$largeUnit}\n\n";

echo "=== item_prices ===\n";
foreach (DB::table('item_prices')->where('item_id', $itemId)->orderBy('id')->get() as $r) {
    echo "id={$r->id} type={$r->availability_price_type} region={$r->region_id} price={$r->price} mode=" . ($r->pricing_mode ?? '-') . " updated={$r->updated_at}\n";
}

$priceLarge = (float) DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->value('price');
$expectedPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $mediumUnit, 1, $outletId);
$expectedLarge = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $largeUnit, 1, $outletId);
echo "\nResolver: large={$expectedLarge} Pack={$expectedPack}\n";
echo "Manual calc large/conv: " . FloorOrderItemPriceResolver::roundUpToHundred($priceLarge / max((float) $item->medium_conversion_qty, 1)) . "\n";

echo "\n=== FO lines Buah Batu Jul 2026 (by price) ===\n";
$foStats = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $itemId)
    ->where('ffo.id_outlet', $outletId)
    ->whereBetween('ffo.tanggal', [$from, $to])
    ->selectRaw('ffoi.price, ffoi.unit, COUNT(*) as cnt, SUM(ffoi.qty) as qty_sum')
    ->groupBy('ffoi.price', 'ffoi.unit')
    ->orderByDesc('qty_sum')
    ->get();
$totalQty = 0;
$totalSub = 0;
foreach ($foStats as $s) {
    $sub = (float) $s->price * (float) $s->qty_sum;
    $totalQty += (float) $s->qty_sum;
    $totalSub += $sub;
    echo "  price={$s->price} unit={$s->unit} lines={$s->cnt} qty={$s->qty_sum} subtotal~{$sub}\n";
}
if ($totalQty > 0) {
    echo "  IMPLIED AVG: " . round($totalSub / $totalQty, 3) . " (qty={$totalQty})\n";
}

echo "\n=== GR Jul 2026 Buah Batu ===\n";
$gr = DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->join('food_floor_order_items as fo', function ($j) {
        $j->on('fo.item_id', '=', 'gri.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->where('gri.item_id', $itemId)
    ->where('ffo.id_outlet', $outletId)
    ->whereBetween('gr.receive_date', [$from, $to])
    ->whereNull('gr.deleted_at')
    ->selectRaw('SUM(gri.qty) as qty, SUM(gri.qty * fo.price) as subtotal, COUNT(DISTINCT gr.id) as gr_cnt')
    ->first();
echo "  GR qty={$gr->qty} subtotal={$gr->subtotal} implied=" . ($gr->qty > 0 ? round($gr->subtotal / $gr->qty, 3) : 0) . "\n";

echo "\n=== Wrong FO samples (price != expected {$expectedPack}) ===\n";
$wrong = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $itemId)
    ->where('ffo.id_outlet', $outletId)
    ->whereBetween('ffo.tanggal', [$from, $to])
    ->whereRaw('ABS(ffoi.price - ?) > 1', [$expectedPack])
    ->select('ffo.tanggal', 'ffo.order_number', 'ffo.status', 'ffoi.price', 'ffoi.qty', 'ffoi.unit', 'ffoi.created_at', 'ffoi.updated_at')
    ->orderBy('ffo.tanggal')
    ->limit(15)
    ->get();
foreach ($wrong as $r) {
    echo "  {$r->tanggal} {$r->order_number} [{$r->status}] price={$r->price} qty={$r->qty} unit={$r->unit} line_updated={$r->updated_at}\n";
}
