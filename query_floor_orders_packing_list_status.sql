-- Query untuk melihat status packing list dari semua floor orders
-- Menampilkan order_number, fo_mode, created_at dan status packing list

SELECT 
    ffo.id as floor_order_id,
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at as floor_order_created_at,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    do.packing_list_id,
    pl.id as packing_list_id_check,
    pl.packing_number,
    pl.food_floor_order_id as packing_floor_order_id,
    gr.id as good_receive_id,
    gr.receive_date,
    o.id_outlet,
    o.nama_outlet as customer,
    COUNT(i.id) as gr_items_count,
    CASE 
        WHEN do.packing_list_id IS NULL OR do.packing_list_id = 0 THEN 'NO_PACKING_LIST'
        WHEN pl.id IS NULL THEN 'PACKING_LIST_NOT_FOUND'
        WHEN pl.food_floor_order_id IS NULL OR pl.food_floor_order_id = 0 THEN 'PACKING_LIST_NO_FLOOR_ORDER'
        WHEN pl.food_floor_order_id != ffo.id THEN 'FLOOR_ORDER_MISMATCH'
        ELSE 'HAS_PACKING_LIST'
    END as packing_list_status

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    LEFT JOIN food_packing_lists as pl ON do.packing_list_id = pl.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

GROUP BY 
    ffo.id, ffo.order_number, ffo.fo_mode, ffo.created_at,
    do.id, do.floor_order_id, do.packing_list_id,
    pl.id, pl.packing_number, pl.food_floor_order_id,
    gr.id, gr.receive_date, o.id_outlet, o.nama_outlet

ORDER BY 
    packing_list_status, gr.receive_date, ffo.order_number;
