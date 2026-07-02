<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$names = [
    'Sauce Blueberry',
    'Dressing Salad',
];

foreach ($names as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "=== {$name} : NOT FOUND ===\n\n";
        continue;
    }

    $priceRow = DB::table('item_prices')
        ->where('item_id', $item->id)
        ->where('availability_price_type', 'all')
        ->orderByDesc('id')
        ->first();
    $itemPriceLarge = (float) ($priceRow->price ?? 0);
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?? '';
    $expectedFo = FloorOrderItemPriceResolver::resolveLineUnitPrice((int) $item->id, $mediumUnit);

    $foStats = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $item->id)
        ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
        ->selectRaw('COUNT(*) cnt, AVG(ffoi.price) avg_price, MIN(ffoi.price) min_price, MAX(ffoi.price) max_price')
        ->first();

    echo "=== {$name} (#{$item->id}) ===\n";
    echo "item_prices(all)={$itemPriceLarge} expected_fo={$expectedFo} unit={$mediumUnit}\n";
    echo "FO Jun rows={$foStats->cnt} avg={$foStats->avg_price} min={$foStats->min_price} max={$foStats->max_price}\n";

    $sample = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->where('ffoi.item_id', $item->id)
        ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
        ->select('ffo.tanggal', 'ffo.order_number', 'o.nama_outlet', 'ffoi.qty', 'ffoi.unit', 'ffoi.price', 'ffo.fo_mode')
        ->orderBy('ffoi.price')
        ->limit(8)
        ->get();
    foreach ($sample as $r) {
        echo "{$r->tanggal} {$r->order_number} {$r->nama_outlet} qty={$r->qty} unit={$r->unit} price={$r->price} mode={$r->fo_mode}\n";
    }

    echo "\n";
}

