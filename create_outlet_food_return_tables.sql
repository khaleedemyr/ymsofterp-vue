-- =====================================================
-- CREATE TABLES FOR OUTLET FOOD RETURN FEATURE
-- =====================================================

-- 1. Table untuk menyimpan data return utama
CREATE TABLE IF NOT EXISTS `outlet_food_returns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_number` varchar(255) NOT NULL COMMENT 'Nomor return (format: RET-YYYYMMDD-XXXX)',
  `outlet_food_good_receive_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID dari outlet_food_good_receives',
  `outlet_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID outlet yang melakukan return',
  `warehouse_outlet_id` int(11) NOT NULL COMMENT 'ID warehouse outlet',
  `return_date` date NOT NULL COMMENT 'Tanggal return',
  `notes` text DEFAULT NULL COMMENT 'Catatan return',
  `status` enum('draft','approved','rejected') NOT NULL DEFAULT 'approved' COMMENT 'Status return',
  `created_by` bigint(20) UNSIGNED NOT NULL COMMENT 'User yang membuat return',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User yang mengupdate return',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_food_returns_return_number_unique` (`return_number`),
  KEY `outlet_food_returns_outlet_food_good_receive_id_foreign` (`outlet_food_good_receive_id`),
  KEY `outlet_food_returns_outlet_id_foreign` (`outlet_id`),
  KEY `outlet_food_returns_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `outlet_food_returns_created_by_foreign` (`created_by`),
  KEY `outlet_food_returns_updated_by_foreign` (`updated_by`),
  KEY `outlet_food_returns_return_date_index` (`return_date`),
  KEY `outlet_food_returns_status_index` (`status`),
  KEY `outlet_food_returns_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan data return outlet food';

-- 2. Table untuk menyimpan item-item yang di-return
CREATE TABLE IF NOT EXISTS `outlet_food_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `outlet_food_return_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID dari outlet_food_returns',
  `outlet_food_good_receive_item_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID dari outlet_food_good_receive_items',
  `item_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID item yang di-return',
  `unit_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID unit yang digunakan untuk return',
  `return_qty` decimal(10,2) NOT NULL COMMENT 'Quantity yang di-return',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outlet_food_return_items_outlet_food_return_id_foreign` (`outlet_food_return_id`),
  KEY `outlet_food_return_items_outlet_food_good_receive_item_id_foreign` (`outlet_food_good_receive_item_id`),
  KEY `outlet_food_return_items_item_id_foreign` (`item_id`),
  KEY `outlet_food_return_items_unit_id_foreign` (`unit_id`),
  KEY `outlet_food_return_items_return_qty_index` (`return_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan item-item yang di-return';

-- 3. Table untuk menyimpan kartu stok outlet (jika belum ada)
CREATE TABLE IF NOT EXISTS `outlet_food_inventory_cards` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID dari outlet_food_inventory_items',
  `id_outlet` bigint(20) UNSIGNED NOT NULL COMMENT 'ID outlet',
  `warehouse_outlet_id` int(11) NOT NULL COMMENT 'ID warehouse outlet',
  `date` date NOT NULL COMMENT 'Tanggal transaksi',
  `reference_type` varchar(255) NOT NULL COMMENT 'Tipe referensi (outlet_food_return, retail_food, dll)',
  `reference_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID referensi',
  `in_qty_small` decimal(10,2) DEFAULT 0 COMMENT 'Quantity masuk (small unit)',
  `in_qty_medium` decimal(10,2) DEFAULT 0 COMMENT 'Quantity masuk (medium unit)',
  `in_qty_large` decimal(10,2) DEFAULT 0 COMMENT 'Quantity masuk (large unit)',
  `out_qty_small` decimal(10,2) DEFAULT 0 COMMENT 'Quantity keluar (small unit)',
  `out_qty_medium` decimal(10,2) DEFAULT 0 COMMENT 'Quantity keluar (medium unit)',
  `out_qty_large` decimal(10,2) DEFAULT 0 COMMENT 'Quantity keluar (large unit)',
  `cost_per_small` decimal(10,2) DEFAULT 0 COMMENT 'Cost per small unit',
  `cost_per_medium` decimal(10,2) DEFAULT 0 COMMENT 'Cost per medium unit',
  `cost_per_large` decimal(10,2) DEFAULT 0 COMMENT 'Cost per large unit',
  `value_in` decimal(15,2) DEFAULT 0 COMMENT 'Nilai masuk',
  `value_out` decimal(15,2) DEFAULT 0 COMMENT 'Nilai keluar',
  `saldo_qty_small` decimal(10,2) DEFAULT 0 COMMENT 'Saldo quantity (small unit)',
  `saldo_qty_medium` decimal(10,2) DEFAULT 0 COMMENT 'Saldo quantity (medium unit)',
  `saldo_qty_large` decimal(10,2) DEFAULT 0 COMMENT 'Saldo quantity (large unit)',
  `saldo_value` decimal(15,2) DEFAULT 0 COMMENT 'Saldo nilai',
  `description` text DEFAULT NULL COMMENT 'Deskripsi transaksi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outlet_food_inventory_cards_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `outlet_food_inventory_cards_id_outlet_foreign` (`id_outlet`),
  KEY `outlet_food_inventory_cards_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `outlet_food_inventory_cards_date_index` (`date`),
  KEY `outlet_food_inventory_cards_reference_type_index` (`reference_type`),
  KEY `outlet_food_inventory_cards_reference_id_index` (`reference_id`),
  KEY `outlet_food_inventory_cards_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel kartu stok outlet untuk tracking inventory';

-- =====================================================
-- FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Foreign keys untuk outlet_food_returns
ALTER TABLE `outlet_food_returns`
  ADD CONSTRAINT `outlet_food_returns_outlet_food_good_receive_id_foreign` 
    FOREIGN KEY (`outlet_food_good_receive_id`) REFERENCES `outlet_food_good_receives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_returns_outlet_id_foreign` 
    FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_returns_warehouse_outlet_id_foreign` 
    FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_returns_created_by_foreign` 
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_returns_updated_by_foreign` 
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Foreign keys untuk outlet_food_return_items
ALTER TABLE `outlet_food_return_items`
  ADD CONSTRAINT `outlet_food_return_items_outlet_food_return_id_foreign` 
    FOREIGN KEY (`outlet_food_return_id`) REFERENCES `outlet_food_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_return_items_outlet_food_good_receive_item_id_foreign` 
    FOREIGN KEY (`outlet_food_good_receive_item_id`) REFERENCES `outlet_food_good_receive_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_return_items_item_id_foreign` 
    FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_return_items_unit_id_foreign` 
    FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

-- Foreign keys untuk outlet_food_inventory_cards
ALTER TABLE `outlet_food_inventory_cards`
  ADD CONSTRAINT `outlet_food_inventory_cards_inventory_item_id_foreign` 
    FOREIGN KEY (`inventory_item_id`) REFERENCES `outlet_food_inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_inventory_cards_id_outlet_foreign` 
    FOREIGN KEY (`id_outlet`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  ADD CONSTRAINT `outlet_food_inventory_cards_warehouse_outlet_id_foreign` 
    FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`) ON DELETE CASCADE;

-- =====================================================
-- INDEXES UNTUK PERFORMANCE
-- =====================================================

-- Index untuk pencarian return berdasarkan tanggal dan outlet
CREATE INDEX `idx_outlet_food_returns_outlet_date` ON `outlet_food_returns` (`outlet_id`, `return_date`);

-- Index untuk pencarian return berdasarkan status
CREATE INDEX `idx_outlet_food_returns_status_date` ON `outlet_food_returns` (`status`, `return_date`);

-- Index untuk pencarian item return berdasarkan item
CREATE INDEX `idx_outlet_food_return_items_item_return` ON `outlet_food_return_items` (`item_id`, `outlet_food_return_id`);

-- Index untuk pencarian kartu stok berdasarkan referensi
CREATE INDEX `idx_outlet_food_inventory_cards_reference` ON `outlet_food_inventory_cards` (`reference_type`, `reference_id`);

-- Index untuk pencarian kartu stok berdasarkan item dan outlet
CREATE INDEX `idx_outlet_food_inventory_cards_item_outlet` ON `outlet_food_inventory_cards` (`inventory_item_id`, `id_outlet`, `warehouse_outlet_id`);

-- =====================================================
-- COMMENTS DAN DOCUMENTATION
-- =====================================================

-- Comment untuk menjelaskan struktur tabel
ALTER TABLE `outlet_food_returns` COMMENT = 'Tabel utama untuk menyimpan data return outlet food. Setiap return terkait dengan satu Good Receive dan dapat berisi multiple items.';

ALTER TABLE `outlet_food_return_items` COMMENT = 'Tabel detail untuk menyimpan item-item yang di-return. Setiap item memiliki quantity dan unit yang spesifik.';

ALTER TABLE `outlet_food_inventory_cards` COMMENT = 'Tabel kartu stok outlet untuk tracking semua transaksi inventory (IN/OUT) termasuk return.';

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Query untuk memverifikasi tabel yang dibuat
SELECT 
    TABLE_NAME,
    TABLE_COMMENT,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('outlet_food_returns', 'outlet_food_return_items', 'outlet_food_inventory_cards')
ORDER BY TABLE_NAME;

-- Query untuk memverifikasi foreign keys
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('outlet_food_returns', 'outlet_food_return_items', 'outlet_food_inventory_cards')
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME;
