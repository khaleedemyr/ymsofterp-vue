<?php

// Verification queries untuk check apakah data di report benar belum GR
// Jalankan: php artisan tinker
// Kemudian copy-paste kode di bawah

use Illuminate\Support\Facades\DB;

// 1. Count total DO belum vs sudah GR
$stats = DB::select("
SELECT 
    COUNT(DISTINCT CASE WHEN gr.id IS NULL THEN do.id END) as belum_gr,
    COUNT(DISTINCT CASE WHEN gr.id IS NOT NULL THEN do.id END) as sudah_gr,
    COUNT(DISTINCT do.id) as total_do
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
");

echo "=== STATUS DO ===\n";
echo "Belum GR: " . $stats[0]->belum_gr . "\n";
echo "Sudah GR: " . $stats[0]->sudah_gr . "\n";
echo "Total: " . $stats[0]->total_do . "\n\n";

// 2. Show first 5 DO yang belum GR dengan detail
$notReceivedDOs = DB::select("
SELECT 
    do.number,
    do.created_at,
    DATEDIFF(NOW(), DATE(do.created_at)) as days_not_received,
    o.nama_outlet,
    wo.name as warehouse_outlet,
    fo.fo_mode,
    u.nama_lengkap as created_by,
    'NOT FOUND IN GR' as gr_status
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
LEFT JOIN food_floor_orders fo ON do.floor_order_id = fo.id
LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
LEFT JOIN users u ON do.created_by = u.id
WHERE gr.id IS NULL
ORDER BY do.created_at ASC
LIMIT 5
");

echo "=== Sample DO BELUM GR (5 data pertama) ===\n";
foreach($notReceivedDOs as $do) {
    echo "DO: {$do->number} | Days: {$do->days_not_received} | Outlet: {$do->nama_outlet} | Mode: {$do->fo_mode}\n";
}
echo "\n";

// 3. Verify: Check specific DO dengan GR-nya
$specificDO = 'DO2602010068'; // Ganti dengan DO dari screenshot
$doWithGR = DB::select("
SELECT 
    do.number as do_number,
    COUNT(gr.id) as jumlah_gr,
    GROUP_CONCAT(gr.number SEPARATOR ', ') as gr_numbers
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
WHERE do.number = ?
GROUP BY do.id, do.number
", [$specificDO]);

if(!empty($doWithGR)) {
    echo "=== CHECK SPECIFIC DO ===\n";
    echo "DO: {$doWithGR[0]->do_number}\n";
    echo "Jumlah GR: {$doWithGR[0]->jumlah_gr}\n";
    echo "GR Numbers: {$doWithGR[0]->gr_numbers}\n";
    echo "Status: " . ($doWithGR[0]->jumlah_gr == 0 ? "BELUM GR ✓ CORRECT" : "SUDAH GR ✗ ERROR") . "\n";
}
