<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$orderId = 'CPT-msykoet29lg3';
$paid = 'CPT26080751';

echo "=== ORDER ===\n";
$order = DB::table('orders')->where('paid_number', $paid)->orWhere('id', $orderId)->first();
echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$itemTables = ['order_details', 'order_items', 'orders_detail', 'order_menus', 'detail_orders'];
foreach ($itemTables as $t) {
    if (Schema::hasTable($t)) {
        echo "table exists: {$t} cols=" . implode(',', Schema::getColumnListing($t)) . "\n";
    }
}

// Find likely detail table by order_id FK
$detailTable = null;
foreach (['order_details', 'order_items', 'orders_detail'] as $t) {
    if (!Schema::hasTable($t)) continue;
    $cols = Schema::getColumnListing($t);
    foreach (['order_id', 'id_order', 'orders_id'] as $fk) {
        if (in_array($fk, $cols, true)) {
            $detailTable = [$t, $fk];
            break 2;
        }
    }
}

if (!$detailTable) {
    echo "No detail table found\n";
    exit;
}

[$table, $fk] = $detailTable;
echo "\nUsing {$table}.{$fk}\n\n";

$items = DB::table($table)->where($fk, $order->id)->get();
if ($items->isEmpty()) {
    $items = DB::table($table)->where($fk, $order->nomor)->get();
}
if ($items->isEmpty() && isset($order->paid_number)) {
    // try paid_number join path
    $items = DB::table($table)->where($fk, $order->paid_number)->get();
}

echo "items count: " . $items->count() . "\n";
$sumActive = 0;
$sumAll = 0;
$sumVoid = 0;
foreach ($items as $i => $item) {
    $qty = (float) ($item->qty ?? $item->quantity ?? $item->jumlah ?? 0);
    $price = (float) ($item->price ?? $item->harga ?? $item->harga_satuan ?? 0);
    $subtotal = (float) ($item->subtotal ?? $item->total ?? ($qty * $price));
    $status = $item->status ?? $item->item_status ?? $item->void ?? $item->is_void ?? '';
    $name = $item->menu_name ?? $item->item_name ?? $item->nama ?? $item->name ?? '-';
    $voided = false;
    foreach ((array) $item as $k => $v) {
        if (stripos((string) $k, 'void') !== false && ($v === 1 || $v === '1' || $v === true || strtolower((string)$v) === 'void' || strtolower((string)$v) === 'yes')) {
            $voided = true;
        }
        if (strtolower((string)$k) === 'status' && in_array(strtolower((string)$v), ['void', 'cancelled', 'cancel', 'voided'], true)) {
            $voided = true;
        }
    }
    $sumAll += $subtotal;
    if ($voided) $sumVoid += $subtotal; else $sumActive += $subtotal;
    echo sprintf(
        "#%d %s qty=%s price=%s subtotal=%s status=%s voided=%s raw=%s\n",
        $i + 1,
        $name,
        $qty,
        $price,
        $subtotal,
        is_scalar($status) ? $status : json_encode($status),
        $voided ? 'YES' : 'no',
        json_encode($item, JSON_UNESCAPED_UNICODE)
    );
}

echo "\nSUM all subtotals: {$sumAll}\n";
echo "SUM active: {$sumActive}\n";
echo "SUM voided: {$sumVoid}\n";
echo "orders.total: {$order->total}\n";
echo "orders.manual_discount: {$order->manual_discount_amount}\n";
echo "gap order.total - sumActive: " . ((float)$order->total - $sumActive) . "\n";
echo "gap order.total - sumAll: " . ((float)$order->total - $sumAll) . "\n";
