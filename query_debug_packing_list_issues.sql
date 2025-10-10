-- Query untuk debugging masalah packing list
-- Mengecek mengapa data berbeda antara current method dan packing list method

-- 1. Cek delivery orders yang tidak punya packing_list_id
SELECT 
    'Missing Packing List ID' as issue_type,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    do.packing_list_id,
    gr.id as good_receive_id,
    gr.receive_date,
    COUNT(i.id) as gr_items_count

FROM delivery_orders as do
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL
    AND (do.packing_list_id IS NULL OR do.packing_list_id = 0)

GROUP BY do.id, do.floor_order_id, do.packing_list_id, gr.id, gr.receive_date

ORDER BY gr.receive_date;

-- 2. Cek packing lists yang tidak punya food_floor_order_id
SELECT 
    'Missing Floor Order ID in Packing List' as issue_type,
    pl.id as packing_list_id,
    pl.packing_number,
    pl.food_floor_order_id as packing_floor_order_id,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    gr.id as good_receive_id,
    gr.receive_date,
    COUNT(i.id) as gr_items_count

FROM food_packing_lists as pl
    JOIN delivery_orders as do ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL
    AND (pl.food_floor_order_id IS NULL OR pl.food_floor_order_id = 0)

GROUP BY pl.id, pl.packing_number, pl.food_floor_order_id, do.id, do.floor_order_id, gr.id, gr.receive_date

ORDER BY gr.receive_date;

-- 3. Cek perbedaan floor_order_id antara delivery_orders dan packing_lists
SELECT 
    'Different Floor Order ID' as issue_type,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    pl.id as packing_list_id,
    pl.food_floor_order_id as packing_floor_order_id,
    CASE 
        WHEN do.floor_order_id = pl.food_floor_order_id THEN 'SAME'
        WHEN do.floor_order_id != pl.food_floor_order_id THEN 'DIFFERENT'
        ELSE 'MISSING'
    END as comparison,
    gr.id as good_receive_id,
    gr.receive_date,
    COUNT(i.id) as gr_items_count

FROM delivery_orders as do
    JOIN food_packing_lists as pl ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

GROUP BY do.id, do.floor_order_id, pl.id, pl.food_floor_order_id, gr.id, gr.receive_date

ORDER BY gr.receive_date;

-- 4. Summary per tanggal
SELECT 
    'Summary by Date' as summary_type,
    DATE(gr.receive_date) as tanggal,
    COUNT(DISTINCT gr.id) as total_gr,
    COUNT(DISTINCT CASE WHEN do.packing_list_id IS NOT NULL THEN gr.id END) as gr_with_packing_list,
    COUNT(DISTINCT CASE WHEN do.packing_list_id IS NULL THEN gr.id END) as gr_without_packing_list,
    COUNT(DISTINCT i.id) as total_gr_items,
    COUNT(DISTINCT CASE WHEN do.packing_list_id IS NOT NULL THEN i.id END) as gr_items_with_packing_list,
    COUNT(DISTINCT CASE WHEN do.packing_list_id IS NULL THEN i.id END) as gr_items_without_packing_list

FROM outlet_food_good_receives as gr
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN delivery_orders as do ON gr.delivery_order_id = do.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

GROUP BY DATE(gr.receive_date)

ORDER BY tanggal;
