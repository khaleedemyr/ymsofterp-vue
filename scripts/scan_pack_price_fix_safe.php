<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$foFrom = '2026-06-01';
$foTo = '2026-06-30';

$sql = <<<'SQL'
SELECT
    i.id AS item_id,
    i.name,
    w.name AS warehouse,
    COALESCE(i.medium_conversion_qty, 1) AS medium_conv,
    ip.price AS price_large
FROM items i
JOIN warehouse_division wd ON wd.id = i.warehouse_division_id
JOIN warehouses w ON w.id = wd.warehouse_id
JOIN item_prices ip ON ip.item_id = i.id AND ip.availability_price_type = 'all'
WHERE ip.price > 0
  AND COALESCE(i.medium_conversion_qty, 1) > 1
  AND w.name IN ('Main Store', 'MK1 Hot Kitchen', 'MK2 Cold Kitchen', 'Main Kitchen')
ORDER BY w.name, i.name
SQL;

$rows = DB::select($sql);
$candidates = [];

foreach ($rows as $r) {
    $itemId = (int) $r->item_id;
    $conv = (float) $r->medium_conv;
    $priceLarge = (float) $r->price_large;
    $mediumUnit = DB::table('units')->where('id', DB::table('items')->where('id', $itemId)->value('medium_unit_id'))->value('name') ?: 'Pack';

    $sysPack = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
    $masterUi = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);
    $divided = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge / $conv);

    if (abs($sysPack - $divided) > 1) {
        continue;
    }
    if (abs($masterUi - $sysPack) < 500) {
        continue;
    }

    $foTop = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereDate('ffo.tanggal', '>=', $foFrom)
        ->whereDate('ffo.tanggal', '<=', $foTo)
        ->selectRaw('ffoi.price, count(*) as cnt')
        ->groupBy('ffoi.price')
        ->orderByDesc('cnt')
        ->first();

    $foPrice = $foTop ? (float) $foTop->price : 0.0;
    $foMatchesDivided = $foPrice > 0 && abs($foPrice - $sysPack) < 1;
    $legacyHigh = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereDate('ffo.tanggal', '>=', $foFrom)
        ->whereDate('ffo.tanggal', '<=', $foTo)
        ->where('ffoi.price', '>', $sysPack * 2)
        ->exists();

    if (! $foMatchesDivided && ! $legacyHigh) {
        continue;
    }

    $candidates[] = [
        'item_id' => $itemId,
        'name' => $r->name,
        'warehouse' => $r->warehouse,
        'conv' => $conv,
        'price_large' => $priceLarge,
        'sys_pack' => $sysPack,
        'master_ui' => $masterUi,
        'fo_top' => $foPrice,
        'new_large' => round($priceLarge * $conv, 2),
        'new_pack' => $masterUi,
    ];
}

echo 'Need fix (FO divided, master UI = intended Pack): ' . count($candidates) . PHP_EOL . PHP_EOL;
printf("%-8s %-30s %-12s %5s %10s %10s %10s %10s\n", 'id', 'Item', 'Warehouse', 'conv', 'FO/sys', 'master', 'newPack', 'newLarge');
echo str_repeat('-', 105) . PHP_EOL;

$ids = [];
foreach ($candidates as $c) {
    printf(
        "%-8d %-30s %-12s %5.0f %10s %10s %10s %10s\n",
        $c['item_id'],
        mb_substr($c['name'], 0, 30),
        mb_substr($c['warehouse'], 0, 12),
        $c['conv'],
        number_format($c['fo_top'] ?: $c['sys_pack'], 0, ',', '.'),
        number_format($c['master_ui'], 0, ',', '.'),
        number_format($c['new_pack'], 0, ',', '.'),
        number_format($c['new_large'], 0, ',', '.'),
    );
    $ids[] = $c['item_id'];
}

$csv = __DIR__ . '/pack_price_fix_safe_candidates.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['item_id']);
foreach ($ids as $id) {
    fputcsv($fp, [$id]);
}
fclose($fp);
echo PHP_EOL . "CSV: {$csv}\n";
