-- ==============================================
-- VERIFY: Data yang ditampilkan di report benar belum di-GR
-- ==============================================

-- 1. Query untuk show DO yang belum GR (sama seperti report)
SELECT 
    do.id,
    do.number as do_number,
    do.created_at as do_date,
    DATEDIFF(NOW(), DATE(do.created_at)) as days_not_received,
    o.nama_outlet,
    wo.name as warehouse_outlet,
    fo.fo_mode,
    u.nama_lengkap as created_by,
    CASE 
        WHEN gr.id IS NULL THEN 'BELUM GR' 
        ELSE 'SUDAH GR'
    END as status
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
LEFT JOIN food_floor_orders fo ON do.floor_order_id = fo.id
LEFT JOIN tbl_data_outlet o ON fo.id_outlet = o.id_outlet
LEFT JOIN warehouse_outlets wo ON fo.warehouse_outlet_id = wo.id
LEFT JOIN users u ON do.created_by = u.id
WHERE gr.id IS NULL  -- Hanya yang BELUM GR
ORDER BY do.created_at ASC
LIMIT 20;

-- =========================================
-- 2. Validation: Check specific DO apakah sudah ada GR atau belum
-- =========================================
-- Ganti 'DO2602010068' dengan DO number dari report

SELECT 
    do.number as do_number,
    COUNT(gr.id) as jumlah_gr,
    GROUP_CONCAT(gr.number) as gr_numbers,
    GROUP_CONCAT(gr.status) as gr_statuses,
    GROUP_CONCAT(gr.created_at) as gr_dates
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
WHERE do.number = 'DO2602010068'
GROUP BY do.id, do.number;

-- =========================================
-- 3. Check: Ada berapa DO yang sudah GR vs belum GR
-- =========================================

SELECT 
    CASE 
        WHEN gr.id IS NOT NULL THEN 'SUDAH GR (Outlet)'
        ELSE 'BELUM GR (Outlet)'
    END as status,
    COUNT(DISTINCT do.id) as jumlah_do
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
GROUP BY status;

-- =========================================
-- 4. Check: RO vs non-RO DO yang belum GR
-- =========================================

SELECT 
    do.source_type,
    fo.fo_mode,
    COUNT(DISTINCT do.id) as jumlah_do
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
LEFT JOIN food_floor_orders fo ON do.floor_order_id = fo.id
WHERE gr.id IS NULL
GROUP BY do.source_type, fo.fo_mode;

-- =========================================
-- 5. Check: apakah ada DO yang orphan (floor_order_id null)?
-- =========================================

SELECT 
    do.id,
    do.number,
    do.floor_order_id,
    do.packing_list_id,
    do.ro_supplier_gr_id,
    'ORPHAN - No Floor Order' as issue
FROM delivery_orders do
LEFT JOIN outlet_food_good_receives gr ON gr.delivery_order_id = do.id AND gr.deleted_at IS NULL
WHERE gr.id IS NULL AND do.floor_order_id IS NULL
LIMIT 20;
