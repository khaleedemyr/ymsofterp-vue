-- SQL Script untuk membuat index yang diperlukan untuk Stock Card Report
-- Jalankan script ini di database production untuk meningkatkan performa

-- 1. Index untuk food_inventory_cards table
-- Index untuk kolom date (sering digunakan untuk filter tanggal)
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_date 
ON food_inventory_cards(date);

-- Index untuk kombinasi inventory_item_id dan warehouse_id
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_item_warehouse 
ON food_inventory_cards(inventory_item_id, warehouse_id);

-- Index untuk reference_type dan reference_id
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_reference 
ON food_inventory_cards(reference_type, reference_id);

-- Index untuk kombinasi date, inventory_item_id, warehouse_id
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_date_item_warehouse 
ON food_inventory_cards(date, inventory_item_id, warehouse_id);

-- 2. Index untuk food_inventory_items table
-- Index untuk item_id
CREATE INDEX IF NOT EXISTS idx_food_inventory_items_item_id 
ON food_inventory_items(item_id);

-- 3. Index untuk items table
-- Index untuk category_id (jika sering digunakan untuk filter)
CREATE INDEX IF NOT EXISTS idx_items_category_id 
ON items(category_id);

-- Index untuk small_unit_id, medium_unit_id, large_unit_id
CREATE INDEX IF NOT EXISTS idx_items_small_unit_id 
ON items(small_unit_id);

CREATE INDEX IF NOT EXISTS idx_items_medium_unit_id 
ON items(medium_unit_id);

CREATE INDEX IF NOT EXISTS idx_items_large_unit_id 
ON items(large_unit_id);

-- 4. Index untuk warehouses table
-- Index untuk name (jika sering digunakan untuk sorting)
CREATE INDEX IF NOT EXISTS idx_warehouses_name 
ON warehouses(name);

-- 5. Index untuk units table
-- Index untuk name (jika sering digunakan untuk sorting)
CREATE INDEX IF NOT EXISTS idx_units_name 
ON units(name);

-- 6. Index untuk food_good_receives table
-- Index untuk gr_number
CREATE INDEX IF NOT EXISTS idx_food_good_receives_gr_number 
ON food_good_receives(gr_number);

-- 7. Index untuk warehouse_transfers table
-- Index untuk transfer_number
CREATE INDEX IF NOT EXISTS idx_warehouse_transfers_transfer_number 
ON warehouse_transfers(transfer_number);

-- 8. Index untuk delivery_orders table
-- Index untuk number
CREATE INDEX IF NOT EXISTS idx_delivery_orders_number 
ON delivery_orders(number);

-- 9. Index untuk food_packing_lists table
-- Index untuk food_floor_order_id
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_floor_order 
ON food_packing_lists(food_floor_order_id);

-- 10. Index untuk food_floor_orders table
-- Index untuk id_outlet dan warehouse_outlet_id
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_outlet 
ON food_floor_orders(id_outlet, warehouse_outlet_id);

-- 11. Index untuk tbl_data_outlet table
-- Index untuk id_outlet
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_id_outlet 
ON tbl_data_outlet(id_outlet);

-- 12. Index untuk warehouse_outlets table
-- Index untuk name
CREATE INDEX IF NOT EXISTS idx_warehouse_outlets_name 
ON warehouse_outlets(name);

-- 13. Composite index untuk query yang kompleks
-- Index untuk kombinasi yang sering digunakan dalam JOIN
CREATE INDEX IF NOT EXISTS idx_food_inventory_cards_complex 
ON food_inventory_cards(inventory_item_id, warehouse_id, date, reference_type);

-- 14. Index untuk categories table
-- Index untuk show_pos (jika sering digunakan untuk filter)
CREATE INDEX IF NOT EXISTS idx_categories_show_pos 
ON categories(show_pos);

-- 15. Index untuk items dengan category dan show_pos
-- Index untuk kombinasi category_id dan show_pos
CREATE INDEX IF NOT EXISTS idx_items_category_show_pos 
ON items(category_id, id);

-- Query untuk mengecek index yang sudah ada
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    NON_UNIQUE
FROM 
    INFORMATION_SCHEMA.STATISTICS 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
        'food_inventory_cards',
        'food_inventory_items', 
        'items',
        'warehouses',
        'units',
        'food_good_receives',
        'warehouse_transfers',
        'delivery_orders',
        'food_packing_lists',
        'food_floor_orders',
        'tbl_data_outlet',
        'warehouse_outlets',
        'categories'
    )
ORDER BY 
    TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Query untuk mengecek performa query stock card
EXPLAIN SELECT 
    c.id,
    c.date,
    i.id as item_id,
    i.name as item_name,
    w.name as warehouse_name,
    c.in_qty_small,
    c.out_qty_small,
    c.saldo_qty_small
FROM food_inventory_cards c
JOIN food_inventory_items fi ON c.inventory_item_id = fi.id
JOIN items i ON fi.item_id = i.id
JOIN warehouses w ON c.warehouse_id = w.id
WHERE i.id = 1
    AND c.date >= '2024-01-01'
    AND c.date <= '2024-12-31'
ORDER BY c.date
LIMIT 1000;
