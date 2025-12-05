-- Query Summary Comparison: Current vs With Packing List
-- Menampilkan ringkasan perbedaan antara kedua pendekatan

-- Query 1: Current Method Summary
WITH current_summary AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        o.is_outlet,
        
        -- Main Kitchen
        SUM(CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_kitchen,
        
        -- Main Store
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
        SUM(i.received_qty * fo.price) as line_total,
        COUNT(*) as record_count

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
),

-- Query 2: With Packing List Method Summary
packing_list_summary AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        o.is_outlet,
        
        -- Main Kitchen
        SUM(CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_kitchen,
        
        -- Main Store
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
        SUM(i.received_qty * fo.price) as line_total,
        COUNT(*) as record_count

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

    GROUP BY DATE(gr.receive_date), o.id_outlet, o.nama_outlet, o.is_outlet
)

-- Final Comparison
SELECT 
    COALESCE(c.tanggal, p.tanggal) as tanggal,
    COALESCE(c.id_outlet, p.id_outlet) as id_outlet,
    COALESCE(c.customer, p.customer) as customer,
    COALESCE(c.is_outlet, p.is_outlet) as is_outlet,
    
    -- Current Method Results
    COALESCE(c.main_kitchen, 0) as current_main_kitchen,
    COALESCE(c.main_store, 0) as current_main_store,
    COALESCE(c.chemical, 0) as current_chemical,
    COALESCE(c.stationary, 0) as current_stationary,
    COALESCE(c.marketing, 0) as current_marketing,
    COALESCE(c.line_total, 0) as current_line_total,
    COALESCE(c.record_count, 0) as current_record_count,
    
    -- Packing List Method Results
    COALESCE(p.main_kitchen, 0) as packing_main_kitchen,
    COALESCE(p.main_store, 0) as packing_main_store,
    COALESCE(p.chemical, 0) as packing_chemical,
    COALESCE(p.stationary, 0) as packing_stationary,
    COALESCE(p.marketing, 0) as packing_marketing,
    COALESCE(p.line_total, 0) as packing_line_total,
    COALESCE(p.record_count, 0) as packing_record_count,
    
    -- Differences
    COALESCE(p.main_kitchen, 0) - COALESCE(c.main_kitchen, 0) as diff_main_kitchen,
    COALESCE(p.main_store, 0) - COALESCE(c.main_store, 0) as diff_main_store,
    COALESCE(p.chemical, 0) - COALESCE(c.chemical, 0) as diff_chemical,
    COALESCE(p.stationary, 0) - COALESCE(c.stationary, 0) as diff_stationary,
    COALESCE(p.marketing, 0) - COALESCE(c.marketing, 0) as diff_marketing,
    COALESCE(p.line_total, 0) - COALESCE(c.line_total, 0) as diff_line_total,
    COALESCE(p.record_count, 0) - COALESCE(c.record_count, 0) as diff_record_count,
    
    -- Status
    CASE 
        WHEN c.line_total IS NULL AND p.line_total IS NOT NULL THEN 'ONLY_IN_PACKING_LIST'
        WHEN c.line_total IS NOT NULL AND p.line_total IS NULL THEN 'ONLY_IN_CURRENT'
        WHEN c.line_total = p.line_total THEN 'SAME'
        WHEN c.line_total != p.line_total THEN 'DIFFERENT'
        ELSE 'UNKNOWN'
    END as comparison_status

FROM current_summary c
FULL OUTER JOIN packing_list_summary p ON (
    c.tanggal = p.tanggal AND 
    c.id_outlet = p.id_outlet
)

ORDER BY tanggal;
