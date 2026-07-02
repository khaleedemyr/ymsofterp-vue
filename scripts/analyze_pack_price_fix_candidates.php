<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

/** GR screenshot prices (Pack/Pcs) — user says these are wrong (too low) */
$grScreenshot = [
    'Beef Chuck Short Ribs Dice' => 3100,
    'Beef Oxtail' => 4700,
    'Blackpepper Sauce' => 2800,
    'Bolognaise Sauce' => 2800,
    'Cheese Sauce' => 3300,
    'Coating Bakar' => 4700,
    'Coating Goreng' => 4600,
    'Curry Sauce' => 10900,
    'Duck Crispy' => 1900,
    'Japanese Teriyaki Sauce' => 1900,
    'Korean BBQ Sauce' => 1200,
    'Kuah Buntut' => 200,
    'Kuah Garang Asam' => 800,
    'Mushroom Sauce' => 2400,
    'Sauce Blueberry' => 10000,
    'Smoked Chicken' => 10900,
];

echo "=== Analisis: item_prices kemungkinan per Pack (perlu × medium_conv) ===\n\n";
printf(
    "%-30s %6s %12s %10s %10s %10s %8s\n",
    'Item',
    'conv',
    'item_price',
    'GR ss',
    'sys Pack',
    'as Pack',
    'FIX?'
);
echo str_repeat('-', 100) . "\n";

$candidates = [];

foreach ($grScreenshot as $name => $grPrice) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "{$name}: NOT FOUND\n";
        continue;
    }

    $itemId = (int) $item->id;
    $mediumConv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
    $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, null, null);
    $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
    $sysPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit ?: 'Pack');
    $asPackIfLarge = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);
    $dividedPack = $mediumConv > 1
        ? FloorOrderItemPriceResolver::roundUpToHundred($priceLarge / $mediumConv)
        : $asPackIfLarge;

    // Fix jika: conv>1, harga DB ≈ harga Pack yang diinginkan (bukan Recipe), sys saat ini = divided
    $needsFix = false;
    if ($mediumConv > 1 && $priceLarge > 0) {
        $treatDbAsPack = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);
        $matchesScreenshotDivided = abs($sysPack - $grPrice) < 1;
        $dbLooksLikePackPrice = abs($treatDbAsPack - $priceLarge) < 1
            && abs($dividedPack - $grPrice) < 500
            && abs($treatDbAsPack - $grPrice) > 1000;
        $needsFix = $matchesScreenshotDivided && $dbLooksLikePackPrice;
    }

    printf(
        "%-30s %6.0f %12s %10s %10s %10s %8s\n",
        $name,
        $mediumConv,
        number_format($priceLarge, 0, ',', '.'),
        number_format($grPrice, 0, ',', '.'),
        number_format($sysPack, 0, ',', '.'),
        number_format($asPackIfLarge, 0, ',', '.'),
        $needsFix ? 'YES' : 'no'
    );

    if ($needsFix) {
        $candidates[] = [
            'item_id' => $itemId,
            'name' => $name,
            'medium_conv' => $mediumConv,
            'old_large' => $priceLarge,
            'new_large' => round($priceLarge * $mediumConv, 2),
            'new_pack' => FloorOrderItemPriceResolver::roundUpToHundred($priceLarge),
        ];
    }
}

echo "\n=== Kandidat fix (" . count($candidates) . ") ===\n";
foreach ($candidates as $c) {
    echo sprintf(
        "%s (id %d): large %s -> %s, Pack jadi ~%s\n",
        $c['name'],
        $c['item_id'],
        number_format($c['old_large'], 0, ',', '.'),
        number_format($c['new_large'], 0, ',', '.'),
        number_format($c['new_pack'], 0, ',', '.'),
    );
}

// Legacy FO Jun: harga sebelum sync (bukan divided)
echo "\n=== FO Jun 2026 top prices (legacy check) ===\n";
foreach ($grScreenshot as $name => $grPrice) {
    $itemId = DB::table('items')->where('name', $name)->value('id');
    if (! $itemId) {
        continue;
    }
    $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge(
        (int) $itemId,
        FloorOrderItemPriceResolver::resolvePriceRow((int) $itemId, null, null),
    );
    $fo = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereDate('ffo.tanggal', '>=', '2026-06-01')
        ->whereDate('ffo.tanggal', '<=', '2026-06-30')
        ->selectRaw('ffoi.price, count(*) as cnt')
        ->groupBy('ffoi.price')
        ->orderByDesc('cnt')
        ->limit(3)
        ->get();
    $tops = $fo->map(fn ($r) => number_format((float) $r->price, 0) . "x{$r->cnt}")->implode(', ');
    echo "{$name}: item_price={$priceLarge} GR={$grPrice} FO=[{$tops}]\n";
}
