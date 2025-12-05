-- Query untuk mengecek data packing list yang tersedia
-- Debug query untuk melihat relasi packing list

-- 1. Cek data packing list untuk outlet 23 di September 2025
SELECT 
    'Packing List Data' as check_type,
    pl.id as packing_list_id,
    pl.packing_number,
    pl.food_floor_order_id,
    pl.warehouse_division_id,
    pl.status,
    pl.created_at,
    do.id as delivery_order_id,
    gr.id as good_receive_id,
    gr.receive_date

FROM food_packing_lists as pl
    JOIN delivery_orders as do ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

ORDER BY gr.receive_date, pl.id;

-- 2. Cek perbedaan floor_order_id antara delivery_orders dan packing_lists
SELECT 
    'Floor Order ID Comparison' as check_type,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    pl.id as packing_list_id,
    pl.food_floor_order_id as packing_floor_order_id,
    CASE 
        WHEN do.floor_order_id = pl.food_floor_order_id THEN 'SAME'
        WHEN do.floor_order_id != pl.food_floor_order_id THEN 'DIFFERENT'
        ELSE 'MISSING'
    END as comparison,
    gr.receive_date

FROM delivery_orders as do
    JOIN food_packing_lists as pl ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

ORDER BY gr.receive_date, do.id;

-- 3. Cek data packing list items
SELECT 
    'Packing List Items' as check_type,
    pl.id as packing_list_id,
    pl.packing_number,
    pli.id as packing_list_item_id,
    pli.food_floor_order_item_id,
    pli.qty as packing_qty,
    pli.unit as packing_unit,
    pli.source,
    fo.price as floor_order_price,
    (pli.qty * fo.price) as packing_subtotal,
    it.name as item_name,
    gr.receive_date

FROM food_packing_lists as pl
    JOIN food_packing_list_items as pli ON pl.id = pli.packing_list_id
    JOIN food_floor_order_items as fo ON pli.food_floor_order_item_id = fo.id
    JOIN items as it ON fo.item_id = it.id
    JOIN delivery_orders as do ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

ORDER BY gr.receive_date, pl.id, pli.id;

-- 4. Cek delivery orders yang tidak punya packing list
SELECT 
    'Missing Packing List' as check_type,
    do.id as delivery_order_id,
    do.floor_order_id,
    do.packing_list_id,
    gr.id as good_receive_id,
    gr.receive_date

FROM delivery_orders as do
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL
    AND do.packing_list_id IS NULL

ORDER BY gr.receive_date, do.id;
