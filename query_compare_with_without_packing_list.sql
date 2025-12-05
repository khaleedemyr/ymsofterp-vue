-- Query 1: Current Query (Tanpa Packing List) - Langsung dari GR ke Floor Order
-- Query yang saat ini digunakan di Rekap FJ

SELECT 
    'CURRENT_QUERY' as query_type,
    'outlet_food_good_receives' as source_table,
    DATE(gr.receive_date) as tanggal,
    o.id_outlet,
    o.nama_outlet as customer,
    o.is_outlet,
    it.name as item_name,
    u.name as unit_name,
    i.received_qty as qty,
    fo.price as floor_order_price,
    (i.received_qty * fo.price) as subtotal,
    w.name as warehouse_name,
    sc.name as sub_category_name,
    do.id as delivery_order_id,
    do.floor_order_id,
    fo.id as floor_order_item_id

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

-- Query 2: Dengan Packing List - Melalui food_packing_lists
-- Query alternatif yang menggunakan relasi packing list

SELECT 
    'WITH_PACKING_LIST' as query_type,
    'outlet_food_good_receives' as source_table,
    DATE(gr.receive_date) as tanggal,
    o.id_outlet,
    o.nama_outlet as customer,
    o.is_outlet,
    it.name as item_name,
    u.name as unit_name,
    i.received_qty as qty,
    fo.price as floor_order_price,
    (i.received_qty * fo.price) as subtotal,
    w.name as warehouse_name,
    sc.name as sub_category_name,
    do.id as delivery_order_id,
    do.floor_order_id,
    fo.id as floor_order_item_id

FROM outlet_food_good_receives as gr
    JOIN outlet_food_good_receive_items as i ON gr.id = i.outlet_food_good_receive_id
    JOIN items as it ON i.item_id = it.id
    JOIN sub_categories as sc ON it.sub_category_id = sc.id
    JOIN units as u ON i.unit_id = u.id
    JOIN delivery_orders as do ON gr.delivery_order_id = do.id
    JOIN food_packing_lists as pl ON do.packing_list_id = pl.id  -- JOIN KE PACKING LIST
    JOIN food_floor_order_items as fo ON (
        i.item_id = fo.item_id AND 
        fo.floor_order_id = pl.food_floor_order_id  -- AMBIL DARI PACKING LIST
    )
    LEFT JOIN warehouse_division as wd ON it.warehouse_division_id = wd.id
    LEFT JOIN warehouses as w ON wd.warehouse_id = w.id
    JOIN tbl_data_outlet as o ON gr.outlet_id = o.id_outlet

WHERE 
    o.id_outlet = 23
    AND YEAR(gr.receive_date) = 2025
    AND MONTH(gr.receive_date) = 9
    AND gr.deleted_at IS NULL

ORDER BY query_type, tanggal, item_name;
