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
$fromDate = null;
$toDate = null;
$grFromDate = null;
$grToDate = null;
$limit = null;
$allStatuses = in_array('--all-statuses', $argv ?? [], true);
$defaultSharedItems = [
    'Beef Patty',
    'Dressing Salad',
    'Duck Confit',
    'Marinate Gomatare',
    'Orange Sauce',
    'Sauce Blueberry',
    'Smoked Chicken',
    'Thai Dressing',
    'Beef Patty Steak',
    'Meat Ball Patty',
    'Coating Goreng',
];
$itemNames = $defaultSharedItems;
$allItems = in_array('--all-items', $argv ?? [], true);

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, strlen('--since='));
    }
    if (str_starts_with($arg, '--from=')) {
        $fromDate = substr($arg, strlen('--from='));
    }
    if (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, strlen('--to='));
    }
    if (str_starts_with($arg, '--gr-from=')) {
        $grFromDate = substr($arg, strlen('--gr-from='));
    }
    if (str_starts_with($arg, '--gr-to=')) {
        $grToDate = substr($arg, strlen('--gr-to='));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, strlen('--limit=')));
    }
    if (str_starts_with($arg, '--items=')) {
        $itemNames = array_filter(array_map('trim', explode(',', substr($arg, strlen('--items=')))));
    }
}

echo "=== Fix FO prices from item_prices (large → medium) ===\n";
echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
if ($grFromDate && $grToDate) {
    echo "Tanggal GR (receive): {$grFromDate} s/d {$grToDate}\n";
} elseif ($fromDate && $toDate) {
    echo "Tanggal FO: {$fromDate} s/d {$toDate}\n";
} else {
    echo "Since updated_at: {$since}\n";
}
echo 'Statuses: ' . ($allStatuses ? 'all' : 'draft, submitted') . "\n";
if ($allItems) {
    echo "Items: ALL (manual pricing, non-asset)\n";
} else {
    echo 'Items: ' . implode(', ', $itemNames) . "\n";
}
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
    ->whereNotIn('ffo.fo_mode', ['RO Khusus', 'RO Supplier'])
    ->select(
        'ffoi.id',
        'ffoi.floor_order_id',
        'ffoi.item_id',
        'ffoi.item_name',
        'ffoi.qty',
        'ffoi.price',
        'ffoi.subtotal',
        'ffo.tanggal',
        'ffo.id_outlet',
        'ffo.order_number',
        'ffo.status',
        'o.region_id',
        'o.nama_outlet'
    )
    ->orderBy('ffoi.id');

if ($grFromDate && $grToDate) {
    $linkedFoItemIds = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->join('food_floor_order_items as linked', function ($join) {
            $join->on('gri.item_id', '=', 'linked.item_id')
                ->on('linked.floor_order_id', '=', 'do.floor_order_id');
        })
        ->whereBetween('gr.receive_date', [$grFromDate, $grToDate])
        ->whereNull('gr.deleted_at')
        ->distinct()
        ->pluck('linked.id')
        ->all();

    if ($linkedFoItemIds === []) {
        echo "Tidak ada FO item terhubung ke GR pada rentang tanggal tersebut.\n";
        exit(0);
    }

    $query->whereIn('ffoi.id', $linkedFoItemIds);
} elseif ($fromDate && $toDate) {
    $query->whereBetween('ffo.tanggal', [$fromDate, $toDate]);
} else {
    $query->whereDate('ffoi.updated_at', '>=', $since);
}

if (! $allStatuses) {
    $query->whereIn('ffo.status', ['draft', 'submitted']);
}

$itemIds = [];
if ($allItems) {
    $itemIds = DB::table('items as i')
        ->join('categories as c', 'c.id', '=', 'i.category_id')
        ->where('i.status', 'active')
        ->where(function ($q) {
            $q->whereNull('c.is_asset')->orWhere('c.is_asset', '!=', '1');
        })
        ->pluck('i.id')
        ->all();
} else {
    $itemIds = DB::table('items')->whereIn('name', $itemNames)->pluck('id')->all();
}
if ($itemIds === []) {
    echo "Item filter tidak ditemukan di master.\n";
    exit(1);
}
$query->whereIn('ffoi.item_id', $itemIds);

if ($limit !== null) {
    $query->limit($limit);
}

$rows = $query->get();
echo "Rows scanned: {$rows->count()}\n";

$priceKeys = [];
foreach ($rows as $row) {
    $regionId = $row->region_id ? (int) $row->region_id : null;
    $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
    $key = (int) $row->item_id . '|' . ($regionId ?? 0) . '|' . ($outletId ?? '0');
    $priceKeys[$key] = [(int) $row->item_id, $regionId, $outletId];
}

echo 'Unique price keys: ' . count($priceKeys) . "\n";

$expectedCache = [];
foreach ($priceKeys as $key => [$itemId, $regionId, $outletId]) {
    $expectedCache[$key] = FloorOrderItemPriceResolver::resolveMediumUnitPrice($itemId, $regionId, $outletId);
}

$fixes = [];
foreach ($rows as $row) {
    $regionId = $row->region_id ? (int) $row->region_id : null;
    $outletId = $row->id_outlet ? (string) $row->id_outlet : null;
    $key = (int) $row->item_id . '|' . ($regionId ?? 0) . '|' . ($outletId ?? '0');
    $expected = $expectedCache[$key] ?? 0.0;
    if ($expected <= 0) {
        continue;
    }

    $current = (float) $row->price;
    if (abs($expected - $current) < 0.01) {
        continue;
    }

    $qty = (float) $row->qty;
    $fixes[] = [
        'id' => (int) $row->id,
        'floor_order_id' => (int) $row->floor_order_id,
        'order_number' => (string) ($row->order_number ?? ''),
        'tanggal' => (string) ($row->tanggal ?? ''),
        'status' => (string) ($row->status ?? ''),
        'outlet' => (string) ($row->nama_outlet ?? ''),
        'item_name' => (string) ($row->item_name ?? ''),
        'current_price' => $current,
        'expected_price' => $expected,
        'expected_subtotal' => round($expected * $qty, 2),
        'qty' => $qty,
    ];
}

echo 'Mismatched rows: ' . count($fixes) . "\n\n";

$preview = array_slice($fixes, 0, 40);
foreach ($preview as $f) {
    echo "[{$f['status']}] {$f['tanggal']} FO {$f['order_number']} | {$f['outlet']} | {$f['item_name']}\n";
    echo "  price {$f['current_price']} -> {$f['expected_price']} | qty={$f['qty']}\n";
}
if (count($fixes) > count($preview)) {
    echo '... and ' . (count($fixes) - count($preview)) . " more rows\n";
}
echo "\n";

if (! $apply) {
    echo "DRY-RUN selesai. Tambahkan --apply untuk update data.\n";
    exit(0);
}

DB::beginTransaction();
try {
    $updated = 0;
    $affectedOrderIds = [];
    foreach ($fixes as $f) {
        DB::table('food_floor_order_items')
            ->where('id', $f['id'])
            ->update([
                'price' => $f['expected_price'],
                'subtotal' => $f['expected_subtotal'],
                'updated_at' => now(),
            ]);
        $affectedOrderIds[$f['floor_order_id']] = true;
        $updated++;
    }

    if (Schema::hasColumn('food_floor_orders', 'total_amount')) {
        foreach (array_keys($affectedOrderIds) as $orderId) {
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
        echo 'Recalculated total_amount for ' . count($affectedOrderIds) . " FO(s)\n";
    }

    DB::commit();
    echo "APPLY selesai. Updated rows: {$updated}\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
