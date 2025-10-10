-- =====================================================
-- PACKING LIST & DELIVERY ORDER PERFORMANCE OPTIMIZATION
-- =====================================================
-- Jalankan script ini untuk mengoptimasi performa menu packing list dan DO

-- 1. Index untuk food_packing_lists (CRITICAL)
-- Cek dan buat index jika belum ada
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_lists' 
     AND INDEX_NAME = 'idx_food_packing_lists_floor_order_status') = 0,
    'CREATE INDEX idx_food_packing_lists_floor_order_status ON food_packing_lists(food_floor_order_id, status)',
    'SELECT "Index idx_food_packing_lists_floor_order_status already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_lists' 
     AND INDEX_NAME = 'idx_food_packing_lists_warehouse_division') = 0,
    'CREATE INDEX idx_food_packing_lists_warehouse_division ON food_packing_lists(warehouse_division_id)',
    'SELECT "Index idx_food_packing_lists_warehouse_division already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_lists' 
     AND INDEX_NAME = 'idx_food_packing_lists_created_at') = 0,
    'CREATE INDEX idx_food_packing_lists_created_at ON food_packing_lists(created_at)',
    'SELECT "Index idx_food_packing_lists_created_at already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Index untuk food_packing_list_items (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_list_items' 
     AND INDEX_NAME = 'idx_food_packing_list_items_packing_list') = 0,
    'CREATE INDEX idx_food_packing_list_items_packing_list ON food_packing_list_items(packing_list_id)',
    'SELECT "Index idx_food_packing_list_items_packing_list already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_list_items' 
     AND INDEX_NAME = 'idx_food_packing_list_items_floor_order_item') = 0,
    'CREATE INDEX idx_food_packing_list_items_floor_order_item ON food_packing_list_items(food_floor_order_item_id)',
    'SELECT "Index idx_food_packing_list_items_floor_order_item already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Index untuk delivery_orders (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_orders' 
     AND INDEX_NAME = 'idx_delivery_orders_packing_list') = 0,
    'CREATE INDEX idx_delivery_orders_packing_list ON delivery_orders(packing_list_id)',
    'SELECT "Index idx_delivery_orders_packing_list already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_orders' 
     AND INDEX_NAME = 'idx_delivery_orders_ro_supplier_gr') = 0,
    'CREATE INDEX idx_delivery_orders_ro_supplier_gr ON delivery_orders(ro_supplier_gr_id)',
    'SELECT "Index idx_delivery_orders_ro_supplier_gr already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_orders' 
     AND INDEX_NAME = 'idx_delivery_orders_created_at') = 0,
    'CREATE INDEX idx_delivery_orders_created_at ON delivery_orders(created_at)',
    'SELECT "Index idx_delivery_orders_created_at already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_orders' 
     AND INDEX_NAME = 'idx_delivery_orders_created_by') = 0,
    'CREATE INDEX idx_delivery_orders_created_by ON delivery_orders(created_by)',
    'SELECT "Index idx_delivery_orders_created_by already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Index untuk delivery_order_items (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_order_items' 
     AND INDEX_NAME = 'idx_delivery_order_items_delivery_order') = 0,
    'CREATE INDEX idx_delivery_order_items_delivery_order ON delivery_order_items(delivery_order_id)',
    'SELECT "Index idx_delivery_order_items_delivery_order already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_order_items' 
     AND INDEX_NAME = 'idx_delivery_order_items_item') = 0,
    'CREATE INDEX idx_delivery_order_items_item ON delivery_order_items(item_id)',
    'SELECT "Index idx_delivery_order_items_item already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Index untuk food_floor_orders (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_orders' 
     AND INDEX_NAME = 'idx_food_floor_orders_status_tanggal') = 0,
    'CREATE INDEX idx_food_floor_orders_status_tanggal ON food_floor_orders(status, tanggal)',
    'SELECT "Index idx_food_floor_orders_status_tanggal already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_orders' 
     AND INDEX_NAME = 'idx_food_floor_orders_outlet_warehouse') = 0,
    'CREATE INDEX idx_food_floor_orders_outlet_warehouse ON food_floor_orders(id_outlet, warehouse_outlet_id)',
    'SELECT "Index idx_food_floor_orders_outlet_warehouse already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_orders' 
     AND INDEX_NAME = 'idx_food_floor_orders_arrival_date') = 0,
    'CREATE INDEX idx_food_floor_orders_arrival_date ON food_floor_orders(arrival_date)',
    'SELECT "Index idx_food_floor_orders_arrival_date already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Index untuk food_floor_order_items (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_order_items' 
     AND INDEX_NAME = 'idx_food_floor_order_items_floor_order') = 0,
    'CREATE INDEX idx_food_floor_order_items_floor_order ON food_floor_order_items(floor_order_id)',
    'SELECT "Index idx_food_floor_order_items_floor_order already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_order_items' 
     AND INDEX_NAME = 'idx_food_floor_order_items_item') = 0,
    'CREATE INDEX idx_food_floor_order_items_item ON food_floor_order_items(item_id)',
    'SELECT "Index idx_food_floor_order_items_item already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Index untuk food_inventory_stocks (CRITICAL untuk stock checking)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_inventory_stocks' 
     AND INDEX_NAME = 'idx_food_inventory_stocks_warehouse_item') = 0,
    'CREATE INDEX idx_food_inventory_stocks_warehouse_item ON food_inventory_stocks(warehouse_id, inventory_item_id)',
    'SELECT "Index idx_food_inventory_stocks_warehouse_item already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Index untuk food_inventory_items (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_inventory_items' 
     AND INDEX_NAME = 'idx_food_inventory_items_item') = 0,
    'CREATE INDEX idx_food_inventory_items_item ON food_inventory_items(item_id)',
    'SELECT "Index idx_food_inventory_items_item already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 9. Index untuk items (CRITICAL)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'items' 
     AND INDEX_NAME = 'idx_items_warehouse_division') = 0,
    'CREATE INDEX idx_items_warehouse_division ON items(warehouse_division_id)',
    'SELECT "Index idx_items_warehouse_division already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'items' 
     AND INDEX_NAME = 'idx_items_category') = 0,
    'CREATE INDEX idx_items_category ON items(category_id)',
    'SELECT "Index idx_items_category already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 10. Composite indexes untuk query yang kompleks
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_packing_lists' 
     AND INDEX_NAME = 'idx_food_packing_lists_complex') = 0,
    'CREATE INDEX idx_food_packing_lists_complex ON food_packing_lists(food_floor_order_id, warehouse_division_id, status)',
    'SELECT "Index idx_food_packing_lists_complex already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'delivery_orders' 
     AND INDEX_NAME = 'idx_delivery_orders_complex') = 0,
    'CREATE INDEX idx_delivery_orders_complex ON delivery_orders(packing_list_id, created_at, created_by)',
    'SELECT "Index idx_delivery_orders_complex already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'food_floor_orders' 
     AND INDEX_NAME = 'idx_food_floor_orders_complex') = 0,
    'CREATE INDEX idx_food_floor_orders_complex ON food_floor_orders(status, tanggal, id_outlet, warehouse_outlet_id)',
    'SELECT "Index idx_food_floor_orders_complex already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 11. Index untuk activity_logs (untuk logging performance)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'activity_logs' 
     AND INDEX_NAME = 'idx_activity_logs_user_module') = 0,
    'CREATE INDEX idx_activity_logs_user_module ON activity_logs(user_id, module)',
    'SELECT "Index idx_activity_logs_user_module already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'activity_logs' 
     AND INDEX_NAME = 'idx_activity_logs_created_at') = 0,
    'CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at)',
    'SELECT "Index idx_activity_logs_created_at already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 12. Index untuk warehouse_division
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'warehouse_division' 
     AND INDEX_NAME = 'idx_warehouse_division_warehouse') = 0,
    'CREATE INDEX idx_warehouse_division_warehouse ON warehouse_division(warehouse_id)',
    'SELECT "Index idx_warehouse_division_warehouse already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 13. Index untuk warehouse_outlets
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'warehouse_outlets' 
     AND INDEX_NAME = 'idx_warehouse_outlets_name') = 0,
    'CREATE INDEX idx_warehouse_outlets_name ON warehouse_outlets(name)',
    'SELECT "Index idx_warehouse_outlets_name already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 14. Index untuk tbl_data_outlet
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'tbl_data_outlet' 
     AND INDEX_NAME = 'idx_tbl_data_outlet_status') = 0,
    'CREATE INDEX idx_tbl_data_outlet_status ON tbl_data_outlet(status)',
    'SELECT "Index idx_tbl_data_outlet_status already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 15. Index untuk users
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'users' 
     AND INDEX_NAME = 'idx_users_status') = 0,
    'CREATE INDEX idx_users_status ON users(status)',
    'SELECT "Index idx_users_status already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
