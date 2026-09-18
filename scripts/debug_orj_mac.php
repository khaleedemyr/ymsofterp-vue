<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$serial = DB::table('inventory_item_serials')->where('id', 298822)->first();
echo "serial cost_small={$serial->cost_small} unit_id={$serial->unit_id} item_id={$serial->item_id}\n";
echo "qty_small=".($serial->qty_small ?? 'n/a')." cost_source=".($serial->cost_source ?? 'n/a')."\n";

$item = DB::table('items')->where('id', 53235)->first();
echo "item={$item->name}\n";
echo "units s={$item->small_unit_id} m={$item->medium_unit_id} l={$item->large_unit_id}\n";
echo "conv small={$item->small_conversion_qty} med={$item->medium_conversion_qty}\n";

foreach (['small_unit_id','medium_unit_id','large_unit_id'] as $f) {
    $u = DB::table('units')->where('id', $item->$f)->first();
    echo "  {$f} => ".($u->name ?? '?')."\n";
}
$pack = DB::table('units')->where('id', 5)->first();
echo "unit_id 5 = ".($pack->name ?? '?')."\n";

$macSmall = (float) $serial->cost_small;
$smallConv = (float) ($item->small_conversion_qty ?: 1);
$mediumConv = (float) ($item->medium_conversion_qty ?: 1);
$unitId = 5;
echo "macSmall={$macSmall}\n";
if ($unitId === (int) $item->small_unit_id) {
    $macLine = $macSmall;
} elseif ($unitId === (int) $item->medium_unit_id) {
    $macLine = $macSmall * $smallConv;
} elseif ($unitId === (int) $item->large_unit_id) {
    $macLine = $macSmall * $smallConv * $mediumConv;
} else {
    $macLine = $macSmall;
}
echo "macLine after convert={$macLine}\n";
echo "DECIMAL(15,4) max=99999999999.9999 exceeds=".($macLine > 99999999999.9999 ? 'YES' : 'NO')."\n";

$inv = DB::table('food_inventory_items')->where('item_id', 53235)->first();
if ($inv) {
    $stocks = DB::table('food_inventory_stocks')->where('inventory_item_id', $inv->id)->limit(10)->get();
    foreach ($stocks as $s) {
        $implied = ($s->qty_small > 0) ? ($s->value / $s->qty_small) : 0;
        echo "stock wh={$s->warehouse_id} qty={$s->qty_small} value={$s->value} last_cost={$s->last_cost_small} implied={$implied}\n";
    }
}
