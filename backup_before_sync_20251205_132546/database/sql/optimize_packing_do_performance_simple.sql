-- =====================================================
-- PACKING LIST & DELIVERY ORDER PERFORMANCE OPTIMIZATION (SIMPLE VERSION)
-- =====================================================
-- Script ini kompatibel dengan semua versi MySQL
-- Jalankan satu per satu jika ada error

-- 1. Index untuk food_packing_lists (CRITICAL)
CREATE INDEX idx_food_packing_lists_floor_order_status ON food_packing_lists(food_floor_order_id, status);
CREATE INDEX idx_food_packing_lists_warehouse_division ON food_packing_lists(warehouse_division_id);
CREATE INDEX idx_food_packing_lists_created_at ON food_packing_lists(created_at);

-- 2. Index untuk food_packing_list_items (CRITICAL)
CREATE INDEX idx_food_packing_list_items_packing_list ON food_packing_list_items(packing_list_id);
CREATE INDEX idx_food_packing_list_items_floor_order_item ON food_packing_list_items(food_floor_order_item_id);

-- 3. Index untuk delivery_orders (CRITICAL)
CREATE INDEX idx_delivery_orders_packing_list ON delivery_orders(packing_list_id);
CREATE INDEX idx_delivery_orders_ro_supplier_gr ON delivery_orders(ro_supplier_gr_id);
CREATE INDEX idx_delivery_orders_created_at ON delivery_orders(created_at);
CREATE INDEX idx_delivery_orders_created_by ON delivery_orders(created_by);

-- 4. Index untuk delivery_order_items (CRITICAL)
CREATE INDEX idx_delivery_order_items_delivery_order ON delivery_order_items(delivery_order_id);
CREATE INDEX idx_delivery_order_items_item ON delivery_order_items(item_id);

-- 5. Index untuk food_floor_orders (CRITICAL)
CREATE INDEX idx_food_floor_orders_status_tanggal ON food_floor_orders(status, tanggal);
CREATE INDEX idx_food_floor_orders_outlet_warehouse ON food_floor_orders(id_outlet, warehouse_outlet_id);
CREATE INDEX idx_food_floor_orders_arrival_date ON food_floor_orders(arrival_date);

-- 6. Index untuk food_floor_order_items (CRITICAL)
CREATE INDEX idx_food_floor_order_items_floor_order ON food_floor_order_items(floor_order_id);
CREATE INDEX idx_food_floor_order_items_item ON food_floor_order_items(item_id);

-- 7. Index untuk food_inventory_stocks (CRITICAL untuk stock checking)
CREATE INDEX idx_food_inventory_stocks_warehouse_item ON food_inventory_stocks(warehouse_id, inventory_item_id);

-- 8. Index untuk food_inventory_items (CRITICAL)
CREATE INDEX idx_food_inventory_items_item ON food_inventory_items(item_id);

-- 9. Index untuk items (CRITICAL)
CREATE INDEX idx_items_warehouse_division ON items(warehouse_division_id);
CREATE INDEX idx_items_category ON items(category_id);

-- 10. Composite indexes untuk query yang kompleks
CREATE INDEX idx_food_packing_lists_complex ON food_packing_lists(food_floor_order_id, warehouse_division_id, status);
CREATE INDEX idx_delivery_orders_complex ON delivery_orders(packing_list_id, created_at, created_by);
CREATE INDEX idx_food_floor_orders_complex ON food_floor_orders(status, tanggal, id_outlet, warehouse_outlet_id);

-- 11. Index untuk activity_logs (untuk logging performance)
CREATE INDEX idx_activity_logs_user_module ON activity_logs(user_id, module);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- 12. Index untuk warehouse_division
CREATE INDEX idx_warehouse_division_warehouse ON warehouse_division(warehouse_id);

-- 13. Index untuk warehouse_outlets
CREATE INDEX idx_warehouse_outlets_name ON warehouse_outlets(name);

-- 14. Index untuk tbl_data_outlet
CREATE INDEX idx_tbl_data_outlet_status ON tbl_data_outlet(status);

-- 15. Index untuk users
CREATE INDEX idx_users_status ON users(status);

-- Query untuk mengecek index yang sudah dibuat
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
        'food_packing_lists',
        'food_packing_list_items', 
        'delivery_orders',
        'delivery_order_items',
        'food_floor_orders',
        'food_floor_order_items',
        'food_inventory_stocks',
        'food_inventory_items',
        'items',
        'activity_logs'
    )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
