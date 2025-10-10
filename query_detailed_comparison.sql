-- Query Detail Comparison: Current vs With Packing List
-- Menampilkan perbedaan data antara kedua pendekatan

-- Query 1: Current Method (Tanpa Packing List)
WITH current_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        it.id as item_id,
        it.name as item_name,
        u.name as unit_name,
        i.received_qty as qty,
        fo.price as floor_order_price,
        (i.received_qty * fo.price) as subtotal,
        w.name as warehouse_name,
        sc.name as sub_category_name,
        do.id as delivery_order_id,
        do.floor_order_id as current_floor_order_id,
        fo.id as floor_order_item_id,
        'CURRENT' as method

    FROM outlet_food_good_receives as gr
        JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
        JOIN items as it ON i.item_id = it.id
        JOIN sub_categories as sc ON it.sub_category_id = sc.id
        JOIN units as u ON i.unit_id = u.id
        JOIN delivery_orders as do ON gr.delivery_order_id = do.id
        JOIN food_floor_order_items as fo ON (
            i.item_id = fo.item_id AND 
            fo.floor_order_id = do.floor_order_id
        )
        LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
        LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
        JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

    WHERE 
        o.id_outlet = 23
        AND YEAR(gr.receive_date) = 2025
        AND MONTH(gr.receive_date) = 9
        AND gr.deleted_at IS NULL
),

-- Query 2: With Packing List Method
with_packing_list AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        it.id as item_id,
        it.name as item_name,
        u.name as unit_name,
        i.received_qty as qty,
        fo.price as floor_order_price,
        (i.received_qty * fo.price) as subtotal,
        w.name as warehouse_name,
        sc.name as sub_category_name,
        do.id as delivery_order_id,
        pl.food_floor_order_id as packing_floor_order_id,
        fo.id as floor_order_item_id,
        pl.id as packing_list_id,
        'WITH_PACKING_LIST' as method

    FROM outlet_food_good_receives as gr
        JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
        JOIN items as it ON i.item_id = it.id
        JOIN sub_categories as sc ON it.sub_category_id = sc.id
        JOIN units as u ON i.unit_id = u.id
        JOIN delivery_orders as do ON gr.delivery_order_id = do.id
        JOIN food_packing_lists as pl ON do.packing_list_id = pl.id
        JOIN food_floor_order_items as fo ON (
            i.item_id = fo.item_id AND 
            fo.floor_order_id = pl.food_floor_order_id
        )
        LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
        LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
        JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

    WHERE 
        o.id_outlet = 23
        AND YEAR(gr.receive_date) = 2025
        AND MONTH(gr.receive_date) = 9
        AND gr.deleted_at IS NULL
)

-- Comparison Results
SELECT 
    COALESCE(c.tanggal, p.tanggal) as tanggal,
    COALESCE(c.id_outlet, p.id_outlet) as id_outlet,
    COALESCE(c.customer, p.customer) as customer,
    COALESCE(c.item_id, p.item_id) as item_id,
    COALESCE(c.item_name, p.item_name) as item_name,
    COALESCE(c.unit_name, p.unit_name) as unit_name,
    COALESCE(c.qty, p.qty) as qty,
    
    -- Current Method Data
    c.floor_order_price as current_price,
    c.subtotal as current_subtotal,
    c.current_floor_order_id,
    c.delivery_order_id as current_delivery_order_id,
    
    -- Packing List Method Data
    p.floor_order_price as packing_price,
    p.subtotal as packing_subtotal,
    p.packing_floor_order_id,
    p.packing_list_id,
    p.delivery_order_id as packing_delivery_order_id,
    
    -- Comparison
    CASE 
        WHEN c.subtotal IS NULL AND p.subtotal IS NOT NULL THEN 'ONLY_IN_PACKING_LIST'
        WHEN c.subtotal IS NOT NULL AND p.subtotal IS NULL THEN 'ONLY_IN_CURRENT'
        WHEN c.subtotal = p.subtotal THEN 'SAME'
        WHEN c.subtotal != p.subtotal THEN 'DIFFERENT'
        ELSE 'UNKNOWN'
    END as comparison_status,
    
    -- Price Difference
    COALESCE(p.subtotal, 0) - COALESCE(c.subtotal, 0) as price_difference,
    
    -- Floor Order ID Difference
    CASE 
        WHEN c.current_floor_order_id = p.packing_floor_order_id THEN 'SAME_FLOOR_ORDER'
        WHEN c.current_floor_order_id != p.packing_floor_order_id THEN 'DIFFERENT_FLOOR_ORDER'
        ELSE 'MISSING_DATA'
    END as floor_order_comparison

FROM current_method c
FULL OUTER JOIN with_packing_list p ON (
    c.tanggal = p.tanggal AND 
    c.item_id = p.item_id AND 
    c.delivery_order_id = p.delivery_order_id
)

ORDER BY 
    tanggal, 
    item_name,
    comparison_status;
