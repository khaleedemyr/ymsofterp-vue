<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$si = DB::table('outlet_serial_receive_items')->where('header_id', 1238)->where('item_id', 52985)->first();
$serial = DB::table('inventory_item_serials')->where('id', $si->serial_id)->first();
echo "serial out_outlet_id={$serial->out_outlet_id} cost_small={$serial->cost_small}\n";

// Simulate resolve at receive time - was pricing_mode ever auto?
$hist = DB::table('item_prices')->where('item_id', 52985)->get(['id','pricing_mode','updated_at']);
foreach ($hist as $h) {
    echo "price id={$h->id} mode=" . ($h->pricing_mode ?? 'null') . " updated={$h->updated_at}\n";
}
