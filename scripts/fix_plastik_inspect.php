<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$itemId = 52775;

$line = DB::table('food_good_receive_items as gri')
    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->leftJoin('purchase_order_foods as po', 'poi.purchase_order_food_id', '=', 'po.id')
    ->where('gri.id', 31649)
    ->select('gri.*', 'gr.gr_number', 'gr.status as gr_status', 'poi.price as po_price', 'poi.unit_id as po_unit_id', 'poi.id as poi_id', 'po.id as po_id', 'po.number as po_number')
    ->first();

echo "GRI cols relevant:\n";
echo json_encode($line, JSON_PRETTY_PRINT).PHP_EOL;

echo "\npoi columns: ".implode(', ', Schema::getColumnListing('purchase_order_food_items')).PHP_EOL;
$poi = DB::table('purchase_order_food_items')->where('id', 34503)->first();
echo "POI: ".json_encode($poi, JSON_PRETTY_PRINT).PHP_EOL;

echo "\ngri columns: ".implode(', ', Schema::getColumnListing('food_good_receive_items')).PHP_EOL;

$ip = DB::table('item_prices')->where('item_id', $itemId)->get();
echo "\nitem_prices: ".json_encode($ip, JSON_PRETTY_PRINT).PHP_EOL;

// stock / inventory impact?
foreach (['warehouse_stocks', 'item_stocks', 'food_inventory', 'outlet_food_inventory'] as $t) {
    if (Schema::hasTable($t)) echo "has $t\n";
}
