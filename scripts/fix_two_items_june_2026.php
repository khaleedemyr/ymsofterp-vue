<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv, true);
$from = '2026-06-01';
$to = '2026-06-30';
$names = ['Sauce Blueberry', 'Dressing Salad'];

$items = DB::table('items')->whereIn('name', $names)->get()->keyBy('id');
$itemIds = $items->keys()->values()->all();

if ($itemIds === []) {
    echo "Items not found.\n";
    exit(1);
}

$unitNameById = DB::table('units')->pluck('name', 'id')->all();
$priceRowsByItem = DB::table('item_prices')
    ->whereIn('item_id', $itemIds)
    ->orderByDesc('id')
    ->get()
    ->groupBy('item_id');

echo "=== Fix two items (June 2026) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

$rows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->whereIn('ffoi.item_id', $itemIds)
    ->whereBetween('ffo.tanggal', [$from, $to])
    ->select(
        'ffoi.id',
        'ffoi.floor_order_id',
        'ffoi.item_id',
        'ffoi.qty',
        'ffoi.unit',
        'ffoi.price',
        'ffo.tanggal',
        'ffo.order_number',
        'ffo.fo_mode',
        'ffo.id_outlet',
        'o.region_id'
    )
    ->orderBy('ffoi.id')
    ->get();

$fixes = [];
foreach ($rows as $row) {
    $item = $items->get((int) $row->item_id);
    if (! $item) {
        continue;
    }

    $priceRows = $priceRowsByItem->get((int) $row->item_id, collect());
    $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
    $regionId = $row->region_id ? (int) $row->region_id : null;

    $pick = static function (string $type, ?int $region = null, ?string $outlet = null) use ($priceRows): ?object {
        return $priceRows->first(function ($r) use ($type, $region, $outlet) {
            if (($r->availability_price_type ?? '') !== $type) {
                return false;
            }
            if ($type === 'region' && (int) ($r->region_id ?? 0) !== (int) $region) {
                return false;
            }
            if ($type === 'outlet' && (string) ($r->outlet_id ?? '') !== (string) $outlet) {
                return false;
            }
            if ((float) ($r->price ?? 0) <= 0) {
                return false;
            }
            return true;
        });
    };

    $priceRow = $pick('outlet', null, $outletId)
        ?? $pick('region', $regionId, null)
        ?? $pick('all')
        ?? $priceRows->first();

    $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge((int) $row->item_id, $priceRow);
    if ($priceLarge <= 0) {
        continue;
    }

    $tier = FloorOrderItemPriceResolver::detectUnitTier($item, (string) $row->unit, $unitNameById);
    $expected = match ($tier) {
        'large' => FloorOrderItemPriceResolver::roundUpToHundred($priceLarge),
        'small' => FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToSmallPrice($priceLarge, $item)
        ),
        default => FloorOrderItemPriceResolver::roundUpToHundred(
            FloorOrderItemPriceResolver::largeToMediumPrice($priceLarge, $item)
        ),
    };

    if (abs((float) $row->price - $expected) < 0.01) {
        continue;
    }

    $fixes[] = [
        'id' => (int) $row->id,
        'floor_order_id' => (int) $row->floor_order_id,
        'item_name' => $item->name,
        'tanggal' => $row->tanggal,
        'order_number' => $row->order_number,
        'fo_mode' => $row->fo_mode,
        'from' => (float) $row->price,
        'to' => $expected,
        'subtotal' => round($expected * (float) $row->qty, 2),
    ];
}

echo 'Rows scanned: ' . $rows->count() . "\n";
echo 'Rows mismatch: ' . count($fixes) . "\n";
foreach (array_slice($fixes, 0, 20) as $f) {
    echo "{$f['tanggal']} {$f['order_number']} {$f['item_name']} {$f['from']} -> {$f['to']} ({$f['fo_mode']})\n";
}

if (! $apply || $fixes === []) {
    exit(0);
}

DB::beginTransaction();
try {
    $orderIds = [];
    foreach ($fixes as $f) {
        DB::table('food_floor_order_items')
            ->where('id', $f['id'])
            ->update([
                'price' => $f['to'],
                'subtotal' => $f['subtotal'],
                'updated_at' => now(),
            ]);
        $orderIds[$f['floor_order_id']] = true;
    }

    if (Schema::hasColumn('food_floor_orders', 'total_amount')) {
        foreach (array_keys($orderIds) as $orderId) {
            $total = (float) DB::table('food_floor_order_items')
                ->where('floor_order_id', $orderId)
                ->sum('subtotal');
            DB::table('food_floor_orders')
                ->where('id', $orderId)
                ->update([
                    'total_amount' => $total,
                    'updated_at' => now(),
                ]);
        }
    }

    DB::commit();
    echo "Updated: " . count($fixes) . "\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}

