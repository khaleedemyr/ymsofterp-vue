<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$si = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
    ->join('inventory_item_serials as s', 'si.serial_id', '=', 's.id')
    ->whereDate('h.receive_date', '2026-06-13')
    ->where('si.item_id', 52985)
    ->where('si.cost_source', 'serial_warehouse_sale')
    ->select('si.*', 's.out_outlet_id', 's.cost_small as serial_cost_small', 's.source_type', 'h.outlet_id as receive_outlet')
    ->first();

echo "GSR item id={$si->id} cost_small={$si->cost_small} cost_source={$si->cost_source}\n";
echo "serial out_outlet_id={$si->out_outlet_id} serial_cost_small={$si->serial_cost_small} receive_outlet={$si->receive_outlet}\n";

$itemId = 52985;
$outletId = $si->out_outlet_id;
$regionId = $outletId ? DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id') : null;
echo "resolve outlet={$outletId} region={$regionId}\n";

$priceRow = DB::table('item_prices')
    ->where('item_id', $itemId)
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

echo "priceRow id={$priceRow->id} price={$priceRow->price} mode=" . ($priceRow->pricing_mode ?? 'n/a') . "\n";

$mode = ($priceRow && Schema::hasColumn('item_prices', 'pricing_mode'))
    ? (($priceRow->pricing_mode === 'auto') ? 'auto' : 'manual')
    : 'manual';
echo "resolved mode={$mode}\n";

if ($mode === 'manual' && $priceRow && (float) $priceRow->price > 0) {
    $item = DB::table('items')->where('id', $itemId)->first();
    $div = (float) $item->small_conversion_qty * (float) $item->medium_conversion_qty;
    $expected = round((float) $priceRow->price / $div, 4);
    echo "EXPECTED manual cost_small={$expected} (current stored {$si->cost_small})\n";
}
