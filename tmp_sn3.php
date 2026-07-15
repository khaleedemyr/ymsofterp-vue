<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;

$itemId = 54683;
$sn = "2607141526F1IM";

// unit id 5 name
echo "unit_id 5 = ".DB::table("units")->where("id",5)->value("name")."\n";

// All in-stock serials for Marinate Beef warehouse 5
$serials = DB::table("inventory_item_serials")
  ->where("item_id", $itemId)
  ->where("warehouse_id", 5)
  ->where("is_out", 0)
  ->where("is_received", 0)
  ->orderByDesc("id")
  ->limit(20)
  ->get(["id","serial_number","generated_qty_unit","source_qty","unit_id","pack_mode","qty_per_pack","repack_qty","repack_unit_id","generated_at","source_type","source_id"]);
echo "available serials warehouse 5 count query:\n";
foreach($serials as $s){
  echo "{$s->serial_number} qty={$s->generated_qty_unit} unit={$s->unit_id} pack_mode={$s->pack_mode} src={$s->source_type}#{$s->source_id} at={$s->generated_at}\n";
}

// recently out for this item
$out = DB::table("inventory_item_serials")
  ->where("item_id", $itemId)
  ->where("is_out", 1)
  ->orderByDesc("out_at")
  ->limit(10)
  ->get(["serial_number","generated_qty_unit","out_at","out_delivery_order_id","warehouse_id"]);
echo "\nrecently OUT:\n";
foreach($out as $s) echo "{$s->serial_number} qty={$s->generated_qty_unit} do={$s->out_delivery_order_id} out={$s->out_at} wh={$s->warehouse_id}\n";

// packing list items with marinate beef recently?
echo "\n--- packing list items with item 54683 recent ---\n";
$plItems = DB::table("packing_list_items as pli")
  ->join("packing_lists as pl","pl.id","=","pli.packing_list_id")
  ->where("pli.item_id", $itemId)
  ->orderByDesc("pli.id")
  ->limit(15)
  ->get(["pli.id","pli.packing_list_id","pli.qty","pli.unit_id","pl.number","pl.status","pl.created_at","pl.warehouse_id"]);
foreach($plItems as $r) echo json_encode($r)."\n";
