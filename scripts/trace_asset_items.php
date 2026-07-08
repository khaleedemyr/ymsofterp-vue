<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

function dumpItems($table, $fk, $id, $label) {
    $rows = DB::table($table)->where($fk, $id)->get();
    echo "\n{$label} items:\n";
    foreach ($rows as $r) {
        $name = DB::table('items')->where('id', $r->item_id ?? 0)->value('name');
        echo json_encode(array_merge(['item_name'=>$name], (array)$r), JSON_UNESCAPED_UNICODE) . "\n";
    }
}

$adp = [1,2]; foreach ($adp as $id) { $d=DB::table('asset_disposals')->where('id',$id)->first(); echo "\nADP id={$id} ".json_encode($d)."\n"; dumpItems('asset_disposal_items','disposal_id',$id,'ADP'); }
$asa = [1,2,3,4,5]; foreach ($asa as $id) { $a=DB::table('asset_inventory_adjustments')->where('id',$id)->first(); echo "\nASA id={$id} ".json_encode($a)."\n"; dumpItems('asset_inventory_adjustment_items','adjustment_id',$id,'ASA'); }
$aot = [5,10,11]; foreach ($aot as $id) { $t=DB::table('asset_owner_transfers')->where('id',$id)->first(); echo "\nAOT id={$id} ".json_encode($t)."\n"; dumpItems('asset_owner_transfer_items','asset_owner_transfer_id',$id,'AOT'); }
$ait = [5,6]; foreach ($ait as $id) { $t=DB::table('asset_inventory_transfers')->where('id',$id)->first(); echo "\nAIT id={$id} ".json_encode($t)."\n"; dumpItems('asset_inventory_transfer_items','asset_inventory_transfer_id',$id,'AIT'); }

echo "\n--- Current stocks for target SKUs ---\n";
$skus = ['ASSTS-20260429-5823','ASSTK-20260429-9039','ASSTK-20260429-3742','ASSTS-20260429-2922'];
foreach ($skus as $sku) {
    $item = DB::table('items')->where('sku',$sku)->first();
    $inv = DB::table('asset_inventory_items')->where('item_id',$item->id)->first();
    $stocks = DB::table('asset_inventory_stocks as s')
        ->join('tbl_data_outlet as o','s.owner_outlet_id','=','o.id_outlet')
        ->join('warehouse_outlets as w','s.warehouse_outlet_id','=','w.id')
        ->where('s.inventory_item_id',$inv->id)
        ->get(['s.*','o.nama_outlet','w.name as wh']);
    foreach ($stocks as $s) {
        echo "{$sku} stock#{$s->id} {$s->nama_outlet}/{$s->wh} qty={$s->qty_small}\n";
    }
}
