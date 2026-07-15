<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
use Illuminate\Support\Facades\DB;

// sibling that is out
$eq = DB::table("inventory_item_serials")->where("serial_number","2607141526EQHU")->first();
echo "EQHU DO id={$eq->out_delivery_order_id} out_at={$eq->out_at}\n";
$do = DB::table("delivery_orders")->where("id",$eq->out_delivery_order_id)->first();
echo "DO: ".json_encode($do)."\n";

// How were serials created in MKProduction for qty_jadi 2?
// Read controller logic briefly via grep would be better - for now check all marinate serials from same day with qty=2
echo "\nSample: are ALL marinate SN qty=2 while production qty_jadi is per batch?\n";
$samples = DB::table("inventory_item_serials as s")
  ->join("mk_productions as p","p.id","=","s.source_id")
  ->where("s.source_type","mk_production")
  ->where("s.item_id",54683)
  ->whereDate("s.generated_at","2026-07-14")
  ->selectRaw("p.id pid, p.qty, p.qty_jadi, count(s.id) sn_count, avg(s.generated_qty_unit) avg_sn_qty")
  ->groupBy("p.id","p.qty","p.qty_jadi")
  ->orderByDesc("p.id")
  ->limit(10)
  ->get();
foreach($samples as $r) echo json_encode($r)."\n";
