-- ============================================
-- QUERY REPORT REKAP FJ - ANALISIS UNTUK OUTLET_ID=20, TANGGAL=2025-11-19
-- ============================================

-- ============================================
-- 1. QUERY UTAMA - OUTLET FOOD GOOD RECEIVES (GR)
-- ============================================
-- Query ini adalah query utama yang menghitung nilai per item, kemudian di-aggregate per outlet
-- Filter: receive_date = 2025-11-19, outlet_id = 20

SELECT 
    o.nama_outlet as customer,
    o.id_outlet,
    o.is_outlet,
    it.id as item_id,
    it.name as item_name,
    sc.name as sub_category,
    w.name as warehouse,
    i.received_qty,
    COALESCE(fo.price, 0) as price,
    (i.received_qty * COALESCE(fo.price, 0)) as item_subtotal,
    gr.receive_date,
    gr.id as gr_id,
    gr.number as gr_number
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
ORDER BY o.nama_outlet, it.name;

-- ============================================
-- 2. QUERY GROUP BY ITEM (SEBELUM AGGREGATE PER OUTLET)
-- ============================================
-- Query ini sama dengan query #1, tapi sudah di-group by item
-- Ini adalah query yang digunakan di controller sebelum di-aggregate per outlet

SELECT 
    o.nama_outlet as customer,
    o.is_outlet,
    it.id as item_id,
    it.name as item_name,
    sc.name as sub_category,
    w.name as warehouse,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as item_subtotal
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY o.nama_outlet, o.is_outlet, it.id, it.name, sc.name, w.name
ORDER BY o.nama_outlet, it.name;

-- ============================================
-- 3. QUERY UNTUK CEK DATA GR YANG TERLIBAT
-- ============================================
-- Cek semua GR di outlet_id=20 pada tanggal 2025-11-19

SELECT 
    gr.id,
    gr.number,
    gr.receive_date,
    gr.outlet_id,
    o.nama_outlet,
    gr.delivery_order_id,
    gr.deleted_at,
    COUNT(i.id) as total_items
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
LEFT JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
GROUP BY gr.id, gr.number, gr.receive_date, gr.outlet_id, o.nama_outlet, gr.delivery_order_id, gr.deleted_at
ORDER BY gr.id;

-- ============================================
-- 4. QUERY UNTUK CEK ITEM DETAIL PER GR
-- ============================================
-- Cek detail item di setiap GR, termasuk price dari food_floor_order_items

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    i.received_qty,
    i.unit_id,
    u.name as unit_name,
    do.id as delivery_order_id,
    do.floor_order_id,
    fo.id as floor_order_item_id,
    fo.price as floor_order_price,
    (i.received_qty * COALESCE(fo.price, 0)) as subtotal,
    w.name as warehouse,
    sc.name as sub_category
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
LEFT JOIN sub_categories as sc ON it.sub_category_id = sc.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
ORDER BY gr.id, i.id;

-- ============================================
-- 5. QUERY UNTUK CEK APAKAH ADA ITEM TANPA WAREHOUSE
-- ============================================
-- Item yang tidak punya warehouse akan di-exclude dari report

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    i.item_id,
    it.name as item_name,
    it.warehouse_division_id,
    wd.id as warehouse_division_id,
    wd.warehouse_id,
    w.id as warehouse_id,
    w.name as warehouse_name
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NULL
ORDER BY gr.id, i.item_id;

-- ============================================
-- 6. QUERY UNTUK CEK APAKAH ADA ITEM YANG DI-DELETE
-- ============================================
-- GR yang sudah di-delete akan di-exclude dari report

SELECT 
    gr.id,
    gr.number,
    gr.receive_date,
    gr.outlet_id,
    o.nama_outlet,
    gr.deleted_at
FROM outlet_food_good_receives as gr
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NOT NULL
ORDER BY gr.id;

-- ============================================
-- 7. QUERY UNTUK CEK FLOOR ORDER ITEMS (UNTUK PRICE)
-- ============================================
-- Cek apakah price dari food_floor_order_items sudah benar

SELECT 
    fo.id,
    fo.floor_order_id,
    fo.item_id,
    it.name as item_name,
    fo.price,
    do.id as delivery_order_id,
    do.floor_order_id as do_floor_order_id,
    gr.id as gr_id,
    gr.receive_date,
    gr.outlet_id
FROM food_floor_order_items as fo
JOIN items as it ON fo.item_id = it.id
JOIN delivery_orders as do ON fo.floor_order_id = do.floor_order_id
JOIN outlet_food_good_receives as gr ON do.id = gr.delivery_order_id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
ORDER BY fo.floor_order_id, fo.item_id;

-- ============================================
-- 8. QUERY UNTUK CEK MATCHING ANTARA GR ITEM DAN FLOOR ORDER ITEM
-- ============================================
-- Cek apakah item di GR match dengan item di floor_order_items untuk mendapatkan price

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    i.item_id as gr_item_id,
    it.name as item_name,
    do.floor_order_id,
    fo.item_id as fo_item_id,
    fo.price,
    CASE 
        WHEN fo.id IS NOT NULL THEN 'MATCH'
        ELSE 'NO MATCH - PRICE = 0'
    END as match_status
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
ORDER BY gr.id, i.item_id;

-- ============================================
-- 9. QUERY UNTUK CEK TOTAL PER WAREHOUSE (SEBELUM KATEGORISASI)
-- ============================================
-- Cek total per warehouse sebelum di-kategorisasi ke main_kitchen, main_store, dll

SELECT 
    o.nama_outlet as customer,
    w.name as warehouse,
    sc.name as sub_category,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as total_subtotal
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY o.nama_outlet, w.name, sc.name
ORDER BY o.nama_outlet, w.name, sc.name;

-- ============================================
-- 10. QUERY UNTUK CEK KATEGORISASI (MAIN KITCHEN, MAIN STORE, dll)
-- ============================================
-- Cek bagaimana data di-kategorisasi berdasarkan warehouse dan sub_category

SELECT 
    o.nama_outlet as customer,
    w.name as warehouse,
    sc.name as sub_category,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal,
    CASE 
        WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'main_kitchen'
        WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(sc.name) = 'CHEMICAL' THEN 'chemical'
        WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(sc.name) = 'STATIONARY' THEN 'stationary'
        WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(sc.name) = 'MARKETING' THEN 'marketing'
        WHEN UPPER(w.name) = 'MAIN STORE' THEN 'main_store'
        ELSE 'other'
    END as category
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY o.nama_outlet, w.name, sc.name
ORDER BY o.nama_outlet, category, sc.name;

-- ============================================
-- 11. QUERY FINAL - HASIL AGGREGATE PER OUTLET (SESUAI LOGIKA CONTROLLER)
-- ============================================
-- Query ini menghasilkan hasil final seperti yang ditampilkan di report

SELECT 
    o.nama_outlet as customer,
    o.is_outlet,
    SUM(CASE 
        WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
        THEN (i.received_qty * COALESCE(fo.price, 0)) 
        ELSE 0 
    END) as main_kitchen,
    SUM(CASE 
        WHEN UPPER(w.name) = 'MAIN STORE' 
        AND UPPER(sc.name) NOT IN ('CHEMICAL', 'STATIONARY', 'MARKETING')
        THEN (i.received_qty * COALESCE(fo.price, 0)) 
        ELSE 0 
    END) as main_store,
    SUM(CASE 
        WHEN UPPER(w.name) = 'MAIN STORE' 
        AND UPPER(sc.name) = 'CHEMICAL'
        THEN (i.received_qty * COALESCE(fo.price, 0)) 
        ELSE 0 
    END) as chemical,
    SUM(CASE 
        WHEN UPPER(w.name) = 'MAIN STORE' 
        AND UPPER(sc.name) = 'STATIONARY'
        THEN (i.received_qty * COALESCE(fo.price, 0)) 
        ELSE 0 
    END) as stationary,
    SUM(CASE 
        WHEN UPPER(w.name) = 'MAIN STORE' 
        AND UPPER(sc.name) = 'MARKETING'
        THEN (i.received_qty * COALESCE(fo.price, 0)) 
        ELSE 0 
    END) as marketing,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as line_total
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY o.nama_outlet, o.is_outlet
ORDER BY o.nama_outlet;

-- ============================================
-- 12. QUERY UNTUK CEK PERBANDINGAN - RANGE TANGGAL
-- ============================================
-- Cek data untuk range tanggal (jika user filter by range)

SELECT 
    DATE(gr.receive_date) as receive_date,
    o.nama_outlet as customer,
    o.id_outlet,
    COUNT(DISTINCT gr.id) as total_gr,
    COUNT(i.id) as total_items,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as total_value
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE gr.outlet_id = 20
  AND DATE(gr.receive_date) BETWEEN '2025-11-15' AND '2025-11-20'
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY DATE(gr.receive_date), o.nama_outlet, o.id_outlet
ORDER BY receive_date, o.nama_outlet;

-- ============================================
-- 13. QUERY UNTUK CEK SEMUA ITEM (TANPA FILTER WAREHOUSE)
-- ============================================
-- Query ini untuk membandingkan dengan modal detail penjualan
-- Modal detail penjualan TIDAK filter w.name IS NOT NULL
-- Jadi query ini akan menampilkan SEMUA item, termasuk yang tidak punya warehouse

SELECT 
    o.nama_outlet as customer,
    o.id_outlet,
    it.id as item_id,
    it.name as item_name,
    sc.name as sub_category,
    w.name as warehouse,
    i.received_qty,
    COALESCE(fo.price, 0) as price,
    (i.received_qty * COALESCE(fo.price, 0)) as item_subtotal,
    gr.receive_date,
    gr.id as gr_id,
    gr.number as gr_number,
    CASE 
        WHEN w.name IS NULL THEN 'TIDAK PUNYA WAREHOUSE - TIDAK MUNCUL DI REPORT REKAP FJ'
        ELSE 'PUNYA WAREHOUSE - MUNCUL DI REPORT REKAP FJ'
    END as status_warehouse
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  -- TIDAK ADA FILTER w.name IS NOT NULL - INI SAMA DENGAN MODAL DETAIL PENJUALAN
ORDER BY o.nama_outlet, it.name;

-- ============================================
-- 14. QUERY UNTUK CEK PERBANDINGAN - DENGAN DAN TANPA FILTER WAREHOUSE
-- ============================================
-- Query ini untuk melihat perbedaan jumlah item dengan dan tanpa filter warehouse

SELECT 
    'DENGAN FILTER w.name IS NOT NULL' as query_type,
    COUNT(DISTINCT i.id) as total_items,
    COUNT(DISTINCT gr.id) as total_gr,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as total_value
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL

UNION ALL

SELECT 
    'TANPA FILTER w.name IS NOT NULL' as query_type,
    COUNT(DISTINCT i.id) as total_items,
    COUNT(DISTINCT gr.id) as total_gr,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as total_value
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL;

-- ============================================
-- 15. QUERY SESUAI MODAL DETAIL PENJUALAN (fjDetail)
-- ============================================
-- Query ini sama persis dengan yang digunakan di modal detail penjualan
-- Filter: customer = 'Justus Steak House Cipete', from = '2025-11-19', to = '2025-11-19'
-- Kategori: MAIN STORE (tanpa Chemical, Stationary, Marketing)

SELECT 
    it.name as item_name,
    cat.name as category,
    u.name as unit,
    SUM(i.received_qty) as received_qty,
    AVG(COALESCE(fo.price, 0)) as price,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN categories as cat ON it.category_id = cat.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE o.nama_outlet = 'Justus Steak House Cipete'
  AND DATE(gr.receive_date) >= '2025-11-19'
  AND DATE(gr.receive_date) <= '2025-11-19'
  AND gr.deleted_at IS NULL
  AND w.name = 'MAIN STORE'
  AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing')
GROUP BY it.name, cat.name, u.name
ORDER BY cat.name, it.name;

-- ============================================
-- 16. QUERY UNTUK CEK SEMUA ITEM DI SEMUA GR (TANPA FILTER APAPUN)
-- ============================================
-- Query ini untuk menemukan item yang hilang
-- Tidak ada filter warehouse, tidak ada filter deleted_at
-- Ini untuk melihat SEMUA item yang ada di database

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    gr.deleted_at,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    it.warehouse_division_id,
    wd.id as wd_id,
    wd.warehouse_id,
    w.id as warehouse_id,
    w.name as warehouse_name,
    CASE 
        WHEN gr.deleted_at IS NOT NULL THEN 'GR DIHAPUS'
        WHEN w.name IS NULL THEN 'TIDAK PUNYA WAREHOUSE'
        ELSE 'OK'
    END as status_item
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
ORDER BY gr.id, i.id;

-- ============================================
-- 17. QUERY UNTUK CEK ITEM YANG HILANG (COMPARISON)
-- ============================================
-- Query ini untuk membandingkan semua item vs item yang muncul di query utama

-- Semua item (tanpa filter)
SELECT 
    'SEMUA ITEM' as query_type,
    COUNT(DISTINCT i.id) as total_item_receives,
    COUNT(DISTINCT i.item_id) as total_unique_items,
    COUNT(DISTINCT gr.id) as total_gr
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20

UNION ALL

-- Item yang muncul di query utama (dengan filter)
SELECT 
    'ITEM YANG MUNCUL DI QUERY UTAMA' as query_type,
    COUNT(DISTINCT i.id) as total_item_receives,
    COUNT(DISTINCT i.item_id) as total_unique_items,
    COUNT(DISTINCT gr.id) as total_gr
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL;

-- ============================================
-- 18. QUERY UNTUK CEK ITEM YANG DI-EXCLUDE
-- ============================================
-- Item yang di-exclude karena tidak punya warehouse atau GR dihapus
-- PERBAIKAN: Query ini harus menemukan item yang tidak punya warehouse

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.deleted_at,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    it.warehouse_division_id,
    wd.id as wd_id,
    wd.warehouse_id,
    w.id as warehouse_id,
    w.name as warehouse_name,
    CASE 
        WHEN gr.deleted_at IS NOT NULL THEN 'EXCLUDE: GR DIHAPUS'
        WHEN w.name IS NULL THEN 'EXCLUDE: TIDAK PUNYA WAREHOUSE'
        ELSE 'OK - TIDAK SEHARUSNYA MUNCUL DI QUERY INI'
    END as reason_excluded
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND (
    gr.deleted_at IS NOT NULL 
    OR w.name IS NULL 
    OR (it.warehouse_division_id IS NOT NULL AND wd.id IS NULL)
    OR (wd.warehouse_id IS NOT NULL AND w.id IS NULL)
  )
ORDER BY 
    CASE 
        WHEN gr.deleted_at IS NOT NULL THEN 1
        WHEN w.name IS NULL THEN 2
        ELSE 3
    END,
    gr.id, 
    i.id;

-- ============================================
-- 19. QUERY UNTUK CEK APAKAH ADA ITEM DI GR SUPPLIER
-- ============================================
-- Mungkin ada item yang ada di good_receive_outlet_suppliers
-- Tapi di reportSalesPivotSpecial tidak include GR Supplier

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    i.qty_received,
    w.name as warehouse_name
FROM good_receive_outlet_suppliers as gr
JOIN good_receive_outlet_supplier_items as i ON gr.id = i.good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
ORDER BY gr.id, i.id;

-- ============================================
-- 20. QUERY UNTUK CEK PER GR (DETAIL)
-- ============================================
-- Cek detail item per GR untuk memastikan tidak ada yang terlewat
-- PERBAIKAN: Tambahkan detail untuk melihat item yang tidak punya warehouse

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    gr.deleted_at,
    COUNT(i.id) as total_items_in_gr,
    COUNT(CASE WHEN w.name IS NOT NULL THEN 1 END) as items_with_warehouse,
    COUNT(CASE WHEN w.name IS NULL THEN 1 END) as items_without_warehouse,
    COUNT(CASE WHEN it.warehouse_division_id IS NULL THEN 1 END) as items_without_warehouse_division,
    COUNT(CASE WHEN it.warehouse_division_id IS NOT NULL AND wd.id IS NULL THEN 1 END) as items_with_invalid_warehouse_division,
    COUNT(CASE WHEN wd.warehouse_id IS NOT NULL AND w.id IS NULL THEN 1 END) as items_with_invalid_warehouse
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
GROUP BY gr.id, gr.number, gr.receive_date, gr.deleted_at
ORDER BY gr.id;

-- ============================================
-- 21. QUERY UNTUK CEK ITEM YANG DUPLIKAT (SAME ITEM DI MULTIPLE GR)
-- ============================================
-- Cek apakah ada item yang sama muncul di beberapa GR
-- Ini untuk memastikan perhitungan yang benar

SELECT 
    i.item_id,
    it.name as item_name,
    COUNT(DISTINCT gr.id) as total_gr,
    COUNT(i.id) as total_item_receives,
    SUM(i.received_qty) as total_qty,
    GROUP_CONCAT(DISTINCT gr.number ORDER BY gr.number SEPARATOR ', ') as gr_numbers
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
GROUP BY i.item_id, it.name
HAVING COUNT(i.id) > 1
ORDER BY i.item_id;

-- ============================================
-- 22. QUERY UNTUK CEK PERBANDINGAN - QUERY UTAMA VS QUERY 16
-- ============================================
-- Query ini untuk menemukan item yang hilang karena JOIN
-- Query utama menggunakan JOIN (bukan LEFT JOIN) dengan beberapa tabel
-- Jika item tidak punya data di tabel tersebut, akan di-exclude

-- Query utama (dengan semua JOIN seperti di controller)
SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    sc.name as sub_category,
    w.name as warehouse,
    do.id as delivery_order_id,
    do.floor_order_id,
    fo.id as floor_order_item_id,
    fo.price as floor_order_price,
    u.id as unit_id,
    u.name as unit_name,
    o.id_outlet,
    o.nama_outlet
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id  -- JOIN (bukan LEFT JOIN)
JOIN units as u ON i.unit_id = u.id  -- JOIN (bukan LEFT JOIN)
JOIN delivery_orders as do ON gr.delivery_order_id = do.id  -- JOIN (bukan LEFT JOIN)
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL
ORDER BY gr.id, i.id;

-- ============================================
-- 23. QUERY UNTUK CEK ITEM YANG HILANG KARENA JOIN
-- ============================================
-- Cek item yang tidak punya sub_category, unit, atau delivery_order

SELECT 
    'ITEM TANPA SUB_CATEGORY' as reason,
    gr.id as gr_id,
    gr.number as gr_number,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    it.sub_category_id,
    sc.id as sc_id
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN sub_categories as sc ON it.sub_category_id = sc.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND (it.sub_category_id IS NULL OR sc.id IS NULL)

UNION ALL

SELECT 
    'ITEM TANPA UNIT' as reason,
    gr.id as gr_id,
    gr.number as gr_number,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    i.unit_id,
    u.id as u_id
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN units as u ON i.unit_id = u.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND (i.unit_id IS NULL OR u.id IS NULL)

UNION ALL

SELECT 
    'GR TANPA DELIVERY_ORDER' as reason,
    gr.id as gr_id,
    gr.number as gr_number,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    gr.delivery_order_id,
    do.id as do_id
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND (gr.delivery_order_id IS NULL OR do.id IS NULL)

ORDER BY reason, gr_id, item_id;

-- ============================================
-- 24. QUERY UNTUK CEK PERBANDINGAN COUNT
-- ============================================
-- Bandingkan jumlah item dengan dan tanpa JOIN yang ketat

SELECT 
    'QUERY 16 (TANPA JOIN KETAT)' as query_type,
    COUNT(DISTINCT i.id) as total_item_receives
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL

UNION ALL

SELECT 
    'QUERY UTAMA (DENGAN JOIN KETAT)' as query_type,
    COUNT(DISTINCT i.id) as total_item_receives
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
JOIN sub_categories as sc ON it.sub_category_id = sc.id
JOIN units as u ON i.unit_id = u.id
JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN food_floor_order_items as fo ON i.item_id = fo.item_id 
    AND fo.floor_order_id = do.floor_order_id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
  AND w.name IS NOT NULL;

-- ============================================
-- 25. QUERY UNTUK CEK ITEM YANG HILANG (DETAIL)
-- ============================================
-- Cek item yang ada di query 16 tapi tidak ada di query utama

SELECT 
    q16.gr_id,
    q16.gr_number,
    q16.item_receive_id,
    q16.item_id,
    q16.item_name,
    q16.warehouse_name,
    'ADA DI QUERY 16, TIDAK ADA DI QUERY UTAMA' as status
FROM (
    SELECT 
        gr.id as gr_id,
        gr.number as gr_number,
        i.id as item_receive_id,
        i.item_id,
        it.name as item_name,
        w.name as warehouse_name
    FROM outlet_food_good_receives as gr
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN items as it ON i.item_id = it.id
    LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
    LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
    WHERE DATE(gr.receive_date) = '2025-11-19'
      AND gr.outlet_id = 20
      AND gr.deleted_at IS NULL
      AND w.name IS NOT NULL
) as q16
LEFT JOIN (
    SELECT DISTINCT
        gr.id as gr_id,
        i.id as item_receive_id
    FROM outlet_food_good_receives as gr
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN items as it ON i.item_id = it.id
    JOIN sub_categories as sc ON it.sub_category_id = sc.id
    JOIN units as u ON i.unit_id = u.id
    JOIN delivery_orders as do ON gr.delivery_order_id = do.id
    LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
    LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
    WHERE DATE(gr.receive_date) = '2025-11-19'
      AND gr.outlet_id = 20
      AND gr.deleted_at IS NULL
      AND w.name IS NOT NULL
) as q_main ON q16.gr_id = q_main.gr_id AND q16.item_receive_id = q_main.item_receive_id
WHERE q_main.gr_id IS NULL
ORDER BY q16.gr_id, q16.item_id;

-- ============================================
-- 26. QUERY UNTUK CEK 3 ITEM YANG HILANG (DETAIL DEBUG)
-- ============================================
-- Cek detail 3 item yang hilang: Mashed Potato Puree, Potato Straight Cut, Potato Wedges
-- GR ID: 12553, Item IDs: 53219, 53224, 53225

SELECT 
    'DATA GR' as check_type,
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    gr.delivery_order_id,
    gr.deleted_at,
    do.id as do_id,
    do.floor_order_id,
    do.packing_list_id
FROM outlet_food_good_receives as gr
LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
WHERE gr.id = 12553;

SELECT 
    'DATA ITEM RECEIVE' as check_type,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    i.unit_id,
    it.sub_category_id,
    i.received_qty
FROM outlet_food_good_receive_items as i
JOIN items as it ON i.item_id = it.id
WHERE i.outlet_food_good_receive_id = 12553
  AND i.item_id IN (53219, 53224, 53225);

SELECT 
    'DATA SUB_CATEGORY' as check_type,
    it.id as item_id,
    it.name as item_name,
    it.sub_category_id,
    sc.id as sc_id,
    sc.name as sub_category_name
FROM items as it
LEFT JOIN sub_categories as sc ON it.sub_category_id = sc.id
WHERE it.id IN (53219, 53224, 53225);

SELECT 
    'DATA UNIT' as check_type,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    i.unit_id,
    u.id as u_id,
    u.name as unit_name
FROM outlet_food_good_receive_items as i
JOIN items as it ON i.item_id = it.id
LEFT JOIN units as u ON i.unit_id = u.id
WHERE i.outlet_food_good_receive_id = 12553
  AND i.item_id IN (53219, 53224, 53225);

SELECT 
    'DATA DELIVERY_ORDER' as check_type,
    gr.id as gr_id,
    gr.delivery_order_id,
    do.id as do_id,
    do.floor_order_id,
    do.packing_list_id,
    CASE 
        WHEN gr.delivery_order_id IS NULL THEN 'GR TIDAK PUNYA delivery_order_id'
        WHEN do.id IS NULL THEN 'delivery_order_id TIDAK ADA DI TABEL delivery_orders'
        ELSE 'OK'
    END as status
FROM outlet_food_good_receives as gr
LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
WHERE gr.id = 12553;

-- ============================================
-- 27. QUERY UNTUK CEK SEMUA JOIN UNTUK 3 ITEM YANG HILANG
-- ============================================
-- Cek apakah semua JOIN berhasil untuk 3 item yang hilang

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    i.id as item_receive_id,
    i.item_id,
    it.name as item_name,
    CASE WHEN sc.id IS NULL THEN 'TIDAK PUNYA SUB_CATEGORY' ELSE 'OK' END as sub_category_status,
    CASE WHEN u.id IS NULL THEN 'TIDAK PUNYA UNIT' ELSE 'OK' END as unit_status,
    CASE WHEN do.id IS NULL THEN 'TIDAK PUNYA DELIVERY_ORDER' ELSE 'OK' END as delivery_order_status,
    CASE WHEN w.name IS NULL THEN 'TIDAK PUNYA WAREHOUSE' ELSE 'OK' END as warehouse_status
FROM outlet_food_good_receives as gr
JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
JOIN items as it ON i.item_id = it.id
LEFT JOIN sub_categories as sc ON it.sub_category_id = sc.id
LEFT JOIN units as u ON i.unit_id = u.id
LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
WHERE gr.id = 12553
  AND i.item_id IN (53219, 53224, 53225);

-- ============================================
-- 28. QUERY UNTUK CEK APAKAH DELIVERY_ORDER NULL ATAU TIDAK VALID
-- ============================================
-- Cek semua GR di tanggal tersebut yang tidak punya delivery_order

SELECT 
    gr.id as gr_id,
    gr.number as gr_number,
    gr.receive_date,
    gr.delivery_order_id,
    do.id as do_id,
    COUNT(i.id) as total_items,
    CASE 
        WHEN gr.delivery_order_id IS NULL THEN 'GR TIDAK PUNYA delivery_order_id'
        WHEN do.id IS NULL THEN 'delivery_order_id TIDAK VALID (tidak ada di tabel delivery_orders)'
        ELSE 'OK'
    END as status
FROM outlet_food_good_receives as gr
LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
LEFT JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
WHERE DATE(gr.receive_date) = '2025-11-19'
  AND gr.outlet_id = 20
  AND gr.deleted_at IS NULL
GROUP BY gr.id, gr.number, gr.receive_date, gr.delivery_order_id, do.id
HAVING gr.delivery_order_id IS NULL OR do.id IS NULL
ORDER BY gr.id;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- PERBEDAAN UTAMA:
-- 1. REPORT REKAP FJ (reportSalesPivotSpecial):
--    - Filter: w.name IS NOT NULL (exclude item tanpa warehouse)
--    - Group by item dulu, lalu aggregate per outlet
-- 
-- 2. MODAL DETAIL PENJUALAN (fjDetail):
--    - TIDAK ada filter w.name IS NOT NULL (include semua item)
--    - Group by item_name, category, unit
--
-- 3. Filter utama: receive_date = '2025-11-19' dan outlet_id = 20
-- 4. Validasi: gr.deleted_at IS NULL (GR tidak boleh di-delete)
-- 5. Price diambil dari food_floor_order_items berdasarkan:
--    - i.item_id = fo.item_id
--    - fo.floor_order_id = do.floor_order_id
-- 6. Jika tidak ada match di food_floor_order_items, price = 0
-- 7. Kategorisasi berdasarkan warehouse dan sub_category:
--    - main_kitchen: warehouse IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen')
--    - main_store: warehouse = 'MAIN STORE' AND sub_category NOT IN ('Chemical', 'Stationary', 'Marketing')
--    - chemical: warehouse = 'MAIN STORE' AND sub_category = 'Chemical'
--    - stationary: warehouse = 'MAIN STORE' AND sub_category = 'Stationary'
--    - marketing: warehouse = 'MAIN STORE' AND sub_category = 'Marketing'
-- ============================================

