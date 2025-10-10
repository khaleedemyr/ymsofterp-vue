-- Query sederhana untuk floor orders yang tidak ada packing list
-- Fokus pada order_number, fo_mode, created_at

SELECT DISTINCT
    ffo.order_number,
    ffo.fo_mode,
    ffo.created_at,
    COUNT(DISTINCT gr.id) as good_receive_count,
    COUNT(DISTINCT i.id) as total_items

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

GROUP BY ffo.order_number, ffo.fo_mode, ffo.created_at

ORDER BY ffo.created_at DESC, ffo.order_number;
