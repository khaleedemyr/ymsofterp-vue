<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Illuminate\Support\Facades\DB;

$from = "2026-07-01";
$to = "2026-07-01";

echo "=== 1) REKAP FJ Food GR (same joins as trait) ===\n";
$food = DB::select("
SELECT ROUND(SUM(i.received_qty * COALESCE(fo.price, 0)), 2) AS total
FROM outlet_food_good_receives gr
JOIN outlet_food_good_receive_items i ON i.outlet_food_good_receive_id = gr.id
JOIN items it ON it.id = i.item_id
JOIN categories c ON c.id = it.category_id
JOIN sub_categories sc ON sc.id = it.sub_category_id
JOIN units u ON u.id = it.small_unit_id
LEFT JOIN delivery_orders do ON do.id = gr.delivery_order_id
LEFT JOIN food_floor_order_items fo ON fo.floor_order_id = do.floor_order_id AND fo.item_id = i.item_id
LEFT JOIN warehouse_divisions wd ON wd.id = it.warehouse_division_id
LEFT JOIN warehouses w ON w.id = wd.warehouse_id
WHERE gr.deleted_at IS NULL
  AND w.name IS NOT NULL
  AND DATE(gr.receive_date) BETWEEN ? AND ?
", [$from, $to]);
echo "food_gr=" . ($food[0]->total ?? 0) . "\n";

echo "=== 2) REKAP FJ Serial GR ===\n";
// Find serial method columns from schema lightly
$cols = DB::select("SHOW COLUMNS FROM serial_item_good_receive_items");
echo "serial_item cols: " . implode(", ", array_map(fn($c) => $c->Field, $cols)) . "\n";
