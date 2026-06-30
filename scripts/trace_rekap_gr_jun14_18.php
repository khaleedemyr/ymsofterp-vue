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

// Harga yang tampil di screenshot user (kemungkinan salah di Rekap FJ)
$userErpPrices = [
    'Beef Patty' => 200,
    'Dressing Salad' => 23700,
    'Duck Confit' => 2200,
    'Marinate Gomatare' => 23600,
    'Orange Sauce' => 1900,
    'Sauce Blueberry' => 1100,
    'Smoked Chicken' => 10900,
    'Thai Dressing' => 30200,
    'Beef Patty Steak' => 300,
    'Meat Ball Patty' => 100,
    'Coating Goreng' => 3900,
];

$itemIds = DB::table('items')->whereIn('name', $items)->pluck('id', 'name');

echo "=== Rekap FJ GR price vs FO expected (Main Kitchen items, per receive date) ===\n\n";

foreach ($dates as $date) {
    echo "--- GR receive_date {$date} ---\n";
    foreach ($items as $name) {
        if (!isset($itemIds[$name])) {
            continue;
        }
        $itemId = (int) $itemIds[$name];

        $grRows = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->where('it.id', $itemId)
            ->whereDate('gr.receive_date', $date)
            ->whereNull('gr.deleted_at')
            ->where('cat.name', 'Main Kitchen')
            ->selectRaw('avg(coalesce(fo.price,0)) as avg_price, count(*) as cnt')
            ->first();

        if (!$grRows || (int) $grRows->cnt === 0) {
            continue;
        }

        $expected = FloorOrderItemPriceResolver::resolveMediumUnitPrice($itemId);
        $avg = (float) $grRows->avg_price;
        $userErp = $userErpPrices[$name] ?? 0;
        $matchExp = abs($avg - $expected) < 0.01 ? 'OK' : 'SALAH';
        $matchUser = abs($avg - $userErp) < 0.01 ? 'sama screenshot' : 'beda screenshot';

        printf(
            "  %-22s GR avg: %8s | expected: %8s | screenshot: %8s | [%s] [%s]\n",
            $name,
            number_format($avg, 0, ',', '.'),
            number_format($expected, 0, ',', '.'),
            number_format($userErp, 0, ',', '.'),
            $matchExp,
            $matchUser
        );
    }
    echo "\n";
}
