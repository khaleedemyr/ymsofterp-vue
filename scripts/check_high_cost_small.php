<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$rows = DB::table('outlet_serial_receive_items')
    ->where('item_id', 52985)
    ->where('cost_small', '>', 50000)
    ->select('cost_small', 'cost_source', DB::raw('count(*) c'))
    ->groupBy('cost_small', 'cost_source')
    ->get();
foreach ($rows as $r) {
    echo "cost_small={$r->cost_small} src={$r->cost_source} count={$r->c}\n";
}
