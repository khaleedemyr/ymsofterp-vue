<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\ItemUnitCost;
use Illuminate\Support\Facades\DB;

$names = ['%Sirloin%Aussie%', '%Tenderloin%Aussie%'];

foreach ($names as $pattern) {
    $items = DB::table('items')->where('name', 'like', $pattern)->get();
    echo "\n=== Items like {$pattern} ===\n";
    foreach ($items as $item) {
        echo "ID {$item->id} | {$item->name}\n";
        echo "  small_conv={$item->small_conversion_qty} medium_conv={$item->medium_conversion_qty}\n";

        $prices = DB::table('item_prices')->where('item_id', $item->id)->get();
        foreach ($prices as $p) {
            $mode = $p->pricing_mode ?? 'n/a';
            echo "  item_prices: type={$p->availability_price_type} price={$p->price} mode={$mode}\n";
        }

        $priceLarge = (float) (DB::table('item_prices')
            ->where('item_id', $item->id)
            ->where('availability_price_type', 'all')
            ->orderByDesc('id')
            ->value('price') ?? 0);

        $smallConv = (float) ($item->small_conversion_qty ?: 1);
        $mediumConv = (float) ($item->medium_conversion_qty ?: 1);
        $divisor = $smallConv * $mediumConv;
        $expectedCostSmall = $divisor > 0 ? round($priceLarge / $divisor, 4) : 0;
        $expectedPcsPrice = ItemUnitCost::priceForUnit($expectedCostSmall, $item, $item->small_unit_id);

        echo "  Manual large Rp " . number_format($priceLarge, 2) . " => cost_small {$expectedCostSmall} => harga/Pcs " . number_format($expectedPcsPrice, 2) . "\n";

        $serialCosts = DB::table('inventory_item_serials')
            ->where('item_id', $item->id)
            ->where('cost_small', '>', 0)
            ->select('cost_small', DB::raw('count(*) as cnt'))
            ->groupBy('cost_small')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get();

        echo "  Distinct cost_small on serials:\n";
        foreach ($serialCosts as $sc) {
            $pcs = ItemUnitCost::priceForUnit((float) $sc->cost_small, $item, $item->small_unit_id);
            echo "    cost_small={$sc->cost_small} (x{$sc->cnt}) => Rp " . number_format($pcs, 2) . "/Pcs\n";
        }

        $gsrItems = DB::table('outlet_serial_receive_items as si')
            ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
            ->where('si.item_id', $item->id)
            ->whereDate('h.receive_date', '>=', '2026-06-10')
            ->select('si.cost_small', DB::raw('count(*) as cnt'))
            ->groupBy('si.cost_small')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        if ($gsrItems->isNotEmpty()) {
            echo "  GSR receive cost_small (Jun 10+):\n";
            foreach ($gsrItems as $gi) {
                $pcs = ItemUnitCost::priceForUnit((float) $gi->cost_small, $item, $item->small_unit_id);
                echo "    cost_small={$gi->cost_small} (x{$gi->cnt}) => Rp " . number_format($pcs, 2) . "/Pcs\n";
            }
        }
    }
}

echo "\nDone.\n";
