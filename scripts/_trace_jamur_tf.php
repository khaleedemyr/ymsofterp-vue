<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tf = DB::table('warehouse_transfers')->where('transfer_number', 'WT-20260916-0001')->first();
echo "TF: ".json_encode($tf)."\n\n";

$items = DB::table('warehouse_transfer_items as ti')
    ->leftJoin('items as i','i.id','=','ti.item_id')
    ->leftJoin('units as u','u.id','=','ti.unit_id')
    ->where('ti.warehouse_transfer_id', $tf->id)
    ->get(['ti.*','i.name','i.sku','i.small_conversion_qty','u.name as unit_name','i.small_unit_id','i.medium_unit_id']);
echo "ITEMS:\n".json_encode($items, JSON_PRETTY_PRINT)."\n\n";

$fii = DB::table('food_inventory_items')->where('item_id', $items[0]->item_id)->first();
$ch = DB::table('food_inventory_cost_histories')
    ->where('reference_type','warehouse_transfer')
    ->where('reference_id', $tf->id)
    ->get();
echo "COST HIST:\n".json_encode($ch, JSON_PRETTY_PRINT)."\n\n";

$cards = DB::table('food_inventory_cards')
    ->where('reference_type','warehouse_transfer')
    ->where('reference_id', $tf->id)
    ->get();
echo "CARDS:\n".json_encode($cards, JSON_PRETTY_PRINT)."\n\n";

$stocks = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', $fii->id)
    ->whereIn('warehouse_id', [1,2,5])
    ->get(['warehouse_id','qty_small','last_cost_small','value']);
echo "STOCKS NOW:\n".json_encode($stocks, JSON_PRETTY_PRINT)."\n\n";

$c = app(App\Http\Controllers\WarehouseCostHealthDashboardController::class);
$d = $c->transactionDetail(Illuminate\Http\Request::create('/x','GET',['type'=>'warehouse_transfer','id'=>$tf->id]))->getData(true);
echo "API DETAIL:\n".json_encode($d, JSON_PRETTY_PRINT)."\n";
