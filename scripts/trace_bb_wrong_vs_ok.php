<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 54667;
$outletId = '18';

echo "=== Barbeque Sauce: wrong (118600) vs ok (29700) FO lines Jul 2026 ===\n\n";

$rows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $itemId)
    ->where('ffo.id_outlet', $outletId)
    ->whereBetween('ffo.tanggal', ['2026-07-01', '2026-07-31'])
    ->whereIn('ffoi.price', [118600, 29700, 7500])
    ->select(
        'ffoi.id',
        'ffoi.price',
        'ffoi.qty',
        'ffoi.unit',
        'ffoi.created_at as line_created',
        'ffoi.updated_at as line_updated',
        'ffo.id as fo_id',
        'ffo.order_number',
        'ffo.tanggal',
        'ffo.status',
        'ffo.fo_mode',
        'ffo.input_mode',
        'ffo.created_at as fo_created',
        'ffo.updated_at as fo_updated',
    )
    ->orderBy('ffoi.price')
    ->orderBy('ffo.tanggal')
    ->get();

foreach ($rows as $r) {
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $r->unit, 1, $outletId);
    echo "{$r->tanggal} {$r->order_number} [{$r->status}] {$r->fo_mode} input={$r->input_mode}\n";
    echo "  price={$r->price} expected_now={$expected} qty={$r->qty} unit={$r->unit}\n";
    echo "  line_created={$r->line_created} line_updated={$r->line_updated}\n";
    echo "  fo_created={$r->fo_created} fo_updated={$r->fo_updated}\n\n";
}

$item = DB::table('items')->where('id', $itemId)->first();
echo "Item medium_conversion_qty={$item->medium_conversion_qty}\n";
$ip = DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->orderByDesc('id')->first();
echo "item_prices all: price={$ip->price} updated={$ip->updated_at}\n";
