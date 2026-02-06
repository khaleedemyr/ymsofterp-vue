-- Add indexes untuk optimize query outlet inventory report
-- Yang menyebabkan "Creating sort index" lama

-- 1. Index untuk category name (untuk ORDER BY c.name)
CREATE INDEX idx_categories_name ON categories(name) 
WHERE deleted_at IS NULL;

-- Jika MySQL tidak support partial index, gunakan ini:
-- CREATE INDEX idx_categories_name ON categories(name);

-- 2. Index untuk item name dan category_id (untuk ORDER BY dan JOIN)
CREATE INDEX idx_items_category_name ON items(category_id, name) 
WHERE deleted_at IS NULL;

-- Jika MySQL tidak support partial index, gunakan ini:
-- CREATE INDEX idx_items_category_name ON items(category_id, name);

-- 3. Index untuk outlet_food_inventory_stocks (filter utama)
CREATE INDEX idx_outlet_stocks_outlet_warehouse 
ON outlet_food_inventory_stocks(id_outlet, warehouse_outlet_id, inventory_item_id);

-- 4. Index untuk outlet_food_inventory_items
CREATE INDEX idx_outlet_inventory_items_item_id 
ON outlet_food_inventory_items(item_id);

-- 5. Index untuk tbl_data_outlet status (untuk filter outlet aktif)
CREATE INDEX idx_data_outlet_status 
ON tbl_data_outlet(status, id_outlet);

-- 6. Index untuk warehouse_outlets status
CREATE INDEX idx_warehouse_outlets_status_outlet 
ON warehouse_outlets(status, outlet_id, id);

-- Verify indexes
SHOW INDEX FROM categories WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM items WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM outlet_food_inventory_stocks WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM outlet_food_inventory_items WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM tbl_data_outlet WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM warehouse_outlets WHERE Key_name LIKE 'idx_%';

-- Check query execution plan after adding indexes
EXPLAIN 
SELECT 
    i.id as item_id,
    i.name as item_name,
    c.id as category_id,
    c.name as category_name,
    o.id_outlet as outlet_id,
    o.nama_outlet as outlet_name
FROM outlet_food_inventory_stocks as s
JOIN outlet_food_inventory_items as fi ON s.inventory_item_id = fi.id
JOIN items as i ON fi.item_id = i.id
JOIN tbl_data_outlet as o ON s.id_outlet = o.id_outlet
LEFT JOIN categories as c ON i.category_id = c.id
WHERE s.id_outlet = 1
ORDER BY c.name, i.name
LIMIT 100;
