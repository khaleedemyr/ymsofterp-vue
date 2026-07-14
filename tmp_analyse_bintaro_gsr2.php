<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 23;
$date = '2026-07-12';
$serialPrice = "(CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)";

$fmt = fn ($n) => number_format((float) $n, 2, ',', '.');

// Per GSR: items by warehouse
$headers = DB::table('outlet_serial_receive_headers')
    ->where('outlet_id', $outletId)
    ->whereDate('receive_date', $date)
    ->whereNull('deleted_at')
    ->orderBy('number')
    ->get(['id', 'number']);

echo "=== Per GSR item warehouse check ===\n";
foreach ($headers as $h) {
    $rows = DB::table('outlet_serial_receive_items as si')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->where('si.header_id', $h->id)
        ->select(
            'it.id',
            'it.name',
            'w.name as warehouse',
            'sc.name as sub_category',
            DB::raw("SUM(si.qty * {$serialPrice}) as total")
        )
        ->groupBy('it.id', 'it.name', 'w.name', 'sc.name')
        ->get();

    $sum = $rows->sum('total');
    $withWh = $rows->filter(fn ($r) => !empty($r->warehouse))->sum('total');
    $noWh = $rows->filter(fn ($r) => empty($r->warehouse))->sum('total');
    echo "{$h->number}: total={$fmt($sum)} withWH={$fmt($withWh)} noWH={$fmt($noWh)}\n";
    if ($noWh > 0.01 || abs($sum - $withWh) > 0.01) {
        foreach ($rows->filter(fn ($r) => empty($r->warehouse)) as $r) {
            echo "   NO-WH #{$r->id} {$r->name}: {$fmt($r->total)}\n";
        }
    }
    // show warehouses
    $byW = $rows->groupBy(fn ($r) => $r->warehouse ?: '(null)');
    foreach ($byW as $wn => $list) {
        echo "   WH [{$wn}]: {$fmt($list->sum('total'))}\n";
    }
}

echo "\n=== Focus GSR-0114 items ===\n";
$g114 = DB::table('outlet_serial_receive_headers')->where('number', 'GSR-20260712-0114')->first();
$items = DB::table('outlet_serial_receive_items as si')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->where('si.header_id', $g114->id)
    ->select(
        'it.id', 'it.name', 'w.name as warehouse', 'sc.name as sub_category',
        'u.name as unit', 'si.qty', 'si.cost_small', 'si.unit_id',
        'it.small_unit_id', 'it.medium_unit_id', 'it.large_unit_id',
        'it.small_conversion_qty', 'it.medium_conversion_qty',
        DB::raw("({$serialPrice}) as unit_price"),
        DB::raw("si.qty * ({$serialPrice}) as subtotal")
    )
    ->get();
foreach ($items as $i) {
    echo "#{$i->id} {$i->name} | WH={$i->warehouse} | sc={$i->sub_category} | qty={$i->qty} {$i->unit} cost_small={$i->cost_small} unit_price={$fmt($i->unit_price)} sub={$fmt($i->subtotal)}\n";
    echo "   unit_id={$i->unit_id} S/M/L={$i->small_unit_id}/{$i->medium_unit_id}/{$i->large_unit_id} conv={$i->small_conversion_qty}/{$i->medium_conversion_qty}\n";
}

// Check if retail merged into export
echo "\n=== Does printed rekap include retail? ===\n";
echo "My GR+GSR rekap line: 39.709.376\n";
echo "Screenshot rekap line: 39.929.216\n";
echo "Diff (= retail?): " . number_format(39929216 - 39709376, 0, ',', '.') . "\n";
echo "OP - Screenshot Rekap: " . number_format(40982016 - 39929216, 0, ',', '.') . "\n";
echo "GSR-0114 total: " . number_format(1052800, 0, ',', '.') . "\n";
