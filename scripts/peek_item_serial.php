<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$name = $argv[1] ?? 'Label Preparation Roll';
$item = DB::table('items')->where('name', $name)->first();
if (! $item) {
    echo "Not found\n";
    exit(1);
}

echo json_encode($item, JSON_PRETTY_PRINT) . "\n\n";
$ip = DB::table('item_prices')->where('item_id', $item->id)->get();
foreach ($ip as $p) {
    echo json_encode($p) . "\n";
}

$rows = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
    ->where('si.item_id', $item->id)
    ->whereBetween('h.receive_date', ['2026-06-01', '2026-06-30'])
    ->selectRaw('si.id, h.receive_date, h.outlet_id, si.qty, si.cost_small, si.unit_id, u.name as unit_name')
    ->orderBy('h.receive_date')
    ->limit(10)
    ->get();

echo "\nSerial samples:\n";
foreach ($rows as $r) {
    echo json_encode($r) . "\n";
}

$dist = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
    ->where('si.item_id', $item->id)
    ->whereBetween('h.receive_date', ['2026-06-01', '2026-06-30'])
    ->selectRaw('si.cost_small, COUNT(*) cnt, SUM(si.qty) qty_sum')
    ->groupBy('si.cost_small')
    ->get();
echo "\nCost distribution:\n";
foreach ($dist as $d) {
    echo "cost_small={$d->cost_small} cnt={$d->cnt} qty={$d->qty_sum}\n";
}
