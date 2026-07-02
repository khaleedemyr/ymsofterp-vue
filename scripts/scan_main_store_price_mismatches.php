<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$warehouseId = DB::table('warehouses')->where('name', 'Main Store')->value('id')
    ?? DB::table('warehouses')->where('name', 'like', '%Main Store%')->value('id');

if (! $warehouseId) {
    echo "Warehouse Main Store tidak ditemukan\n";
    exit(1);
}

$divisionIds = DB::table('warehouse_division')->where('warehouse_id', $warehouseId)->pluck('id');

$items = DB::table('items as i')
    ->join('warehouse_division as wd', 'wd.id', '=', 'i.warehouse_division_id')
    ->leftJoin('units as um', 'um.id', '=', 'i.medium_unit_id')
    ->whereIn('wd.id', $divisionIds)
    ->select('i.id', 'i.name', 'i.medium_conversion_qty', 'i.small_conversion_qty', 'um.name as medium_unit')
    ->orderBy('i.name')
    ->get();

$candidates = [];

foreach ($items as $item) {
    $itemId = (int) $item->id;
    $conv = (float) ($item->medium_conversion_qty ?? 1) ?: 1;
    $mediumUnit = (string) ($item->medium_unit ?? 'Pack');

    $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, null, null);
    $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
    if ($priceLarge <= 0) {
        continue;
    }

    $sysUnit = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $mediumUnit);
    $masterAsPack = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge);

    if ($conv <= 1) {
        if (abs($sysUnit - $masterAsPack) > 1) {
            $candidates[] = [
                'item_id' => $itemId,
                'name' => $item->name,
                'conv' => $conv,
                'price_large' => $priceLarge,
                'sys_unit' => $sysUnit,
                'master_as_pack' => $masterAsPack,
                'reason' => 'conv1_mismatch',
            ];
        }
        continue;
    }

    // Salah pola: harga master = Pack, sistem bagi conv
    $divided = FloorOrderItemPriceResolver::roundUpToHundred($priceLarge / $conv);
    if (abs($sysUnit - $divided) < 1 && abs($masterAsPack - $sysUnit) > 1000) {
        $candidates[] = [
            'item_id' => $itemId,
            'name' => $item->name,
            'conv' => $conv,
            'price_large' => $priceLarge,
            'sys_unit' => $sysUnit,
            'master_as_pack' => $masterAsPack,
            'reason' => 'pack_price_in_large_field',
        ];
    }
}

echo 'Main Store items scanned: ' . $items->count() . PHP_EOL;
echo 'Candidates to fix: ' . count($candidates) . PHP_EOL . PHP_EOL;

printf("%-35s %5s %12s %12s %12s\n", 'Item', 'conv', 'sys (GR)', 'master UI', 'new large');
echo str_repeat('-', 85) . PHP_EOL;

foreach ($candidates as $c) {
    $newLarge = round($c['price_large'] * $c['conv'], 2);
    printf(
        "%-35s %5.0f %12s %12s %12s\n",
        mb_substr($c['name'], 0, 35),
        $c['conv'],
        number_format($c['sys_unit'], 0, ',', '.'),
        number_format($c['master_as_pack'], 0, ',', '.'),
        number_format($newLarge, 0, ',', '.'),
    );
}

if (in_array('--csv', $argv ?? [], true)) {
    $fp = fopen(__DIR__ . '/main_store_price_fix_candidates.csv', 'w');
    fputcsv($fp, ['item_id', 'item_name', 'medium_conv', 'price_large_now', 'sys_unit', 'master_as_pack', 'new_large']);
    foreach ($candidates as $c) {
        fputcsv($fp, [
            $c['item_id'],
            $c['name'],
            $c['conv'],
            $c['price_large'],
            $c['sys_unit'],
            $c['master_as_pack'],
            round($c['price_large'] * $c['conv'], 2),
        ]);
    }
    fclose($fp);
    echo PHP_EOL . 'CSV: scripts/main_store_price_fix_candidates.csv' . PHP_EOL;
}
