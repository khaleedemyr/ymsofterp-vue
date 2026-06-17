<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$header = DB::table('outlet_serial_receive_headers')
    ->where('number', 'GSR-20260613-0130')
    ->first();

$items = DB::table('outlet_serial_receive_items')->where('header_id', $header->id)->where('item_id', 52985)->get();
echo "Header {$header->number} outlet_id={$header->outlet_id} items=" . $items->count() . "\n";
foreach ($items->take(5) as $si) {
    echo "  cost_small={$si->cost_small} source={$si->cost_source} si.outlet_id={$si->outlet_id}\n";
}

$dist = DB::table('outlet_serial_receive_items')
    ->where('header_id', $header->id)
    ->where('item_id', 52985)
    ->select('cost_small', 'cost_source', DB::raw('count(*) c'))
    ->groupBy('cost_small', 'cost_source')
    ->get();
echo "\nDistribution:\n";
foreach ($dist as $d) {
    echo "  {$d->cost_small} {$d->cost_source} x{$d->c}\n";
}
