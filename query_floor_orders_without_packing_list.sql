-- Query untuk menampilkan floor orders yang tidak ada packing list-nya
-- Menampilkan order_number, fo_mode, created_at dari food_floor_orders

SELECT 
    ffo.id as floor_order_id,
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at as floor_order_created_at,
    do.id as delivery_order_id,
    do.floor_order_id as delivery_floor_order_id,
    do.packing_list_id,
    gr.id as good_receive_id,
    gr.receive_date,
    o.id_outlet,
    o.nama_outlet as customer,
    COUNT(i.id) as gr_items_count

FROM food_floor_orders as ffo
    JOIN delivery_orders as do ON do.floor_order_id = ffo.id
    JOIN outlet_food_good_receives as gr ON gr.delivery_order_id = do.id
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL
    AND (do.packing_list_id IS NULL OR do.packing_list_id = 0)

GROUP BY 
    ffo.id, ffo.order_number, ffo.fo_mode, ffo.created_at,
    do.id, do.floor_order_id, do.packing_list_id,
    gr.id, gr.receive_date, o.id_outlet, o.nama_outlet

ORDER BY gr.receive_date, ffo.order_number;
