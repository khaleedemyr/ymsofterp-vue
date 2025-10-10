-- Query Rekap FJ - Method Current (Tanpa Packing List)
-- Query yang saat ini digunakan di sistem

SELECT 
    DATE(gr.receive_date) as tanggal,
    o.id_outlet,
    o.nama_outlet as customer,
    o.is_outlet,
    
    -- Main Kitchen (MK1 Hot Kitchen, MK2 Cold Kitchen)
    SUM(CASE 
        WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
        THEN i.received_qty * fo.price 
        ELSE 0 
    END) as main_kitchen,
    
    -- Main Store (bukan Chemical, Stationary, Marketing)
    SUM(CASE 
        WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
        THEN i.received_qty * fo.price 
        ELSE 0 
    END) as main_store,
    
    -- Chemical
    SUM(CASE 
        WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' 
        THEN i.received_qty * fo.price 
        ELSE 0 
    END) as chemical,
    
    -- Stationary
    SUM(CASE 
        WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' 
        THEN i.received_qty * fo.price 
        ELSE 0 
    END) as stationary,
    
    -- Marketing
    SUM(CASE 
        WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' 
        THEN i.received_qty * fo.price 
        ELSE 0 
    END) as marketing,
    
    -- Line Total
    SUM(i.received_qty * fo.price) as line_total

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

GROUP BY DATE(gr.receive_date), o.id_outlet, o.nama_outlet, o.is_outlet

ORDER BY tanggal;
