-- Query untuk melihat nomor Floor Order Bintaro yang muncul di Report Rekap FJ tanggal 7-11-2025
-- Query ini mengambil dari 2 sumber: outlet_food_good_receives dan good_receive_outlet_suppliers

-- ============================================
-- 1. Floor Orders dari outlet_food_good_receives
-- ============================================
SELECT DISTINCT
    'GR' as source_type,
    ffo.id as floor_order_id,
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at as floor_order_created_at,
    gr.id as good_receive_id,
    gr.receive_date,
    o.id_outlet,
    o.nama_outlet as customer,
    COUNT(DISTINCT i.id) as total_items,
    SUM(i.received_qty * COALESCE(fo.price, 0)) as total_amount

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    LEFT JOIN food_floor_order_items as fo ON (
        i.item_id = fo.item_id 
        AND fo.floor_order_id = do.floor_order_id
    )
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.nama_outlet LIKE '%Bintaro%'
    AND DATE(gr.receive_date) = '2025-11-07'
    AND gr.deleted_at IS NULL

GROUP BY 
    ffo.id, ffo.order_number, ffo.fo_mode, ffo.created_at,
    gr.id, gr.receive_date, o.id_outlet, o.nama_outlet

UNION ALL

-- ============================================
-- 2. Floor Orders dari good_receive_outlet_suppliers
-- ============================================
SELECT DISTINCT
    'GR Supplier' as source_type,
    ffo.id as floor_order_id,
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at as floor_order_created_at,
    gr.id as good_receive_id,
    gr.receive_date,
    o.id_outlet,
    o.nama_outlet as customer,
    COUNT(DISTINCT i.id) as total_items,
    SUM(i.qty_received * COALESCE(fo.price, 0)) as total_amount

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    JOIN good_receive_outlet_suppliers as gr ON gr.delivery_order_id = do.id
    JOIN good_receive_outlet_supplier_items as i ON gr.id = i.good_receive_id
    LEFT JOIN food_floor_order_items as fo ON (
        i.item_id = fo.item_id 
        AND fo.floor_order_id = do.floor_order_id
    )
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.nama_outlet LIKE '%Bintaro%'
    AND DATE(gr.receive_date) = '2025-11-07'

GROUP BY 
    ffo.id, ffo.order_number, ffo.fo_mode, ffo.created_at,
    gr.id, gr.receive_date, o.id_outlet, o.nama_outlet

ORDER BY 
    receive_date, 
    order_number,
    source_type;

-- ============================================
-- Query Alternatif: Hanya List Nomor Floor Order (Lebih Sederhana)
-- ============================================
SELECT DISTINCT
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at,
    COUNT(DISTINCT gr.id) as total_gr,
    COUNT(DISTINCT i.id) as total_items

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.nama_outlet LIKE '%Bintaro%'
    AND DATE(gr.receive_date) = '2025-11-07'
    AND gr.deleted_at IS NULL

GROUP BY ffo.order_number, ffo.fo_mode, ffo.created_at

UNION

SELECT DISTINCT
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at,
    COUNT(DISTINCT gr.id) as total_gr,
    COUNT(DISTINCT i.id) as total_items

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    JOIN good_receive_outlet_suppliers as gr ON gr.delivery_order_id = do.id
    JOIN good_receive_outlet_supplier_items as i ON gr.id = i.good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.nama_outlet LIKE '%Bintaro%'
    AND DATE(gr.receive_date) = '2025-11-07'

GROUP BY ffo.order_number, ffo.fo_mode, ffo.created_at

ORDER BY order_number;

