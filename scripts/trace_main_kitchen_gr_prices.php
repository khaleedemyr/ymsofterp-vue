<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$names = [
    'Barbeque Sauce',
    'Beef Patty',
    'Blackpepper Sauce',
    'Butter Portion',
    'Curry Sauce',
    'Japanese Teriyaki Sauce',
    'Kuah Buntut',
    'Marinate Chicken',
    'Marinate Gomatare',
    'Mushroom Sauce',
    'Orange Sauce',
    'Sauce Blueberry',
];

$reportPrices = [
    'Barbeque Sauce' => 7500,
    'Beef Patty' => 200,
    'Blackpepper Sauce' => 2800,
    'Butter Portion' => 38600,
    'Curry Sauce' => 10900,
    'Japanese Teriyaki Sauce' => 1900,
    'Kuah Buntut' => 200,
    'Marinate Chicken' => 45200,
    'Marinate Gomatare' => 23600,
    'Mushroom Sauce' => 2400,
    'Orange Sauce' => 1900,
    'Sauce Blueberry' => 1100,
];

echo "=== Rekap FJ screenshot vs item_prices (large) vs expected medium ===\n\n";
printf("%-28s %10s %12s %6s %10s %8s\n", 'Item', 'Rekap FJ', 'item_price', 'conv', 'Expected', 'Match?');
echo str_repeat('-', 90) . "\n";

foreach ($names as $name) {
    $items = DB::table('items')->where('name', $name)->get(['id', 'name', 'medium_conversion_qty', 'medium_unit_id', 'large_unit_id']);

    if ($items->isEmpty()) {
        echo "{$name}: NOT FOUND\n\n";
        continue;
    }

    foreach ($items as $item) {
        $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $largeUnit = DB::table('units')->where('id', $item->large_unit_id)->value('name');
        $priceRow = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
        $largePrice = FloorOrderItemPriceResolver::resolvePriceLarge((int) $item->id, $priceRow);
        $expected = FloorOrderItemPriceResolver::resolveMediumUnitPrice((int) $item->id);
        $rekap = $reportPrices[$name] ?? 0;
        $match = ($rekap == $expected) ? 'OK' : 'DIFF';

        printf(
            "%-28s %10s %12s %6s %10s %8s\n",
            $name,
            number_format($rekap, 0, ',', '.'),
            number_format($largePrice, 0, ',', '.'),
            $item->medium_conversion_qty,
            number_format($expected, 0, ',', '.'),
            $match
        );
    }
}

echo "\n=== FO price legacy vs benar (Jun 2026+, top 2 harga) ===\n\n";
foreach ($names as $name) {
    $itemId = DB::table('items')->where('name', $name)->value('id');
    if (!$itemId) {
        continue;
    }
    $expected = FloorOrderItemPriceResolver::resolveMediumUnitPrice((int) $itemId);
    $foPrices = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->where('ffo.tanggal', '>=', '2026-06-01')
        ->selectRaw('ffoi.price, count(*) as cnt')
        ->groupBy('ffoi.price')
        ->orderByDesc('cnt')
        ->limit(2)
        ->get();

    echo "{$name}: expected={$expected} |";
    foreach ($foPrices as $fp) {
        $flag = ((float) $fp->price === (float) $expected) ? 'ok' : 'legacy?';
        echo " {$fp->price}(x{$fp->cnt},{$flag})";
    }
    echo "\n";
}
