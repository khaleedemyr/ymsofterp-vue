<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$names = [
    'Beef Patty',
    'Dressing Salad',
    'Duck Confit',
    'Marinate Gomatare',
    'Orange Sauce',
    'Sauce Blueberry',
    'Smoked Chicken',
    'Thai Dressing',
    'Beef Patty Steak',
    'Meat Ball Patty',
    'Coating Goreng',
];

echo "=== Trace FO vs item_prices ===\n\n";

foreach ($names as $name) {
    $items = DB::table('items')->where('name', $name)->get([
        'id', 'name', 'medium_conversion_qty', 'small_conversion_qty',
        'large_unit_id', 'medium_unit_id', 'small_unit_id',
    ]);

    if ($items->isEmpty()) {
        echo "=== {$name}: NOT FOUND ===\n\n";
        continue;
    }

    foreach ($items as $item) {
        $largeUnit = DB::table('units')->where('id', $item->large_unit_id)->value('name');
        $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
        $smallUnit = DB::table('units')->where('id', $item->small_unit_id)->value('name');

        echo "=== {$item->name} (#{$item->id}) ===\n";
        echo "  units: small={$smallUnit} medium={$mediumUnit} large={$largeUnit}\n";
        echo "  conv: small={$item->small_conversion_qty} medium={$item->medium_conversion_qty}\n";

        $prices = DB::table('item_prices')->where('item_id', $item->id)->orderByDesc('id')->get();
        foreach ($prices as $p) {
            $mode = $p->pricing_mode ?? 'manual';
            echo "  item_prices #{$p->id} scope={$p->availability_price_type} mode={$mode} price={$p->price}\n";
        }

        $row = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
        $large = FloorOrderItemPriceResolver::resolvePriceLarge((int) $item->id, $row);
        $fo = FloorOrderItemPriceResolver::resolveMediumUnitPrice((int) $item->id);
        $conv = (float) ($item->medium_conversion_qty ?: 1);

        echo "  resolvePriceLarge={$large}\n";
        echo "  FO_medium (resolver)={$fo}\n";
        echo "  if_always_divide_large={$conv} => " . FloorOrderItemPriceResolver::roundUpToHundred($large / $conv) . "\n";

        // Sample FO lines Jun 14-18
        $foLines = DB::table('food_floor_order_items as ffoi')
            ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
            ->where('ffoi.item_id', $item->id)
            ->whereBetween('ffo.tanggal', ['2026-06-14', '2026-06-18'])
            ->select('ffo.tanggal', 'ffo.order_number', 'ffoi.price', 'ffoi.qty', 'ffo.id_outlet')
            ->orderBy('ffo.tanggal')
            ->limit(5)
            ->get();

        if ($foLines->isNotEmpty()) {
            echo "  FO lines Jun 14-18:\n";
            foreach ($foLines as $line) {
                echo "    {$line->tanggal} {$line->order_number} price={$line->price} qty={$line->qty}\n";
            }
        } else {
            echo "  FO lines Jun 14-18: none\n";
        }
        echo "\n";
    }
}
