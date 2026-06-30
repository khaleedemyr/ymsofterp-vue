<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$dates = ['2026-06-14', '2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18'];
$items = [
    'Beef Patty', 'Dressing Salad', 'Duck Confit', 'Marinate Gomatare', 'Orange Sauce',
    'Sauce Blueberry', 'Smoked Chicken', 'Thai Dressing', 'Beef Patty Steak', 'Meat Ball Patty', 'Coating Goreng',
];

$itemIds = DB::table('items')->whereIn('name', $items)->pluck('id', 'name');

echo "=== FO actual vs resolver expected (Jun 14-18) ===\n\n";

foreach ($dates as $date) {
    echo "--- {$date} ---\n";
    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
        ->where('ffo.tanggal', $date)
        ->whereIn('ffoi.item_id', $itemIds->values())
        ->whereNotIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
        ->select('ffoi.item_name', 'ffoi.price', 'ffoi.item_id', 'o.region_id', 'ffo.id_outlet', 'ffo.order_number')
        ->orderBy('ffoi.item_name')
        ->get();

    $grouped = $rows->groupBy('item_name');
    foreach ($items as $name) {
        if (!isset($itemIds[$name])) {
            continue;
        }
        $lines = $grouped->get($name);
        if (!$lines || $lines->isEmpty()) {
            continue;
        }
        $sample = $lines->first();
        $regionId = $sample->region_id ? (int) $sample->region_id : null;
        $outletId = $sample->id_outlet ? (string) $sample->id_outlet : null;
        $expected = FloorOrderItemPriceResolver::resolveMediumUnitPrice((int) $sample->item_id, $regionId, $outletId);

        $prices = $lines->pluck('price')->unique()->sort()->values();
        $priceStr = $prices->map(fn ($p) => number_format((float) $p, 0, ',', '.'))->implode(' / ');
        $flag = $prices->every(fn ($p) => abs((float) $p - $expected) < 0.01) ? 'OK' : 'SALAH';
        $expStr = number_format($expected, 0, ',', '.');

        echo sprintf("  %-22s FO: %-20s expected: %8s  [%s]  (%d lines)\n", $name, $priceStr, $expStr, $flag, $lines->count());
    }
    echo "\n";
}
