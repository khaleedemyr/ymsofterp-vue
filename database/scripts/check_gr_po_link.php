<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select("
SELECT
  gi.id,
  gi.qty_received,
  gi.po_item_id,
  poi.price,
  poi.total,
  COALESCE(poi.purchase_order_food_id, poi.purchase_order_id, g.po_id) AS po_id,
  po.number AS po_number,
  po.date AS po_date,
  u_po.nama_lengkap AS po_creator,
  s.name AS supplier,
  pf.warehouse_id,
  w.name AS warehouse_name,
  ur.nama_lengkap AS received_by_name,
  po.status AS po_status
FROM food_good_receives g
JOIN food_good_receive_items gi ON gi.good_receive_id = g.id
LEFT JOIN purchase_order_food_items poi ON poi.id = gi.po_item_id
LEFT JOIN purchase_order_foods po ON po.id = COALESCE(poi.purchase_order_food_id, poi.purchase_order_id, g.po_id)
LEFT JOIN pr_food_items pfi ON pfi.id = poi.pr_food_item_id
LEFT JOIN pr_foods pf ON pf.id = pfi.pr_food_id
LEFT JOIN warehouses w ON w.id = pf.warehouse_id
LEFT JOIN users u_po ON u_po.id = po.created_by
LEFT JOIN suppliers s ON s.id = COALESCE(poi.supplier_id, po.supplier_id, g.supplier_id)
LEFT JOIN users ur ON ur.id = g.received_by
WHERE g.gr_number = 'GR-20260921-0011'
");

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
