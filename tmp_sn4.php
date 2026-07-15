<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Support\InventorySerialEffectiveQty;

$cols = collect(DB::select("SHOW COLUMNS FROM packing_list_items"))->pluck("Field")->all();
echo "pli cols: ".implode(",",$cols)."\n";

$sn = DB::table("inventory_item_serials as s")
  ->leftJoin("units as u","u.id","=","s.unit_id")
  ->leftJoin("units as ru","ru.id","=","s.repack_unit_id")
  ->where("s.serial_number","2607141526F1IM")
  ->select("s.*","u.name as unit_name","ru.name as repack_unit_name")
  ->first();

$eff = InventorySerialEffectiveQty::forSerialRow($sn);
echo "effective qty helper = ".json_encode($eff)."\n";
echo "unit_name={$sn->unit_name} generated={$sn->generated_qty_unit} source={$sn->source_qty} pack_mode={$sn->pack_mode}\n";

// Duplicate item lines in open packing lists?
$recent = DB::table("packing_list_items as pli")
  ->join("packing_lists as pl","pl.id","=","pli.packing_list_id")
  ->where("pli.item_id", 54683)
  ->orderByDesc("pli.id")
  ->limit(20)
  ->get();
echo "recent pli rows:\n";
foreach($recent as $r) echo json_encode($r)."\n";
