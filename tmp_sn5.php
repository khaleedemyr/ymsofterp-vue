<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Support\InventorySerialEffectiveQty;

$sn = DB::table("inventory_item_serials as s")
  ->leftJoin("items as i","i.id","=","s.item_id")
  ->leftJoin("units as u","u.id","=","s.unit_id")
  ->leftJoin("units as ru","ru.id","=","s.repack_unit_id")
  ->leftJoin("units as su","su.id","=","i.small_unit_id")
  ->leftJoin("units as mu","mu.id","=","i.medium_unit_id")
  ->leftJoin("units as lu","lu.id","=","i.large_unit_id")
  ->where("s.serial_number","2607141526F1IM")
  ->select("s.*","i.name as item_name","u.name as unit_name","ru.name as repack_unit_name",
    "i.small_unit_id","i.medium_unit_id","i.large_unit_id","i.small_conversion_qty","i.medium_conversion_qty",
    "su.name as small_unit_name","mu.name as medium_unit_name","lu.name as large_unit_name")
  ->first();

$phys = InventorySerialEffectiveQty::resolve($sn);
$doc = InventorySerialEffectiveQty::resolveForDocumentUnit($sn, "Pack", InventorySerialEffectiveQty::itemUomFromRow($sn));
$scan = InventorySerialEffectiveQty::resolveForScan($sn);
echo "resolve=$phys docPack=$doc scan=".json_encode($scan)."\n";
echo "NOTE: generated_qty_unit={$sn->generated_qty_unit} but resolve ignores mk_production qty ? 1\n";

// production source 9708
$prod = DB::table("mk_productions")->where("id",9708)->first();
if(!$prod){
  // try detail
  $d = DB::table("mk_production_items")->where("id",9708)->first();
  echo "mk_production_items 9708: ".json_encode($d)."\n";
  $cols = collect(DB::select("SHOW TABLES LIKE '%mk_production%'"))->map(fn($r)=>array_values((array)$r)[0]);
  echo "tables: ".$cols->implode(",")."\n";
} else {
  echo "mk_productions: ".json_encode($prod)."\n";
}

// Count how many serials from batch 9708
$batch = DB::table("inventory_item_serials")->where("source_type","mk_production")->where("source_id",9708)->get(["serial_number","generated_qty_unit","is_out"]);
echo "batch 9708 serials:\n";
foreach($batch as $b) echo "  {$b->serial_number} qty={$b->generated_qty_unit} out={$b->is_out}\n";
