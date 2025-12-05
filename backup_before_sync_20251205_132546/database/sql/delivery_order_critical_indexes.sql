-- =====================================================
-- DELIVERY ORDER CRITICAL PERFORMANCE INDEXES
-- =====================================================
-- Script ini WAJIB dijalankan untuk mengatasi masalah performa delivery order
-- Jalankan satu per satu jika ada error

-- 1. Index untuk delivery_orders (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_delivery_orders_packing_list ON delivery_orders(packing_list_id);
CREATE INDEX IF NOT EXISTS idx_delivery_orders_ro_supplier_gr ON delivery_orders(ro_supplier_gr_id);
CREATE INDEX IF NOT EXISTS idx_delivery_orders_created_at ON delivery_orders(created_at);
CREATE INDEX IF NOT EXISTS idx_delivery_orders_created_by ON delivery_orders(created_by);
CREATE INDEX IF NOT EXISTS idx_delivery_orders_number ON delivery_orders(number);

-- 2. Index untuk delivery_order_items (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_delivery_order_items_delivery_order ON delivery_order_items(delivery_order_id);
CREATE INDEX IF NOT EXISTS idx_delivery_order_items_item ON delivery_order_items(item_id);

-- 3. Index untuk food_packing_lists (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_floor_order ON food_packing_lists(food_floor_order_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_warehouse_division ON food_packing_lists(warehouse_division_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_status ON food_packing_lists(status);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_created_at ON food_packing_lists(created_at);

-- 4. Index untuk food_floor_orders (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_outlet ON food_floor_orders(id_outlet);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_warehouse_outlet ON food_floor_orders(warehouse_outlet_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_status ON food_floor_orders(status);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_tanggal ON food_floor_orders(tanggal);

-- 5. Index untuk food_good_receives (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_food_good_receives_po_id ON food_good_receives(po_id);
CREATE INDEX IF NOT EXISTS idx_food_good_receives_supplier ON food_good_receives(supplier_id);
CREATE INDEX IF NOT EXISTS idx_food_good_receives_receive_date ON food_good_receives(receive_date);

-- 6. Index untuk purchase_order_foods (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_purchase_order_foods_source ON purchase_order_foods(source_id);
CREATE INDEX IF NOT EXISTS idx_purchase_order_foods_source_type ON purchase_order_foods(source_type);

-- 7. Index untuk warehouse_division (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouse_division_warehouse ON warehouse_division(warehouse_id);

-- 8. Index untuk warehouses (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouses_name ON warehouses(name);

-- 9. Index untuk warehouse_outlets (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_warehouse_outlets_name ON warehouse_outlets(name);

-- 10. Index untuk tbl_data_outlet (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_id_outlet ON tbl_data_outlet(id_outlet);
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_nama_outlet ON tbl_data_outlet(nama_outlet);

-- 11. Index untuk users (MOST CRITICAL)
CREATE INDEX IF NOT EXISTS idx_users_id ON users(id);
CREATE INDEX IF NOT EXISTS idx_users_nama_lengkap ON users(nama_lengkap);

-- 12. COMPOSITE INDEXES untuk performa maksimal
CREATE INDEX IF NOT EXISTS idx_delivery_orders_complex ON delivery_orders(packing_list_id, created_at, created_by);
CREATE INDEX IF NOT EXISTS idx_food_packing_lists_complex ON food_packing_lists(food_floor_order_id, warehouse_division_id, status);
CREATE INDEX IF NOT EXISTS idx_food_floor_orders_complex ON food_floor_orders(id_outlet, warehouse_outlet_id, status, tanggal);

-- 13. Index untuk food_inventory_stocks (CRITICAL untuk stock operations)
CREATE INDEX IF NOT EXISTS idx_food_inventory_stocks_warehouse_item ON food_inventory_stocks(warehouse_id, inventory_item_id);
CREATE INDEX IF NOT EXISTS idx_food_inventory_stocks_inventory_item ON food_inventory_stocks(inventory_item_id);

-- 14. Index untuk food_inventory_items (CRITICAL untuk stock operations)
CREATE INDEX IF NOT EXISTS idx_food_inventory_items_item ON food_inventory_items(item_id);

-- 15. Index untuk items (CRITICAL untuk item lookups)
CREATE INDEX IF NOT EXISTS idx_items_warehouse_division ON items(warehouse_division_id);
CREATE INDEX IF NOT EXISTS idx_items_category ON items(category_id);
CREATE INDEX IF NOT EXISTS idx_items_small_unit ON items(small_unit_id);
CREATE INDEX IF NOT EXISTS idx_items_medium_unit ON items(medium_unit_id);
CREATE INDEX IF NOT EXISTS idx_items_large_unit ON items(large_unit_id);

-- 16. Index untuk units (CRITICAL untuk unit conversions)
CREATE INDEX IF NOT EXISTS idx_units_name ON units(name);
CREATE INDEX IF NOT EXISTS idx_units_id ON units(id);

-- 17. Index untuk food_floor_order_items (CRITICAL untuk item lookups)
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_floor_order ON food_floor_order_items(floor_order_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_item ON food_floor_order_items(item_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_warehouse_division ON food_floor_order_items(warehouse_division_id);

-- 18. Index untuk food_packing_list_items (CRITICAL untuk item lookups)
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_packing_list ON food_packing_list_items(packing_list_id);
CREATE INDEX IF NOT EXISTS idx_food_packing_list_items_floor_order_item ON food_packing_list_items(food_floor_order_item_id);

-- 19. Index untuk food_good_receive_items (CRITICAL untuk RO Supplier GR)
CREATE INDEX IF NOT EXISTS idx_food_good_receive_items_gr_id ON food_good_receive_items(good_receive_id);
CREATE INDEX IF NOT EXISTS idx_food_good_receive_items_item ON food_good_receive_items(item_id);
CREATE INDEX IF NOT EXISTS idx_food_good_receive_items_unit ON food_good_receive_items(unit_id);

-- 20. Index untuk activity_logs (CRITICAL untuk logging)
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_module ON activity_logs(user_id, module);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);

-- 21. Index untuk item_barcodes (CRITICAL untuk barcode lookups)
CREATE INDEX IF NOT EXISTS idx_item_barcodes_item_id ON item_barcodes(item_id);
CREATE INDEX IF NOT EXISTS idx_item_barcodes_barcode ON item_barcodes(barcode);

-- 22. Index untuk categories dan sub_categories
CREATE INDEX IF NOT EXISTS idx_categories_id ON categories(id);
CREATE INDEX IF NOT EXISTS idx_sub_categories_id ON sub_categories(id);
CREATE INDEX IF NOT EXISTS idx_sub_categories_category ON sub_categories(category_id);

-- 23. Index untuk suppliers
CREATE INDEX IF NOT EXISTS idx_suppliers_id ON suppliers(id);
CREATE INDEX IF NOT EXISTS idx_suppliers_name ON suppliers(name);

-- Verifikasi index yang telah dibuat
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN (
        'delivery_orders', 
        'delivery_order_items', 
        'food_packing_lists', 
        'food_floor_orders',
        'food_good_receives',
        'purchase_order_foods',
        'warehouse_division',
        'warehouses',
        'warehouse_outlets',
        'tbl_data_outlet',
        'users',
        'food_inventory_stocks',
        'food_inventory_items',
        'items',
        'units',
        'food_floor_order_items',
        'food_packing_list_items',
        'food_good_receive_items',
        'activity_logs',
        'item_barcodes',
        'categories',
        'sub_categories',
        'suppliers'
    )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
