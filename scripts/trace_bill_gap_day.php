<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$orderId = 'CPT-msykoet29lg3';
$outlet = 'SH011';
$date = '2026-08-18';

echo "=== ORDER HEADER vs ITEMS ===\n";
$order = DB::table('orders')->where('id', $orderId)->first();
$itemsSum = DB::table('order_items')->where('order_id', $orderId)->sum(DB::raw('qty * price'));
$itemsSubtotalSum = DB::table('order_items')->where('order_id', $orderId)->sum('subtotal');
echo "orders.total={$order->total}\n";
echo "sum qty*price={$itemsSum}\n";
echo "sum subtotal={$itemsSubtotalSum}\n";
echo "GAP=" . ((float)$order->total - (float)$itemsSum) . "\n\n";

// Search related tables for void/history
$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();
$key = 'Tables_in_' . $dbName;
$interesting = [];
foreach ($tables as $t) {
    $name = $t->$key;
    if (preg_match('/void|cancel|order_item|order_detail|order_log|audit/i', $name)) {
        $interesting[] = $name;
    }
}
echo "interesting tables: " . implode(', ', $interesting) . "\n\n";

foreach ($interesting as $name) {
    $cols = Schema::getColumnListing($name);
    $fkCols = array_values(array_filter($cols, fn ($c) => stripos($c, 'order') !== false || $c === 'id'));
    if (empty($fkCols)) continue;
    $q = DB::table($name);
    $q->where(function ($w) use ($fkCols, $orderId) {
        foreach ($fkCols as $c) {
            $w->orWhere($c, 'like', '%' . $orderId . '%');
            $w->orWhere($c, 'like', '%CPT26080751%');
            $w->orWhere($c, 'like', '%msykoet29lg3%');
        }
    });
    try {
        $rows = $q->limit(50)->get();
        if ($rows->count() > 0) {
            echo "--- {$name} ({$rows->count()}) ---\n";
            foreach ($rows as $r) {
                echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
            }
            echo "\n";
        }
    } catch (Throwable $e) {
        echo "skip {$name}: {$e->getMessage()}\n";
    }
}

echo "=== OUTLET DAY COMPARE ===\n";
$headerTotal = DB::table('orders')
    ->where('kode_outlet', $outlet)
    ->whereDate('created_at', $date)
    ->where('status', 'paid')
    ->sum('total');

$itemTotal = DB::table('order_items')
    ->join('orders', 'order_items.order_id', '=', 'orders.id')
    ->where('orders.kode_outlet', $outlet)
    ->whereDate('orders.created_at', $date)
    ->where('orders.status', 'paid')
    ->sum(DB::raw('order_items.qty * order_items.price'));

echo "SUM orders.total (paid) = {$headerTotal}\n";
echo "SUM order_items qty*price (paid) = {$itemTotal}\n";
echo "DAY GAP = " . ((float)$headerTotal - (float)$itemTotal) . "\n\n";

echo "=== ORDERS WITH HEADER != ITEMS ===\n";
$mismatches = DB::select("
    SELECT o.id, o.paid_number, o.total,
           COALESCE(SUM(oi.qty * oi.price), 0) AS items_total,
           (o.total - COALESCE(SUM(oi.qty * oi.price), 0)) AS gap
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.kode_outlet = ?
      AND DATE(o.created_at) = ?
      AND o.status = 'paid'
    GROUP BY o.id, o.paid_number, o.total
    HAVING ABS(o.total - COALESCE(SUM(oi.qty * oi.price), 0)) > 1
    ORDER BY ABS(o.total - COALESCE(SUM(oi.qty * oi.price), 0)) DESC
", [$outlet, $date]);

foreach ($mismatches as $m) {
    echo sprintf(
        "%s paid=%s header=%s items=%s gap=%s\n",
        $m->id,
        $m->paid_number,
        $m->total,
        $m->items_total,
        $m->gap
    );
}
