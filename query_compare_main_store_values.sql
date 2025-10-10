-- Query untuk membandingkan nilai main_store antara current method dan packing list method
-- Fokus pada kolom tanggal dan main_store

-- Current Method
WITH current_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_store_current

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

    GROUP BY DATE(gr.receive_date)
),

-- Packing List Method
packing_list_method AS (
    SELECT 
        DATE(gr.receive_date) as tanggal,
        SUM(CASE 
            WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') 
            THEN i.received_qty * fo.price 
            ELSE 0 
        END) as main_store_packing

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

    GROUP BY DATE(gr.receive_date)
)

-- Comparison
SELECT 
    COALESCE(c.tanggal, p.tanggal) as tanggal,
    COALESCE(c.main_store_current, 0) as main_store_current,
    COALESCE(p.main_store_packing, 0) as main_store_packing,
    COALESCE(p.main_store_packing, 0) - COALESCE(c.main_store_current, 0) as difference,
    CASE 
        WHEN c.main_store_current IS NULL AND p.main_store_packing IS NOT NULL THEN 'ONLY_IN_PACKING_LIST'
        WHEN c.main_store_current IS NOT NULL AND p.main_store_packing IS NULL THEN 'ONLY_IN_CURRENT'
        WHEN c.main_store_current = p.main_store_packing THEN 'SAME'
        WHEN c.main_store_current != p.main_store_packing THEN 'DIFFERENT'
        ELSE 'UNKNOWN'
    END as comparison_status,
    ROUND(
        CASE 
            WHEN c.main_store_current > 0 THEN 
                ((COALESCE(p.main_store_packing, 0) - COALESCE(c.main_store_current, 0)) / c.main_store_current) * 100
            ELSE 0
        END, 2
    ) as percentage_difference

FROM current_method c
FULL OUTER JOIN packing_list_method p ON c.tanggal = p.tanggal

ORDER BY tanggal;
