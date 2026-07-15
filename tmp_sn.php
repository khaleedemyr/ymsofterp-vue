<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sn = "2607141526F1IM";
echo "=== SERIAL LOOKUP: $sn ===\n";

// Find tables with serial-like columns
$tables = [
  "item_serials", "item_serial_numbers", "serial_numbers", "serial_items",
  "stock_serials", "warehouse_item_serials", "inventory_serials",
  "outlet_serial_receive_items", "delivery_order_serials", "delivery_order_item_serials",
  "item_serial_stocks", "serials"
];
foreach ($tables as $t) {
  if (Schema::hasTable($t)) echo "HAS TABLE $t\n";
}

// Search common patterns
$candidates = DB::select("SHOW TABLES LIKE '%serial%'");
foreach ($candidates as $row) {
  $vals = array_values((array)$row);
  echo "table: ".$vals[0]."\n";
}
