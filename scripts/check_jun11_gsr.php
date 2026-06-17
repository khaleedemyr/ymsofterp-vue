<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$headers = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->where('h.outlet_id', 20)
    ->where('si.item_id', 52985)
    ->whereDate('h.receive_date', '2026-06-11')
    ->select('h.number', 'si.cost_small', 'si.cost_source', 'si.created_at', 'si.updated_at')
    ->get();
foreach ($headers as $h) {
    echo "{$h->number} cost={$h->cost_small} src={$h->cost_source} created={$h->created_at} updated={$h->updated_at}\n";
}
