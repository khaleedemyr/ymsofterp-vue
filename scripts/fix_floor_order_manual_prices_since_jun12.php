<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);
$since = '2026-06-12';
$limit = null;
$allItems = in_array('--all-items', $argv ?? [], true);

$defaultItemNames = [
    'Barbeque Sauce',
    'Blackpepper Sauce',
    'Bolognaise Sauce',
    'Curry Sauce',
    'Japanese Teriyaki Sauce',
    'Korean BBQ Sauce',
    'Mushroom Sauce',
    'Cheese Sauce',
    'Kuah Buntut',
    'Thai Dressing',
    'Beef Chuck Short Ribs Dice',
    'Beef Oxtail',
    'Beef Ragout',
    'Duck Crispy',
];

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, strlen('--since='));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, strlen('--limit=')));
    }
}

echo "=== Fix FO manual prices (post Jun-12 logic) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Since: {$since}\n";
echo 'Scope: ' . ($allItems ? 'all items' : 'listed items only') . "\n";
if ($limit !== null) {
    echo "Limit rows: {$limit}\n";
}
echo "\n";

if (! Schema::hasTable('food_floor_order_items') || ! Schema::hasTable('food_floor_orders')) {
    echo "Tabel FO tidak ditemukan.\n";
    exit(1);
}

$query = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->whereDate('ffoi.updated_at', '>=', $since)
    ->select(
        'ffoi.id',
        'ffoi.floor_order_id',
        'ffoi.item_id',
        'ffoi.item_name',
        'ffoi.qty',
        'ffoi.price',
        'ffoi.subtotal',
        'ffoi.updated_at',
        'ffo.id_outlet',
        'ffo.order_number',
        'o.region_id',
        'o.nama_outlet'
    )
    ->orderBy('ffoi.id');

if (! $allItems) {
    $itemIds = DB::table('items')
        ->whereIn('name', $defaultItemNames)
        ->pluck('id')
        ->all();
    if ($itemIds === []) {
        echo "Item list default tidak ditemukan di master.\n";
        exit(1);
    }
    $query->whereIn('ffoi.item_id', $itemIds);
}

if ($limit !== null) {
    $query->limit($limit);
}

$rows = $query->get();
echo "Rows scanned: {$rows->count()}\n";

$fixes = [];
$priceRowCache = [];
foreach ($rows as $row) {
    $regionId = $row->region_id ? (int) $row->region_id : null;
    $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
    $cacheKey = $row->item_id . '|' . ($regionId ?? 0) . '|' . ($outletId ?? '0');

    if (! array_key_exists($cacheKey, $priceRowCache)) {
        $priceRowCache[$cacheKey] = FloorOrderItemPriceResolver::resolvePriceRow((int) $row->item_id, $regionId, $outletId);
    }
    $priceRow = $priceRowCache[$cacheKey];
    if (! $priceRow) {
        continue;
    }

    $mode = ($priceRow->pricing_mode ?? 'manual') === 'auto' ? 'auto' : 'manual';
    $rawPrice = (float) ($priceRow->price ?? 0);
    if ($mode !== 'manual' || $rawPrice <= 0) {
        continue;
    }

    $expected = FloorOrderItemPriceResolver::roundUpToHundred($rawPrice);
    $current = (float) $row->price;
    if (abs($expected - $current) < 0.01) {
        continue;
    }

    $qty = (float) $row->qty;
    $expectedSubtotal = round($expected * $qty, 2);

    $fixes[] = [
        'id' => (int) $row->id,
        'floor_order_id' => (int) $row->floor_order_id,
        'order_number' => (string) ($row->order_number ?? ''),
        'outlet' => (string) ($row->nama_outlet ?? ''),
        'item_name' => (string) ($row->item_name ?? ''),
        'current_price' => $current,
        'expected_price' => $expected,
        'current_subtotal' => (float) ($row->subtotal ?? 0),
        'expected_subtotal' => $expectedSubtotal,
        'qty' => $qty,
        'price_row_id' => (int) $priceRow->id,
        'price_row_scope' => (string) ($priceRow->availability_price_type ?? ''),
    ];
}

echo "Manual mismatched rows: " . count($fixes) . "\n\n";

$preview = array_slice($fixes, 0, 30);
foreach ($preview as $f) {
    echo "[FO {$f['order_number']}] {$f['outlet']} | {$f['item_name']}\n";
    echo "  price {$f['current_price']} -> {$f['expected_price']} | qty={$f['qty']}\n";
    echo "  subtotal {$f['current_subtotal']} -> {$f['expected_subtotal']} | row_scope={$f['price_row_scope']}#{$f['price_row_id']}\n";
}
if (count($fixes) > count($preview)) {
    echo "... and " . (count($fixes) - count($preview)) . " more rows\n";
}
echo "\n";

if (! $apply) {
    echo "DRY-RUN selesai. Tambahkan --apply untuk update data.\n";
    exit(0);
}

DB::beginTransaction();
try {
    $updated = 0;
    foreach ($fixes as $f) {
        DB::table('food_floor_order_items')
            ->where('id', $f['id'])
            ->update([
                'price' => $f['expected_price'],
                'subtotal' => $f['expected_subtotal'],
                'updated_at' => now(),
            ]);
        $updated++;
    }

    DB::commit();
    echo "APPLY selesai. Updated rows: {$updated}\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
