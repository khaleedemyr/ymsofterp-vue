<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'Justus Steak House Cipete')->first();
echo "outlet id={$outlet->id_outlet} region={$outlet->region_id}\n";

$prices = DB::table('item_prices')->where('item_id', 52985)->get();
foreach ($prices as $p) {
    echo "id={$p->id} type={$p->availability_price_type} outlet={$p->outlet_id} region={$p->region_id} price={$p->price} mode=" . ($p->pricing_mode ?? 'n/a') . "\n";
}

// What resolveItemPriceRowForOutlet would pick
$outletId = $outlet->id_outlet;
$regionId = $outlet->region_id;
$row = DB::table('item_prices')
    ->where('item_id', 52985)
    ->where(function ($q) use ($regionId, $outletId) {
        $q->where('availability_price_type', 'all');
        if ($regionId) {
            $q->orWhere(function ($q2) use ($regionId) {
                $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
            });
        }
        if ($outletId) {
            $q->orWhere(function ($q2) use ($outletId) {
                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
            });
        }
    })
    ->orderByRaw("CASE WHEN availability_price_type = 'outlet' THEN 1 WHEN availability_price_type = 'region' THEN 2 ELSE 3 END")
    ->orderByDesc('id')
    ->first();
echo "\nResolved price row: id={$row->id} type={$row->availability_price_type} price={$row->price} mode=" . ($row->pricing_mode ?? 'n/a') . "\n";
