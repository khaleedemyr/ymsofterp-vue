<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\SerialReceiveItemPriceResolver;

$item = \Illuminate\Support\Facades\DB::table('items')->where('id', 52985)->first();
$serial = (object) [
    'item_id' => 52985,
    'cost_small' => 175.19,
    'out_outlet_id' => '20',
    'serial_number' => 'TEST',
    'source_type' => 'warehouse_sale',
];

[$cost, $key, $label] = SerialReceiveItemPriceResolver::resolveCostSmall(52985, $item, $serial, '20');
echo "Manual resolve: cost_small={$cost} key={$key} label={$label}\n";
echo ($cost == 448 && $key === 'item_prices') ? "OK\n" : "FAIL\n";
