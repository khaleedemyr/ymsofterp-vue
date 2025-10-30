-- =====================================================
-- PACKING LIST CRITICAL PERFORMANCE INDEXES
-- =====================================================
-- Script ini WAJIB dijalankan untuk mengatasi masalah performa packing list
-- Jalankan satu per satu jika ada error

-- 1. Index untuk food_packing_lists (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_floor_order ON food_packing_lists(food_floor_order_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_warehouse_division ON food_packing_lists(warehouse_division_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_status ON food_packing_lists(status);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_created_at ON food_packing_lists(created_at);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_created_by ON food_packing_lists(created_by);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_packing_number ON food_packing_lists(packing_number);

-- 2. Index untuk food_packing_list_items (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_packing_list ON food_packing_list_items(packing_list_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_floor_order_item ON food_packing_list_items(food_floor_order_item_id);

-- 3. Index untuk food_floor_orders (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_status ON food_floor_orders(status);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_fo_mode ON food_floor_orders(fo_mode);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_tanggal ON food_floor_orders(tanggal);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_arrival_date ON food_floor_orders(arrival_date);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_outlet ON food_floor_orders(id_outlet);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_warehouse_outlet ON food_floor_orders(warehouse_outlet_id);

-- 4. Index untuk food_floor_order_items (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_floor_order ON food_floor_order_items(floor_order_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_item ON food_floor_order_items(item_id);

-- 5. Index untuk items (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_items_warehouse_division ON items(warehouse_division_id);
CREATE INDEX IF NOT EXISTS idx_items_category ON items(category_id);
CREATE INDEX IF NOT EXISTS idx_items_small_unit ON items(small_unit_id);
CREATE INDEX IF NOT EXISTS idx_items_medium_unit ON items(medium_unit_id);
CREATE INDEX IF NOT EXISTS idx_items_large_unit ON items(large_unit_id);

-- 6. Index untuk warehouse_division (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouse_division_warehouse ON warehouse_division(warehouse_id);
CREATE INDEX IF NOT EXISTS idx_warehouse_division_name ON warehouse_division(name);

-- 7. Index untuk warehouses (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouses_name ON warehouses(name);

-- 8. Index untuk warehouse_outlets (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouse_outlets_name ON warehouse_outlets(name);

-- 9. Index untuk tbl_data_outlet (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_id_outlet ON tbl_data_outlet(id_outlet);
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_nama_outlet ON tbl_data_outlet(nama_outlet);
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_status ON tbl_data_outlet(status);

-- 10. Index untuk users (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_users_id ON users(id);
CREATE INDEX IF NOT EXISTS idx_users_nama_lengkap ON users(nama_lengkap);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

-- 11. Index untuk food_inventory_items (CRITICAL untuk stock operations)
CREATE INDEX IF NOT EXISTS idx_food_inventory_items_item ON food_inventory_items(item_id);

-- 12. Index untuk food_inventory_stocks (CRITICAL untuk stock operations)
CREATE INDEX IF NOT EXISTS idx_food_inventory_stocks_warehouse_item ON food_inventory_stocks(warehouse_id, inventory_item_id);
CREATE INDEX IF NOT EXISTS idx_food_inventory_stocks_inventory_item ON food_inventory_stocks(inventory_item_id);

-- 13. Index untuk units (CRITICAL untuk unit conversions)
CREATE INDEX IF NOT EXISTS idx_units_name ON units(name);
CREATE INDEX IF NOT EXISTS idx_units_id ON units(id);

-- 14. Index untuk categories dan sub_categories
CREATE INDEX IF NOT EXISTS idx_categories_id ON categories(id);
CREATE INDEX IF NOT EXISTS idx_sub_categories_id ON sub_categories(id);
CREATE INDEX IF NOT EXISTS idx_sub_categories_category ON sub_categories(category_id);

-- 15. COMPOSITE INDEXES untuk performa maksimal
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_complex ON food_packing_lists(food_floor_order_id, warehouse_division_id, status);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_complex ON food_floor_orders(status, fo_mode, tanggal, id_outlet);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_complex ON food_floor_order_items(floor_order_id, item_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_complex ON food_packing_list_items(packing_list_id, food_floor_order_item_id);

-- 16. Index untuk activity_logs (CRITICAL untuk logging)
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_module ON activity_logs(user_id, module);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);

-- Verifikasi index yang telah dibuat
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN (
        'food_packing_lists', 
        'food_packing_list_items', 
        'food_floor_orders',
        'food_floor_order_items',
        'items',
        'warehouse_division',
        'warehouses',
        'warehouse_outlets',
        'tbl_data_outlet',
        'users',
        'food_inventory_items',
        'food_inventory_stocks',
        'units',
        'categories',
        'sub_categories',
        'activity_logs'
    )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;


