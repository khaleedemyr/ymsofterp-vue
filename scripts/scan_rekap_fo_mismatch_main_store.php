<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$from = '2026-06-01';
$to = '2026-06-30';

$warehouseId = DB::table('warehouses')->where('name', 'Main Store')->value('id');
$divisionIds = DB::table('warehouse_division')->where('warehouse_id', $warehouseId)->pluck('id');
$itemIds = DB::table('items')->whereIn('warehouse_division_id', $divisionIds)->pluck('id');

$rows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->whereIn('it.id', $itemIds)
    ->whereDate('gr.receive_date', '>=', $from)
    ->whereDate('gr.receive_date', '<=', $to)
    ->whereNull('gr.deleted_at')
    ->where('fo.price', '>', 0)
    ->selectRaw('it.id as item_id, it.name, fo.unit, fo.price, count(*) as cnt')
    ->groupBy('it.id', 'it.name', 'fo.unit', 'fo.price')
    ->orderBy('it.name')
    ->get();

$byItem = [];
foreach ($rows as $r) {
    $id = (int) $r->item_id;
    $item = DB::table('items')->where('id', $id)->first();
    $unit = (string) ($r->unit ?? '');
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice($id, $unit, null, null, $item);
    $current = (float) $r->price;
    if ($expected <= 0 || abs($current - $expected) < 1) {
        continue;
    }
    if (! isset($byItem[$id])) {
        $ip = DB::table('item_prices')->where('item_id', $id)->where('availability_price_type', 'all')->first();
        $byItem[$id] = [
            'item_id' => $id,
            'name' => $r->name,
            'stored' => $ip ? (float) $ip->price : 0,
            'mode' => $ip->pricing_mode ?? '-',
            'conv' => (float) ($item->medium_conversion_qty ?? 1),
            'mismatch_gr_lines' => 0,
            'fo_prices' => [],
            'expected' => $expected,
        ];
    }
    $byItem[$id]['mismatch_gr_lines'] += (int) $r->cnt;
    $pk = (string) $current;
    $byItem[$id]['fo_prices'][$pk] = ($byItem[$id]['fo_prices'][$pk] ?? 0) + (int) $r->cnt;
}

uasort($byItem, fn ($a, $b) => $b['mismatch_gr_lines'] <=> $a['mismatch_gr_lines']);

echo 'Main Store GR Jun: item dengan FO price != expected: ' . count($byItem) . PHP_EOL . PHP_EOL;
printf("%-35s %5s %12s %12s %8s %s\n", 'Item', 'conv', 'stored ERP', 'expected FO', 'GR baris', 'FO salah');
echo str_repeat('-', 110) . PHP_EOL;

$ids = [];
foreach ($byItem as $c) {
    $foWrong = [];
    foreach ($c['fo_prices'] as $p => $n) {
        $foWrong[] = number_format((float) $p, 0, ',', '.') . " x{$n}";
    }
    printf(
        "%-35s %5.0f %12s %12s %8d %s\n",
        mb_substr($c['name'], 0, 35),
        $c['conv'],
        number_format($c['stored'], 0, ',', '.'),
        number_format($c['expected'], 0, ',', '.'),
        $c['mismatch_gr_lines'],
        implode(', ', $foWrong),
    );
    $ids[] = $c['item_id'];
}

$csv = __DIR__ . '/main_store_rekap_fo_mismatch_jun2026.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['item_id']);
foreach ($ids as $id) {
    fputcsv($fp, [$id]);
}
fclose($fp);
echo PHP_EOL . "CSV: {$csv} (" . count($ids) . " item)\n";
