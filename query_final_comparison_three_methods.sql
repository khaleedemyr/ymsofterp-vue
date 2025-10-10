-- Query Final Comparison: 3 Methods
-- 1. Current (GR → Floor Order langsung)
-- 2. With Packing List (GR → Packing List → Floor Order)
-- 3. With Packing List Items (GR → Packing List Items → Floor Order Items)

-- Method 1: Current (Tanpa Packing List)
WITH current_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        o.is_outlet,
        
        SUM(CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_kitchen,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_store,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as chemical,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as stationary,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as marketing,
        
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

-- Method 2: With Packing List
packing_list_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        o.is_outlet,
        
        SUM(CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_kitchen,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_store,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as chemical,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as stationary,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as marketing,
        
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
),

-- Method 3: With Packing List Items
packing_list_items_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        o.id_outlet,
        o.nama_outlet as customer,
        o.is_outlet,
        
        SUM(CASE 
            WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') 
            THEN pli.qty * fo.price 
            ELSE 0 
        END) as main_kitchen,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
            THEN pli.qty * fo.price 
            ELSE 0 
        END) as main_store,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' 
            THEN pli.qty * fo.price 
            ELSE 0 
        END) as chemical,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' 
            THEN pli.qty * fo.price 
            ELSE 0 
        END) as stationary,
        
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' 
            THEN pli.qty * fo.price 
            ELSE 0 
        END) as marketing,
        
        SUM(pli.qty * fo.price) as line_total,
        COUNT(*) as record_count

    FROM outlet_food_good_receives as gr
        JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
        JOIN items as it ON i.item_id = it.id
        JOIN sub_categories as sc ON it.sub_category_id = sc.id
        JOIN units as u ON i.unit_id = u.id
        JOIN delivery_orders as do ON gr.delivery_order_id = do.id
        JOIN food_packing_lists as pl ON do.packing_list_id = pl.id
        JOIN food_packing_list_items as pli ON pl.id = pli.packing_list_id
        JOIN food_floor_order_items as fo ON pli.food_floor_order_item_id = fo.id
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
    COALESCE(c.tanggal, p.tanggal, pli.tanggal) as tanggal,
    COALESCE(c.id_outlet, p.id_outlet, pli.id_outlet) as id_outlet,
    COALESCE(c.customer, p.customer, pli.customer) as customer,
    COALESCE(c.is_outlet, p.is_outlet, pli.is_outlet) as is_outlet,
    
    -- Method 1: Current
    COALESCE(c.main_kitchen, 0) as current_main_kitchen,
    COALESCE(c.main_store, 0) as current_main_store,
    COALESCE(c.chemical, 0) as current_chemical,
    COALESCE(c.stationary, 0) as current_stationary,
    COALESCE(c.marketing, 0) as current_marketing,
    COALESCE(c.line_total, 0) as current_line_total,
    COALESCE(c.record_count, 0) as current_record_count,
    
    -- Method 2: With Packing List
    COALESCE(p.main_kitchen, 0) as packing_main_kitchen,
    COALESCE(p.main_store, 0) as packing_main_store,
    COALESCE(p.chemical, 0) as packing_chemical,
    COALESCE(p.stationary, 0) as packing_stationary,
    COALESCE(p.marketing, 0) as packing_marketing,
    COALESCE(p.line_total, 0) as packing_line_total,
    COALESCE(p.record_count, 0) as packing_record_count,
    
    -- Method 3: With Packing List Items
    COALESCE(pli.main_kitchen, 0) as packing_items_main_kitchen,
    COALESCE(pli.main_store, 0) as packing_items_main_store,
    COALESCE(pli.chemical, 0) as packing_items_chemical,
    COALESCE(pli.stationary, 0) as packing_items_stationary,
    COALESCE(pli.marketing, 0) as packing_items_marketing,
    COALESCE(pli.line_total, 0) as packing_items_line_total,
    COALESCE(pli.record_count, 0) as packing_items_record_count,
    
    -- Differences: Packing List vs Current
    COALESCE(p.line_total, 0) - COALESCE(c.line_total, 0) as diff_packing_vs_current,
    
    -- Differences: Packing List Items vs Current
    COALESCE(pli.line_total, 0) - COALESCE(c.line_total, 0) as diff_packing_items_vs_current,
    
    -- Differences: Packing List Items vs Packing List
    COALESCE(pli.line_total, 0) - COALESCE(p.line_total, 0) as diff_packing_items_vs_packing,
    
    -- Status
    CASE 
        WHEN c.line_total IS NULL AND p.line_total IS NULL AND pli.line_total IS NULL THEN 'NO_DATA'
        WHEN c.line_total IS NOT NULL AND p.line_total IS NULL AND pli.line_total IS NULL THEN 'ONLY_CURRENT'
        WHEN c.line_total IS NULL AND p.line_total IS NOT NULL AND pli.line_total IS NULL THEN 'ONLY_PACKING'
        WHEN c.line_total IS NULL AND p.line_total IS NULL AND pli.line_total IS NOT NULL THEN 'ONLY_PACKING_ITEMS'
        WHEN c.line_total = p.line_total AND p.line_total = pli.line_total THEN 'ALL_SAME'
        WHEN c.line_total != p.line_total OR p.line_total != pli.line_total THEN 'DIFFERENT'
        ELSE 'MIXED'
    END as comparison_status

FROM current_method c
FULL OUTER JOIN packing_list_method p ON (
    c.tanggal = p.tanggal AND 
    c.id_outlet = p.id_outlet
)
FULL OUTER JOIN packing_list_items_method pli ON (
    COALESCE(c.tanggal, p.tanggal) = pli.tanggal AND 
    COALESCE(c.id_outlet, p.id_outlet) = pli.id_outlet
)

ORDER BY tanggal;
