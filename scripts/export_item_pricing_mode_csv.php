<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nama dari list anomali (match LIKE %name%)
$names = [
    'Beras Lokal',
    'Black Pepper',
    'Buah Peach Can',
    'Coca Cola Can',
    'Dishwash',
    'Fryall',
    'Gerkin',
    'HVS 1 Ply 75x65',
    'HVS Roll',
    'Madu',
    'Sauce Chilli Botol',
    'Tissue Towel',
];

$hasPricingMode = Schema::hasColumn('item_prices', 'pricing_mode');

$items = DB::table('items as i')
    ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
    ->where(function ($q) use ($names) {
        foreach ($names as $n) {
            $q->orWhere('i.name', 'like', '%' . $n . '%');
        }
    })
    ->where('i.status', 'active')
    ->select(
        'i.id',
        'i.name',
        'i.sku',
        'c.name as category_name'
    )
    ->orderBy('i.name')
    ->get();

$outPath = __DIR__ . '/item_pricing_mode_list.csv';
$fh = fopen($outPath, 'w');

fputcsv($fh, [
    'item_id',
    'item_name',
    'sku',
    'category',
    'pricing_mode',
    'availability_price_type',
    'region_id',
    'outlet_id',
    'item_prices_price',
    'suggested_sell_gr_plus_12',
    'is_picked_by_resolver_all_scope',
]);

foreach ($items as $item) {
    $priceRows = DB::table('item_prices')
        ->where('item_id', $item->id)
        ->orderByDesc('id')
        ->get();

    $picked = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
    $suggested = FoodGrLastPurchaseForItem::suggestedSellingPrice((int) $item->id);

    if ($priceRows->isEmpty()) {
        fputcsv($fh, [
            $item->id,
            $item->name,
            $item->sku,
            $item->category_name,
            'NO_PRICE',
            '',
            '',
            '',
            '',
            $suggested !== null ? number_format($suggested, 2, '.', '') : '',
            '',
        ]);
        continue;
    }

    foreach ($priceRows as $pr) {
        $mode = $hasPricingMode
            ? ((($pr->pricing_mode ?? 'manual') === 'auto') ? 'auto' : 'manual')
            : 'n/a';

        $isPicked = $picked && (int) $picked->id === (int) $pr->id ? 'yes' : 'no';

        fputcsv($fh, [
            $item->id,
            $item->name,
            $item->sku,
            $item->category_name,
            $mode,
            $pr->availability_price_type,
            $pr->region_id,
            $pr->outlet_id,
            number_format((float) $pr->price, 2, '.', ''),
            $suggested !== null ? number_format($suggested, 2, '.', '') : '',
            $isPicked,
        ]);
    }
}

fclose($fh);

// Summary CSV (1 baris per item = mode yang dipakai resolver)
$summaryPath = __DIR__ . '/item_pricing_mode_summary.csv';
$sfh = fopen($summaryPath, 'w');
fputcsv($sfh, [
    'item_id',
    'item_name',
    'sku',
    'category',
    'pricing_mode',
    'scope',
    'item_prices_price',
    'suggested_sell_gr_plus_12',
]);

foreach ($items as $item) {
    $picked = FloorOrderItemPriceResolver::resolvePriceRow((int) $item->id, null, null);
    $suggested = FoodGrLastPurchaseForItem::suggestedSellingPrice((int) $item->id);

    if (! $picked) {
        fputcsv($sfh, [
            $item->id,
            $item->name,
            $item->sku,
            $item->category_name,
            'NO_PRICE',
            '',
            '',
            $suggested !== null ? number_format($suggested, 2, '.', '') : '',
        ]);
        continue;
    }

    $mode = $hasPricingMode
        ? ((($picked->pricing_mode ?? 'manual') === 'auto') ? 'auto' : 'manual')
        : 'n/a';

    fputcsv($sfh, [
        $item->id,
        $item->name,
        $item->sku,
        $item->category_name,
        $mode,
        $picked->availability_price_type,
        number_format((float) $picked->price, 2, '.', ''),
        $suggested !== null ? number_format($suggested, 2, '.', '') : '',
    ]);
}

fclose($sfh);

$auto = 0;
$manual = 0;
$none = 0;
$summary = fopen($summaryPath, 'r');
fgetcsv($summary);
while (($row = fgetcsv($summary)) !== false) {
    if (($row[4] ?? '') === 'auto') {
        $auto++;
    } elseif (($row[4] ?? '') === 'manual') {
        $manual++;
    } else {
        $none++;
    }
}
fclose($summary);

echo "Detail CSV : {$outPath}\n";
echo "Summary CSV: {$summaryPath}\n";
echo "Total items: {$items->count()} | auto={$auto} | manual={$manual} | no_price={$none}\n";
