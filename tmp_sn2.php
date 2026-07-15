<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;

$sn = "2607141526F1IM";

$cols = collect(DB::select("SHOW COLUMNS FROM inventory_item_serials"))->pluck("Field")->all();
echo "inventory_item_serials cols: ".implode(", ", $cols)."\n\n";

$row = DB::table("inventory_item_serials")->where("serial_number", $sn)->first();
if (!$row) {
  // try like
  $rows = DB::table("inventory_item_serials")->where("serial_number", "like", "%$sn%")->limit(5)->get();
  echo "like matches=".count($rows)."\n";
  echo json_encode($rows, JSON_PRETTY_PRINT)."\n";
} else {
  echo "FOUND inventory_item_serials:\n".json_encode($row, JSON_PRETTY_PRINT)."\n";
  $item = DB::table("items")->where("id", $row->item_id)->first();
  echo "item: ".json_encode($item?->name)." id=".$row->item_id."\n";
  if (isset($item->small_unit_id)) {
    $su = DB::table("units")->where("id",$item->small_unit_id)->value("name");
    $mu = DB::table("units")->where("id",$item->medium_unit_id)->value("name");
    $lu = DB::table("units")->where("id",$item->large_unit_id)->value("name");
    echo "units small=$su med=$mu large=$lu\n";
    echo "conv small=".$item->small_conversion_qty." med=".$item->medium_conversion_qty."\n";
  }
}

// movements
$mov = DB::table("inventory_serial_movements")->where("serial_number", $sn)->orderByDesc("id")->limit(10)->get();
echo "\nmovements=".count($mov)."\n";
foreach($mov as $m) echo json_encode($m)."\n";

// check if already on DO
$doTables = ["delivery_order_serial_items","delivery_order_item_serials","delivery_orders_serials"];
foreach(["delivery_order_serial_items","delivery_order_item_serials"] as $t) {
  if (Schema::hasTable($t)) {
    $c = DB::table($t)->where("serial_number",$sn)->count();
    echo "$t count=$c\n";
  }
}
