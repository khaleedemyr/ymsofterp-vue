<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$h = DB::table('outlet_serial_receive_headers')->where('number', 'GSR-20260916-0017')->first();
echo "header: ".json_encode($h, JSON_PRETTY_PRINT)."\n\n";

$rows = DB::table('outlet_serial_receive_items as si')
    ->leftJoin('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->where('si.header_id', $h->id)
    ->get([
        'si.id',
        'si.item_id',
        'it.name as item_name',
        'si.qty',
        'si.unit_id',
        'u.name as unit_name',
        'si.serial_number',
        'si.delivery_order_id',
        'si.warehouse_outlet_id',
    ]);

echo "items count=".$rows->count()."\n";
foreach ($rows as $r) {
    echo json_encode($r)."\n";
}

$cols = Illuminate\Support\Facades\Schema::getColumnListing('outlet_serial_receive_items');
echo "\ncols: ".implode(', ', $cols)."\n";
