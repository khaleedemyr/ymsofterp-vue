<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = (int) ($argv[1] ?? 23);
$date = $argv[2] ?? '2026-06-27';
$foodPrice = 'COALESCE(fo.price, 0)';

$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
echo "Outlet [{$outletId}] {$outlet} | {$date}\n\n";

echo "=== Per GR: Outlet Payment (semua item) ===\n";
$rows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->groupBy('gr.id', 'gr.number')
    ->select('gr.id', 'gr.number', DB::raw("SUM(i.received_qty * {$foodPrice}) as total"))
    ->orderBy('gr.number')
    ->get();

$sumOp = 0.0;
foreach ($rows as $r) {
    $sumOp += (float) $r->total;
    echo sprintf("  %s id=%d => %s\n", $r->number, $r->id, number_format((float) $r->total, 2, '.', ','));
}
echo '  SUM OP: ' . number_format($sumOp, 2, '.', ',') . "\n\n";

echo "=== Per GR: Rekap FJ (w.name wajib ada) ===\n";
$rows2 = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->whereNotNull('w.name')
    ->groupBy('gr.id', 'gr.number')
    ->select('gr.id', 'gr.number', DB::raw("SUM(i.received_qty * {$foodPrice}) as total"))
    ->orderBy('gr.number')
    ->get();

$sumR = 0.0;
foreach ($rows2 as $r) {
    $sumR += (float) $r->total;
    echo sprintf("  %s id=%d => %s\n", $r->number, $r->id, number_format((float) $r->total, 2, '.', ','));
}
echo '  SUM Rekap: ' . number_format($sumR, 2, '.', ',') . "\n\n";

echo "=== Selisih per GR (OP - Rekap) ===\n";
$byId = [];
foreach ($rows as $r) {
    $byId[$r->id] = ['number' => $r->number, 'op' => (float) $r->total, 'rekap' => 0.0];
}
foreach ($rows2 as $r) {
    if (! isset($byId[$r->id])) {
        $byId[$r->id] = ['number' => $r->number, 'op' => 0.0, 'rekap' => 0.0];
    }
    $byId[$r->id]['rekap'] = (float) $r->total;
}
foreach ($byId as $id => $x) {
    $diff = $x['op'] - $x['rekap'];
    if (abs($diff) > 0.01) {
        echo sprintf("  %s id=%d | OP %s | Rekap %s | selisih %s\n",
            $x['number'], $id,
            number_format($x['op'], 2, '.', ','),
            number_format($x['rekap'], 2, '.', ','),
            number_format($diff, 2, '.', ',')
        );
    }
}

echo "\n=== Item di OP tapi tidak masuk Rekap (tanpa warehouse) ===\n";
$excl = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->whereNull('w.name')
    ->select(
        'gr.number',
        'it.name as item_name',
        'i.received_qty',
        DB::raw("({$foodPrice}) as unit_price"),
        DB::raw("SUM(i.received_qty * {$foodPrice}) as subtotal")
    )
    ->groupBy('gr.number', 'it.name', 'i.received_qty', 'fo.price')
    ->get();

if ($excl->isEmpty()) {
    echo "  (tidak ada)\n";
} else {
    foreach ($excl as $e) {
        echo sprintf("  %s | %s | qty %s x %s = %s\n",
            $e->number, $e->item_name, $e->received_qty,
            number_format((float) $e->unit_price, 2, '.', ','),
            number_format((float) $e->subtotal, 2, '.', ',')
        );
    }
}

echo "\n=== Item warehouse bukan MAIN STORE / MK (masuk Rekap total tapi bukan pivot MS) ===\n";
$otherWh = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->whereNotNull('w.name')
    ->whereNotIn('w.name', ['MK1 Hot Kitchen', 'MK2 Cold Kitchen', 'MAIN STORE'])
    ->select('w.name as warehouse', 'it.name as item_name', DB::raw("SUM(i.received_qty * {$foodPrice}) as subtotal"))
    ->groupBy('w.name', 'it.name')
    ->get();

if ($otherWh->isEmpty()) {
    echo "  (tidak ada)\n";
} else {
    foreach ($otherWh as $e) {
        echo sprintf("  %s | %s => %s\n", $e->warehouse, $e->item_name, number_format((float) $e->subtotal, 2, '.', ','));
    }
}

echo "\n=== COA breakdown (OP Tanpa CoA hint) ===\n";
$coa = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->leftJoin('coa_sub_categories as csc', 'sc.id', '=', 'csc.sub_category_id')
    ->leftJoin('coa as c', 'csc.coa_id', '=', 'c.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->select(
        DB::raw("COALESCE(CONCAT(c.code, ' - ', c.name), 'Tanpa CoA') as coa_label"),
        DB::raw("SUM(i.received_qty * {$foodPrice}) as subtotal")
    )
    ->groupBy('c.code', 'c.name')
    ->orderByDesc('subtotal')
    ->get();

foreach ($coa as $c) {
    echo sprintf("  %s => %s\n", $c->coa_label, number_format((float) $c->subtotal, 2, '.', ','));
}
