<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tf = DB::table('warehouse_transfers')->where('transfer_number', 'WT-20260921-0002')->first();
echo "TF:\n".json_encode($tf, JSON_PRETTY_PRINT)."\n";

$items = DB::table('warehouse_transfer_items as ti')
    ->leftJoin('items as i','i.id','=','ti.item_id')
    ->where('ti.warehouse_transfer_id', $tf->id)
    ->get(['ti.*','i.name','i.sku']);
echo "ITEMS:\n".json_encode($items, JSON_PRETTY_PRINT)."\n";

$ch = DB::table('food_inventory_cost_histories')
    ->where('reference_type','warehouse_transfer')
    ->where('reference_id', $tf->id)
    ->get();
echo "COST HIST:\n".json_encode($ch, JSON_PRETTY_PRINT)."\n";

$cards = DB::table('food_inventory_cards')
    ->where('reference_type','warehouse_transfer')
    ->where('reference_id', $tf->id)
    ->get();
echo "CARDS:\n".json_encode($cards, JSON_PRETTY_PRINT)."\n";

// current stock gomatare
$stocks = DB::table('food_inventory_stocks as s')
    ->join('food_inventory_items as fii','fii.id','=','s.inventory_item_id')
    ->join('items as i','i.id','=','fii.item_id')
    ->leftJoin('warehouses as w','w.id','=','s.warehouse_id')
    ->where('i.sku','MK-20260813-8227')
    ->get(['w.name','s.qty_small','s.last_cost_small','s.value','s.updated_at']);
echo "CURRENT STOCK:\n".json_encode($stocks, JSON_PRETTY_PRINT)."\n";

// when was repair vs transfer
echo "transfer created_at={$tf->created_at}\n";
$repairs = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', 5152)
    ->whereIn('reference_type', ['mass_repair_mk_cost_bug','mass_repair_pass2','mass_repair_pass3'])
    ->orderBy('id')
    ->get(['id','warehouse_id','old_cost','new_cost','mac','reference_type','created_at']);
echo "REPAIRS:\n".json_encode($repairs, JSON_PRETTY_PRINT)."\n";

// how dashboard tops work - max mac from history
$max = DB::table('food_inventory_cost_histories')
    ->where('inventory_item_id', 5152)
    ->orderByDesc('mac')
    ->limit(5)
    ->get(['id','warehouse_id','mac','new_cost','reference_type','reference_id','date','created_at']);
echo "TOP MAC HISTORY gomatare:\n".json_encode($max, JSON_PRETTY_PRINT)."\n";
