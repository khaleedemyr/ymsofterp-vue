<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 52337;
$from = '2026-07-01';
$to = '2026-07-31';

$item = DB::table('items as i')
    ->leftJoin('units as ul', 'ul.id', '=', 'i.large_unit_id')
    ->where('i.id', $itemId)
    ->select('i.*', 'ul.name as large_unit')
    ->first();

echo "=== BLACK PEPPER JULI 2026 ===\n";
echo "Item: {$item->name} | SKU: {$item->sku}\n\n";

echo "--- 1) FO lines (food_floor_order_items) Juli 2026 ---\n";
$foLines = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'fo.id_outlet')
    ->where('foi.item_id', $itemId)
    ->whereBetween('fo.tanggal', [$from, $to])
    ->orderBy('fo.tanggal')
    ->select(
        'fo.id as fo_id',
        'fo.order_number',
        'fo.tanggal',
        'fo.status',
        'fo.fo_mode',
        'o.nama_outlet',
        'foi.id as foi_id',
        'foi.unit',
        'foi.qty',
        'foi.price',
        'foi.subtotal',
        'foi.created_at',
        'foi.updated_at'
    )
    ->get();

if ($foLines->isEmpty()) {
    echo "(tidak ada baris FO Juli 2026)\n";
} else {
    foreach ($foLines as $r) {
        $impliedCost = round((float) $r->price / 1.12, 2);
        echo sprintf(
            "%s | %s | %s | %s | qty=%s %s | fo_price=%s | implied_cost=%s | foi_id=%s\n",
            $r->tanggal,
            $r->order_number,
            $r->status,
            $r->nama_outlet,
            $r->qty,
            $r->unit,
            number_format((float) $r->price, 2, '.', ','),
            number_format($impliedCost, 2, '.', ','),
            $r->foi_id
        );
    }
}

echo "\n--- 2) FO price distinct Juli 2026 ---\n";
$distinct = $foLines->groupBy(fn ($r) => (string) $r->price);
foreach ($distinct as $price => $rows) {
    $implied = round((float) $price / 1.12, 2);
    echo "price={$price} (÷1.12={$implied}) | count=" . $rows->count() . " | outlets: " . $rows->pluck('nama_outlet')->unique()->implode(', ') . "\n";
}

echo "\n--- 3) FO near 180000 (179500-180500) any month ---\n";
$near180 = DB::table('food_floor_order_items as foi')
    ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'fo.id_outlet')
    ->where('foi.item_id', $itemId)
    ->whereBetween('foi.price', [179500, 180500])
    ->orderByDesc('fo.tanggal')
    ->select('fo.order_number', 'fo.tanggal', 'o.nama_outlet', 'foi.price', 'foi.unit', 'foi.qty')
    ->get();
if ($near180->isEmpty()) {
    echo "TIDAK ADA fo.price = 180.000 di food_floor_order_items\n";
} else {
    foreach ($near180 as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n--- 4) Outlet GR Juli 2026 + FO price (rekap) ---\n";
$outletGr = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('units as u', 'i.unit_id', '=', 'u.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'fo.floor_order_id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('it.id', $itemId)
    ->whereBetween('gr.receive_date', [$from, $to])
    ->whereNull('gr.deleted_at')
    ->orderBy('gr.receive_date')
    ->select(
        'gr.id as outlet_gr_id',
        'gr.number as outlet_gr_number',
        'gr.receive_date',
        'o.nama_outlet',
        'w.name as warehouse',
        'i.received_qty',
        'u.name as unit',
        'fo.price as fo_price',
        'fo.id as foi_id',
        'ffo.order_number as fo_number',
        'ffo.tanggal as fo_tanggal'
    )
    ->get();

foreach ($outletGr as $r) {
    $implied = $r->fo_price ? round((float) $r->fo_price / 1.12, 2) : 0;
    echo sprintf(
        "%s | outlet_gr=%s | %s | wh=%s | qty=%s %s | fo=%s (%s) | fo_price=%s | implied_cost=%s | foi_id=%s\n",
        $r->receive_date,
        $r->outlet_gr_number ?? $r->outlet_gr_id,
        $r->nama_outlet,
        $r->warehouse,
        $r->received_qty,
        $r->unit,
        $r->fo_number,
        $r->fo_tanggal,
        $r->fo_price ?? '-',
        $r->fo_price ? number_format($implied, 2) : '-',
        $r->foi_id ?? '-'
    );
}

echo "\n--- 5) GR Pusat (Food GR) Juli 2026 ---\n";
$centralGr = DB::table('food_good_receive_items as gri')
    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
    ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
    ->leftJoin('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
    ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
    ->where('gri.item_id', $itemId)
    ->whereBetween('gr.receive_date', [$from, $to])
    ->orderBy('gr.receive_date')
    ->select('gr.gr_number', 'gr.receive_date', 'po.number as po_number', 'poi.price as po_price', 'u.name as unit')
    ->get();

foreach ($centralGr as $g) {
    $po = (float) $g->po_price;
    $sell = FloorOrderItemPriceResolver::roundUpToHundred($po * 1.12);
    echo sprintf(
        "%s | %s | PO %s | beli=%s/%s | sell+12%%=%s%s\n",
        $g->receive_date,
        $g->gr_number,
        $g->po_number ?? '-',
        number_format($po, 0, ',', '.'),
        $g->unit,
        number_format($sell, 0, ',', '.'),
        abs($po - 180000) < 1 ? '  <-- HPP 180rb' : (abs($po - 230000) < 1 ? '  <-- HPP 230rb' : '')
    );
}

echo "\n--- 6) Serial GR outlet Juli 2026 (jika ada) ---\n";
if (DB::getSchemaBuilder()->hasTable('outlet_serial_receive_items')) {
    $serial = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->select('h.number', 'h.receive_date', 'o.nama_outlet', 'si.qty', 'si.cost_small', 'u.name as unit', 'si.unit_id')
        ->get();
    if ($serial->isEmpty()) {
        echo "(tidak ada serial GR Juli)\n";
    } else {
        foreach ($serial as $s) {
            $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $s->unit);
            $costSmall = (float) $s->cost_small;
            $medium = $costSmall * (float) ($item->small_conversion_qty ?: 1);
            $large = $medium * (float) ($item->medium_conversion_qty ?: 1);
            $lineCost = match ($tier) {
                'large' => $large,
                'small' => $costSmall,
                default => $medium,
            };
            echo sprintf(
                "%s | %s | %s | qty=%s %s | cost_line≈%s | cost_small=%s\n",
                $s->receive_date,
                $s->number,
                $s->nama_outlet,
                $s->qty,
                $s->unit,
                number_format($lineCost, 2),
                number_format($costSmall, 4)
            );
        }
    }
}
