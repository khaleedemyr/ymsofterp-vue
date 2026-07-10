<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = 54667;
$outletId = 18;
$outletName = 'Justus Steak House Buah Batu';
$from = '2026-06-01';
$to = '2026-07-31';

echo "=== Barbeque Sauce (item {$itemId}) trace ===\n\n";

$item = DB::table('items')->where('id', $itemId)->first();
echo "Item: {$item->name}\n";
echo "Units: small={$item->small_unit_id}, medium={$item->medium_unit_id}, large={$item->large_unit_id}\n";
echo "Conv: medium={$item->medium_conversion_qty}, small={$item->small_conversion_qty}\n\n";

$units = DB::table('units')->whereIn('id', [$item->small_unit_id, $item->medium_unit_id, $item->large_unit_id])
    ->pluck('name', 'id');
foreach ($units as $id => $name) {
    echo "  unit {$id}: {$name}\n";
}

echo "\n--- item_prices ---\n";
foreach (DB::table('item_prices')->where('item_id', $itemId)->get() as $p) {
    echo "  {$p->availability_price_type} region={$p->region_id} outlet={$p->outlet_id} price={$p->price} mode={$p->pricing_mode}\n";
}

$priceLarge = 118552.0;
$packPrice = round($priceLarge / (float) $item->medium_conversion_qty, 2);
$mlPrice = round($priceLarge / ((float) $item->medium_conversion_qty * (float) $item->small_conversion_qty), 2);
echo "\nDerived from item_prices large={$priceLarge}:\n";
echo "  per Pack (medium): {$packPrice}\n";
echo "  per Mili liter (small): {$mlPrice}\n";

echo "\n--- GR Food + FO price (last 15) ---\n";
$foodRows = DB::table('outlet_food_good_receive_items as i')
    ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('units as u', 'i.unit_id', '=', 'u.id')
    ->where('i.item_id', $itemId)
    ->where('gr.outlet_id', $outletId)
    ->whereNull('gr.deleted_at')
    ->orderByDesc('gr.receive_date')
    ->limit(15)
    ->select(
        'gr.receive_date',
        'gr.number as gr_number',
        'do.number as do_number',
        'i.received_qty',
        'u.name as gr_unit',
        'fo.price as fo_price',
        'fo.qty as fo_qty',
        DB::raw('i.received_qty * COALESCE(fo.price,0) as subtotal')
    )
    ->get();

foreach ($foodRows as $r) {
    echo "  {$r->receive_date} GR={$r->gr_number} DO={$r->do_number} qty={$r->received_qty} {$r->gr_unit} fo_price={$r->fo_price} subtotal={$r->subtotal}\n";
}

echo "\n--- GR Serial (last 15) ---\n";
$serialRows = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->leftJoin('inventory_item_serials as s', 's.id', '=', 'si.serial_id')
    ->where('si.item_id', $itemId)
    ->where('h.outlet_id', $outletId)
    ->whereNull('h.deleted_at')
    ->orderByDesc('h.receive_date')
    ->limit(15)
    ->select(
        'h.receive_date',
        'h.number as gr_number',
        'si.qty',
        'u.name as unit',
        'si.unit_id',
        'si.cost_small',
        's.serial_number',
        's.cost_small as serial_cost_small',
        's.source_type'
    )
    ->get();

$smallConv = (float) ($item->small_conversion_qty ?: 1);
$mediumConv = (float) ($item->medium_conversion_qty ?: 1);

foreach ($serialRows as $r) {
    $costSmall = (float) ($r->cost_small ?? 0);
    $eff = $costSmall;
    if ((int) $r->unit_id === (int) $item->large_unit_id) {
        $eff = $costSmall * $smallConv * $mediumConv;
    } elseif ((int) $r->unit_id === (int) $item->medium_unit_id) {
        $eff = $costSmall * $smallConv;
    }
    echo "  {$r->receive_date} GR={$r->gr_number} serial={$r->serial_number} qty={$r->qty} {$r->unit} cost_small={$r->cost_small} effective={$eff} src={$r->source_type}\n";
}

echo "\n--- Rekap FJ MK detail ({$from}..{$to}) ---\n";
$mkWarehouses = ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'];
$detail = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('categories as cat', 'it.category_id', '=', 'cat.id')
    ->join('units as u', 'i.unit_id', '=', 'u.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $outletName)
    ->where('it.id', $itemId)
    ->whereDate('gr.receive_date', '>=', $from)
    ->whereDate('gr.receive_date', '<=', $to)
    ->whereNull('gr.deleted_at')
    ->whereIn('w.name', $mkWarehouses)
    ->select(
        'it.name as item_name',
        'u.name as unit',
        DB::raw('SUM(i.received_qty) as received_qty'),
        DB::raw('AVG(COALESCE(fo.price,0)) as price'),
        DB::raw('SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal')
    )
    ->groupBy('it.name', 'u.name')
    ->first();

if ($detail) {
    echo "  qty={$detail->received_qty} unit={$detail->unit} avg_price={$detail->price} subtotal={$detail->subtotal}\n";
    if ((float) $detail->received_qty > 0) {
        echo '  implied unit price: ' . round((float) $detail->subtotal / (float) $detail->received_qty, 2) . "\n";
    }
} else {
    echo "  No MK detail row (warehouse_division kosong?)\n";
}

echo "\n--- GR tanpa filter warehouse ({$from}..{$to}) ---\n";
$allWh = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('units as u', 'i.unit_id', '=', 'u.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', $outletName)
    ->where('it.id', $itemId)
    ->whereDate('gr.receive_date', '>=', $from)
    ->whereDate('gr.receive_date', '<=', $to)
    ->whereNull('gr.deleted_at')
    ->select(
        'w.name as warehouse',
        'u.name as unit',
        DB::raw('SUM(i.received_qty) as received_qty'),
        DB::raw('AVG(COALESCE(fo.price,0)) as price'),
        DB::raw('SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal')
    )
    ->groupBy('w.name', 'u.name')
    ->get();
foreach ($allWh as $row) {
    echo "  warehouse=" . ($row->warehouse ?? 'NULL') . " qty={$row->received_qty} avg_price={$row->price} subtotal={$row->subtotal}\n";
}

echo "\n--- Monthly implied price Buah Batu ---\n";
$monthly = DB::select("
    SELECT DATE_FORMAT(gr.receive_date,'%Y-%m') as periode,
           SUM(i.received_qty) as qty,
           SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal
    FROM outlet_food_good_receives gr
    JOIN outlet_food_good_receive_items i ON gr.id = i.outlet_food_good_receive_id
    JOIN delivery_orders do ON gr.delivery_order_id = do.id
    LEFT JOIN food_floor_order_items fo ON i.item_id = fo.item_id AND fo.floor_order_id = do.floor_order_id
    JOIN tbl_data_outlet o ON gr.outlet_id = o.id_outlet
    WHERE i.item_id = ?
      AND o.nama_outlet = ?
      AND gr.deleted_at IS NULL
    GROUP BY periode
    ORDER BY periode DESC
    LIMIT 18
", [$itemId, $outletName]);

foreach ($monthly as $r) {
    $p = (float) $r->qty > 0 ? round((float) $r->subtotal / (float) $r->qty, 2) : 0;
    echo "  {$r->periode} qty={$r->qty} subtotal={$r->subtotal} implied_price={$p}\n";
}

echo "\n--- FO lines price=118600 (audit trail) ---\n";
$wrongFo = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('ffoi.item_id', $itemId)
    ->where('ffoi.price', 118600)
    ->select(
        'ffoi.id',
        'ffo.order_number',
        'ffo.tanggal',
        'ffo.status',
        'ffo.created_at as fo_created',
        'ffoi.updated_at as line_updated',
        'ffoi.created_at as line_created',
        'o.nama_outlet'
    )
    ->orderBy('ffo.tanggal')
    ->get();
foreach ($wrongFo as $r) {
    $line = DB::table('food_floor_order_items')->where('id', $r->id)->first(['unit', 'qty', 'price']);
    echo "  {$r->tanggal} {$r->order_number} [{$r->status}] {$r->nama_outlet} unit={$line->unit} qty={$line->qty} price={$line->price} line_updated={$r->line_updated}\n";
}

$windows = DB::select("
    SELECT gr.receive_date, i.received_qty, fo.price, (i.received_qty * COALESCE(fo.price,0)) as line_sub
    FROM outlet_food_good_receives gr
    JOIN outlet_food_good_receive_items i ON gr.id = i.outlet_food_good_receive_id
    JOIN delivery_orders do ON gr.delivery_order_id = do.id
    LEFT JOIN food_floor_order_items fo ON i.item_id = fo.item_id AND fo.floor_order_id = do.floor_order_id
    WHERE gr.outlet_id = ? AND i.item_id = ? AND gr.deleted_at IS NULL
    ORDER BY gr.receive_date DESC
    LIMIT 200
", [$outletId, $itemId]);

$cumQty = 0;
$cumSub = 0;
$targetQty = 64;
foreach ($windows as $r) {
    $cumQty += (float) $r->received_qty;
    $cumSub += (float) $r->line_sub;
    if (abs($cumQty - $targetQty) < 0.01) {
        echo "  Rolling last to {$r->receive_date}: qty={$cumQty} subtotal={$cumSub} price=" . round($cumSub / $cumQty, 2) . "\n";
        break;
    }
}
