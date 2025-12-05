-- Query Rekap FJ untuk Outlet ID 23 per hari di bulan September (Group by Kategori)
-- Menampilkan total per kategori per hari

SELECT 
    tanggal,
    customer,
    kategori,
    SUM(qty) as total_qty,
    SUM(subtotal) as total_amount,
    COUNT(DISTINCT item_name) as item_count,
    GROUP_CONCAT(DISTINCT source_table) as sources
FROM (
    -- Query 1: Data dari outlet_food_good_receives
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.nama_outlet as customer,
        it.name as item_name,
        i.received_qty as qty,
        (i.received_qty * fo.price) as subtotal,
        CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
            ELSE 'Other'
        END as kategori,
        'outlet_food_good_receives' as source_table

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

    UNION ALL

    -- Query 2: Data dari good_receive_outlet_suppliers
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.nama_outlet as customer,
        it.name as item_name,
        i.qty_received as qty,
        (i.qty_received * COALESCE(fo.price, 0)) as subtotal,
        CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN 'Main Kitchen'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN 'Chemical'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN 'Stationary'
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN 'Marketing'
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN 'Main Store'
            ELSE 'Other'
        END as kategori,
        'good_receive_outlet_suppliers' as source_table

    FROM good_receive_outlet_suppliers as gr
        JOIN good_receive_outlet_supplier_items as i ON gr.id = i.good_receive_id
        JOIN items as it ON i.item_id = it.id
        JOIN sub_categories as sc ON it.sub_category_id = sc.id
        JOIN units as u ON i.unit_id = u.id
        LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
        LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
        LEFT JOIN delivery_orders as do ON gr.delivery_order_id = do.id
        LEFT JOIN food_floor_order_items as fo ON (
            i.item_id = fo.item_id AND 
            fo.floor_order_id = do.floor_order_id
        )
        JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

    WHERE 
        o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
) as detail_data

GROUP BY tanggal, customer, kategori
ORDER BY tanggal, 
    CASE kategori
        WHEN 'Main Kitchen' THEN 1
        WHEN 'Main Store' THEN 2
        WHEN 'Chemical' THEN 3
        WHEN 'Stationary' THEN 4
        WHEN 'Marketing' THEN 5
        ELSE 6
    END;
