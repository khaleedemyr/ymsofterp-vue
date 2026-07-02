<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use Illuminate\Support\Facades\DB;

$ids = array_map('intval', array_slice($argv, 1));
if ($ids === []) {
    $ids = [53043, 54673, 54708, 54710, 52660, 52887, 53111, 54730, 54694, 52853];
}

$auditor = new FloorOrderPriceAuditor();
$result = $auditor->scan('2026-06-12', null, true, $ids);
echo "Mismatches for batch: " . count($result['mismatches']) . "\n\n";

foreach ($ids as $itemId) {
    $item = DB::table('items')->where('id', $itemId)->first();
    if (!$item) {
        echo "=== {$itemId}: NOT FOUND ===\n\n";
        continue;
    }
    $units = DB::table('units')->whereIn('id', [$item->small_unit_id, $item->medium_unit_id, $item->large_unit_id])->pluck('name', 'id');
    $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, null, null);
    $large = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
    $mode = ($priceRow->pricing_mode ?? 'manual');

    echo "=== {$itemId}: {$item->name} mode={$mode} large={$large} ===\n";
    echo "  small={$units[$item->small_unit_id]} conv={$item->small_conversion_qty}\n";
    echo "  med={$units[$item->medium_unit_id]} conv={$item->medium_conversion_qty}\n";
    echo "  large={$units[$item->large_unit_id]}\n";

    $fo = DB::table('food_floor_order_items')
        ->where('item_id', $itemId)
        ->whereDate('created_at', '>=', '2026-06-12')
        ->selectRaw('unit, price, COUNT(*) as cnt')
        ->groupBy('unit', 'price')
        ->orderByDesc('cnt')
        ->limit(5)
        ->get();

    foreach ($fo as $r) {
        $exp = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $r->unit, null, null, $item);
        $tier = FloorOrderItemPriceResolver::detectUnitTier($item, $r->unit);
        $flag = abs($exp - (float)$r->price) < 0.01 ? 'OK' : 'SELISIH';
        echo "  FO unit={$r->unit} tier={$tier} price={$r->price} x{$r->cnt} expected={$exp} [{$flag}]\n";
    }
    echo "\n";
}
