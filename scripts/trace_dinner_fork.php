<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$item = DB::table('items')->where('sku','ASSTS-20260429-2922')->first();
$inv = DB::table('asset_inventory_items')->where('item_id',$item->id)->first();
$stocks = DB::table('asset_inventory_stocks as s')
    ->join('tbl_data_outlet as o','s.owner_outlet_id','=','o.id_outlet')
    ->join('warehouse_outlets as w','s.warehouse_outlet_id','=','w.id')
    ->where('s.inventory_item_id',$inv->id)->get(['s.*','o.nama_outlet','w.name as wh']);
foreach ($stocks as $s) echo "stock#{$s->id} {$s->nama_outlet}/{$s->wh} qty={$s->qty_small}\n";

$cards = DB::table('asset_inventory_cards')->where('inventory_item_id',$inv->id)->orderBy('id')->get();
foreach ($cards as $c) echo "card#{$c->id} {$c->date} {$c->reference_type}#{$c->reference_id} owner={$c->owner_outlet_id} wh={$c->warehouse_outlet_id} in={$c->in_qty_small} out={$c->out_qty_small}\n";
