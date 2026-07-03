<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$ids = array_map('intval', array_slice($argv, 1));
if ($ids === []) {
    $ids = [11751997, 11751998];
}

foreach ($ids as $id) {
    $r = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->leftJoin('items as i', 'i.id', '=', 'ffoi.item_id')
        ->where('ffoi.id', $id)
        ->select('ffoi.*', 'ffo.tanggal', 'ffo.order_number', 'ffo.status', 'ffo.fo_mode', 'i.name as item_name')
        ->first();
    if (! $r) {
        echo "id={$id}: NOT FOUND\n";
        continue;
    }
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice((int) $r->item_id, (string) $r->unit);
    echo "id={$id} item={$r->item_name} tanggal={$r->tanggal} fo_mode={$r->fo_mode} status={$r->status}\n";
    echo "  unit={$r->unit} price={$r->price} expected={$expected} order={$r->order_number}\n";
}
