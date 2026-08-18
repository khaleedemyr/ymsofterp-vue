<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function money($n): string
{
    return number_format((float) $n, 2, ',', '.');
}

$itemId = 53088;
$itemName = 'Cabe Rawit Merah';

echo "=== GR for anomalous PO lines ===\n";
foreach ([22375, 4159] as $poiId) {
    $poi = DB::table('purchase_order_food_items as poi')
        ->join('purchase_order_foods as po', 'po.id', '=', 'poi.purchase_order_food_id')
        ->where('poi.id', $poiId)
        ->first(['poi.*', 'po.number', 'po.date', 'po.status']);
    echo "poi={$poiId} PO={$poi->number} date={$poi->date} status={$poi->status} qty={$poi->quantity} unit_id={$poi->unit_id} price={$poi->price} total={$poi->total}\n";
    $grs = DB::table('food_good_receive_items as gri')
        ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
        ->where('gri.po_item_id', $poiId)
        ->get();
    echo "  GR rows: {$grs->count()}\n";
    foreach ($grs as $g) {
        echo '  ' . json_encode($g) . "\n";
    }
}

echo "\n=== FO distinct 2026 ===\n";
$foPrices = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->where('foi.item_id', $itemId)
    ->where('fo.tanggal', '>=', '2026-01-01')
    ->select('foi.price', 'foi.unit', DB::raw('count(*) as cnt'), DB::raw('min(fo.tanggal) as first_date'), DB::raw('max(fo.tanggal) as last_date'))
    ->groupBy('foi.price', 'foi.unit')
    ->orderByDesc('cnt')
    ->get();
foreach ($foPrices as $p) {
    echo sprintf("price=%s unit=%s cnt=%s first=%s last=%s\n", money($p->price), $p->unit, $p->cnt, $p->first_date, $p->last_date);
}

echo "\n=== FO 2026 price > 1000 or < 5 ===\n";
$foAnom = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'fo.id_outlet')
    ->where('foi.item_id', $itemId)
    ->where('fo.tanggal', '>=', '2026-01-01')
    ->where(function ($q) {
        $q->where('foi.price', '>', 1000)->orWhere('foi.price', '<', 5);
    })
    ->orderByDesc('fo.tanggal')
    ->limit(40)
    ->get(['fo.order_number', 'fo.tanggal', 'fo.status', 'foi.price', 'foi.unit', 'o.nama_outlet']);
foreach ($foAnom as $n) {
    echo sprintf("%s %s status=%s outlet=%s unit=%s price=%s\n", $n->tanggal, $n->order_number, $n->status, $n->nama_outlet ?? '-', $n->unit, money($n->price));
}
if ($foAnom->isEmpty()) {
    echo "(tidak ada)\n";
}

echo "\n=== Retail food 2026 distinct ===\n";
$rfDistinct = DB::table('retail_food_items as rfi')
    ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
    ->where('rfi.item_name', $itemName)
    ->where('rf.transaction_date', '>=', '2026-01-01')
    ->select('rfi.price', 'rfi.unit', DB::raw('count(*) as cnt'), DB::raw('min(rf.transaction_date) as first_date'), DB::raw('max(rf.transaction_date) as last_date'))
    ->groupBy('rfi.price', 'rfi.unit')
    ->orderByDesc('cnt')
    ->get();
foreach ($rfDistinct as $d) {
    echo sprintf("price=%s unit=%s cnt=%s first=%s last=%s\n", money($d->price), $d->unit, $d->cnt, $d->first_date, $d->last_date);
}

echo "\n=== Retail food last 15 ===\n";
$rf = DB::table('retail_food_items as rfi')
    ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
    ->where('rfi.item_name', $itemName)
    ->orderByDesc('rf.transaction_date')
    ->limit(15)
    ->get(['rf.id as rf_id', 'rf.retail_number', 'rf.transaction_date', 'rf.status', 'rfi.qty', 'rfi.unit', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
foreach ($rf as $row) {
    echo sprintf("%s %s %s outlet=%s qty=%s unit=%s price=%s subtotal=%s\n", $row->transaction_date, $row->retail_number, $row->status, $row->nama_outlet, money($row->qty), $row->unit, money($row->price), money($row->subtotal));
}

echo "\n=== ANOMALI retail 2026 ===\n";
$rfAll = DB::table('retail_food_items as rfi')
    ->join('retail_food as rf', 'rf.id', '=', 'rfi.retail_food_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'rf.outlet_id')
    ->where('rfi.item_name', $itemName)
    ->where('rf.transaction_date', '>=', '2026-01-01')
    ->get(['rf.id as rf_id', 'rf.retail_number', 'rf.transaction_date', 'rf.status', 'rfi.qty', 'rfi.unit', 'rfi.price', 'rfi.subtotal', 'o.nama_outlet']);
$rfAnom = 0;
foreach ($rfAll as $row) {
    $price = (float) $row->price;
    $unit = strtolower((string) $row->unit);
    $isGram = str_contains($unit, 'gram') && ! str_contains($unit, 'kg');
    $isKg = str_contains($unit, 'kg') || $unit === 'kilogram';
    $isAnom = ($isGram && ($price > 1000 || ($price > 0 && $price < 5))) || ($isKg && ($price < 5000 || $price > 300000));
    if (! $isAnom) {
        continue;
    }
    $rfAnom++;
    echo sprintf("ANOMALI %s %s outlet=%s qty=%s unit=%s price=%s subtotal=%s %s rf=%s\n", $row->transaction_date, $row->retail_number, $row->nama_outlet, money($row->qty), $row->unit, money($row->price), money($row->subtotal), $row->status, $row->rf_id);
}
echo $rfAnom === 0 ? "(tidak ada)\n" : "total anomali={$rfAnom}\n";

echo "\n=== GROS last 15 ===\n";
$hasGrNumber = Schema::hasColumn('good_receive_outlet_suppliers', 'gr_number');
$gros = DB::table('good_receive_outlet_supplier_items as gri')
    ->join('good_receive_outlet_suppliers as gr', 'gr.id', '=', 'gri.good_receive_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'gr.outlet_id')
    ->where('gri.item_id', $itemId)
    ->orderByDesc('gr.receive_date')
    ->limit(15)
    ->get();
foreach ($gros as $row) {
    echo sprintf("%s id=%s outlet=%s qty=%s unit=%s price=%s\n", $row->receive_date, $row->id ?? $row->good_receive_id, $row->nama_outlet ?? '-', $row->qty_received ?? '-', $row->unit_id ?? '-', money($row->price ?? 0));
}

echo "\n=== GROS distinct price 2026 ===\n";
$grosD = DB::table('good_receive_outlet_supplier_items as gri')
    ->join('good_receive_outlet_suppliers as gr', 'gr.id', '=', 'gri.good_receive_id')
    ->where('gri.item_id', $itemId)
    ->where('gr.receive_date', '>=', '2026-01-01')
    ->select('gri.price', 'gri.unit_id', DB::raw('count(*) as cnt'), DB::raw('min(gr.receive_date) as first_date'), DB::raw('max(gr.receive_date) as last_date'))
    ->groupBy('gri.price', 'gri.unit_id')
    ->orderByDesc('cnt')
    ->get();
foreach ($grosD as $d) {
    echo sprintf("price=%s unit_id=%s cnt=%s first=%s last=%s\n", money($d->price), $d->unit_id, $d->cnt, $d->first_date, $d->last_date);
}

echo "\n=== GROS anomali cost/small >1000 or <5 ===\n";
$item = DB::table('items')->where('id', $itemId)->first();
$units = DB::table('units')->pluck('name', 'id');
$grosAll = DB::table('good_receive_outlet_supplier_items as gri')
    ->join('good_receive_outlet_suppliers as gr', 'gr.id', '=', 'gri.good_receive_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'gr.outlet_id')
    ->where('gri.item_id', $itemId)
    ->where('gr.receive_date', '>=', '2026-01-01')
    ->get();
$gAnom = 0;
foreach ($grosAll as $row) {
    $price = (float) $row->price;
    $unitId = (int) $row->unit_id;
    $costSmall = $price;
    if ($unitId === (int) $item->large_unit_id) {
        $costSmall = $price / 1000;
    } elseif ($unitId === (int) $item->medium_unit_id) {
        $costSmall = $price / 1000;
    }
    if (! ($costSmall > 1000 || ($costSmall > 0 && $costSmall < 5))) {
        continue;
    }
    $gAnom++;
    echo sprintf("ANOMALI %s outlet=%s qty=%s unit=%s price=%s cost/small=%s\n", $row->receive_date, $row->nama_outlet ?? '-', $row->qty_received, $units[$unitId] ?? $unitId, money($price), money($costSmall));
}
echo $gAnom === 0 ? "(tidak ada)\n" : "total={$gAnom}\n";

echo "\n=== Cost history WH5 2026 min/max + anomali ===\n";
$inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
if ($inv) {
    $stats = DB::table('food_inventory_cost_histories')
        ->where('inventory_item_id', $inv->id)
        ->where('warehouse_id', 5)
        ->where('date', '>=', '2026-01-01')
        ->selectRaw('min(mac) min_mac, max(mac) max_mac, avg(mac) avg_mac, min(new_cost) min_cost, max(new_cost) max_cost')
        ->first();
    echo json_encode($stats) . "\n";
    $anom = DB::table('food_inventory_cost_histories')
        ->where('inventory_item_id', $inv->id)
        ->where('date', '>=', '2026-01-01')
        ->where(function ($q) {
            $q->where('new_cost', '>', 1000)->orWhere('mac', '>', 1000);
        })
        ->orderByDesc('date')
        ->limit(20)
        ->get();
    echo "anomali rows: {$anom->count()}\n";
    foreach ($anom as $c) {
        echo sprintf("%s wh=%s type=%s new_cost=%s mac=%s ref=%s#%s\n", $c->date, $c->warehouse_id, $c->type, money($c->new_cost), money($c->mac), $c->reference_type, $c->reference_id);
    }
}

echo "\nDONE\n";
