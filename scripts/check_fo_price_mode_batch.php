<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$names = [
    'Beras Lokal',
    'Black Pepper',
    'Buah Peach Can',
    'Coca Cola Can',
    'Fryall',
    'Gerkin',
    'Madu',
    'Sauce Chilli Botol',
    'Tissue Towel',
    'Dishwash',
    'HVS',
];

$hasPricingMode = Schema::hasColumn('item_prices', 'pricing_mode');
echo "item_prices.pricing_mode column: " . ($hasPricingMode ? 'YES' : 'NO') . "\n\n";

$items = DB::table('items as i')
    ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
    ->where(function ($q) use ($names) {
        foreach ($names as $n) {
            $q->orWhere('i.name', 'like', '%' . $n . '%');
        }
    })
    ->where('i.status', 'active')
    ->select('i.id', 'i.name', 'i.sku', 'c.name as category_name', 'i.medium_conversion_qty', 'i.small_conversion_qty')
    ->orderBy('i.name')
    ->get();

printf("%-8s %-40s %-10s %-8s %-14s %-14s %-14s %-14s %-10s\n",
    'ID', 'NAME', 'MODE', 'SCOPE', 'item_prices', 'FO_costish', 'suggested', 'FO×1.12', 'ratio');
echo str_repeat('-', 150) . "\n";

foreach ($items as $item) {
    $priceRows = DB::table('item_prices')
        ->where('item_id', $item->id)
        ->orderByDesc('id')
        ->get(['id', 'price', 'pricing_mode', 'availability_price_type', 'region_id', 'outlet_id']);

    if ($priceRows->isEmpty()) {
        printf("%-8s %-40s %-10s\n", $item->id, mb_substr($item->name, 0, 40), 'NO_PRICE');
        continue;
    }

    // Prefer outlet/region/all pick similar to resolver, but dump all briefly
    $picked = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
    $mode = $hasPricingMode ? (($picked->pricing_mode ?? 'manual') === 'auto' ? 'auto' : 'manual') : 'n/a';
    $scope = $picked->availability_price_type ?? '-';
    $master = (float) ($picked->price ?? 0);
    $suggested = FoodGrLastPurchaseForItem::suggestedSellingPrice((int) $item->id);
    $costish = $master > 0 ? round($master / 1.12, 2) : 0;
    $back = $costish > 0 ? FloorOrderItemPriceResolver::roundUpToHundred($costish * 1.12) : 0;
    $ratio = $master > 0 && $costish > 0 ? round($costish / $master, 4) : 0;

    printf(
        "%-8s %-40s %-10s %-8s %-14s %-14s %-14s %-14s %-10s\n",
        $item->id,
        mb_substr($item->name, 0, 40),
        $mode,
        $scope,
        number_format($master, 2, '.', ','),
        number_format($costish, 2, '.', ','),
        $suggested !== null ? number_format($suggested, 2, '.', ',') : '-',
        number_format($back, 2, '.', ','),
        $ratio
    );

    // Show all price scopes if multiple
    if ($priceRows->count() > 1) {
        foreach ($priceRows as $pr) {
            $m = $hasPricingMode ? (($pr->pricing_mode ?? 'manual') === 'auto' ? 'auto' : 'manual') : 'n/a';
            echo "    · #{$pr->id} {$pr->availability_price_type}"
                . ($pr->region_id ? "/r{$pr->region_id}" : '')
                . ($pr->outlet_id ? "/o{$pr->outlet_id}" : '')
                . " mode={$m} price=" . number_format((float) $pr->price, 2, '.', ',') . "\n";
        }
    }
}

echo "\n--- Recent FO lines that look like master/1.12 for these items ---\n";
$itemIds = $items->pluck('id')->all();
if ($itemIds) {
    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->join('items as i', 'i.id', '=', 'ffoi.item_id')
        ->whereIn('ffoi.item_id', $itemIds)
        ->whereDate('ffo.tanggal', '>=', '2026-05-01')
        ->orderByDesc('ffo.tanggal')
        ->limit(30)
        ->get([
            'ffo.order_number',
            'ffo.tanggal',
            'ffo.status',
            'ffo.id_outlet',
            'i.name as item_name',
            'ffoi.unit',
            'ffoi.price as fo_price',
            'ffoi.item_id',
        ]);

    foreach ($rows as $r) {
        $picked = FloorOrderItemPriceResolver::resolvePriceRow((int) $r->item_id, null, (string) $r->id_outlet);
        $mode = $hasPricingMode ? (($picked->pricing_mode ?? 'manual') === 'auto' ? 'auto' : 'manual') : 'n/a';
        $master = (float) ($picked->price ?? 0);
        $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice(
            (int) $r->item_id,
            (string) $r->unit,
            null,
            (string) $r->id_outlet
        );
        $ratio = $master > 0 ? round(((float) $r->fo_price) / $master, 4) : 0;
        echo sprintf(
            "%s | %s | %s | mode=%s | fo=%s | expected=%s | master=%s | fo/master=%s | unit=%s\n",
            $r->tanggal,
            $r->order_number,
            mb_substr($r->item_name, 0, 28),
            $mode,
            number_format((float) $r->fo_price, 2, '.', ','),
            number_format($expected, 2, '.', ','),
            number_format($master, 2, '.', ','),
            $ratio,
            $r->unit
        );
    }
}
