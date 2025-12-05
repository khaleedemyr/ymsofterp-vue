-- =====================================================
-- CREATE TABLES FOR RETAIL NON FOOD MODULE
-- =====================================================

-- Create retail_non_food table
-- This table stores the main retail non food transactions
CREATE TABLE IF NOT EXISTS `retail_non_food` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `retail_number` varchar(255) NOT NULL COMMENT 'Auto generated number with prefix RNF',
  `outlet_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to outlet',
  `warehouse_outlet_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to warehouse outlet',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User who created the transaction',
  `transaction_date` date NOT NULL COMMENT 'Date of transaction',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount of transaction',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved' COMMENT 'Transaction status',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `retail_non_food_retail_number_unique` (`retail_number`),
  KEY `retail_non_food_outlet_id_foreign` (`outlet_id`),
  KEY `retail_non_food_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `retail_non_food_created_by_foreign` (`created_by`),
  KEY `retail_non_food_transaction_date_index` (`transaction_date`),
  KEY `retail_non_food_status_index` (`status`),
  CONSTRAINT `retail_non_food_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  CONSTRAINT `retail_non_food_warehouse_outlet_id_foreign` FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `retail_non_food_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Main table for retail non food transactions';

-- Create retail_non_food_items table
-- This table stores the items for each retail non food transaction
CREATE TABLE IF NOT EXISTS `retail_non_food_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `retail_non_food_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to retail_non_food table',
  `item_name` varchar(255) NOT NULL COMMENT 'Name of the item (manual input)',
  `qty` decimal(10,2) NOT NULL COMMENT 'Quantity of the item',
  `unit` varchar(255) NOT NULL COMMENT 'Unit of measurement (manual input)',
  `price` decimal(15,2) NOT NULL COMMENT 'Price per unit',
  `subtotal` decimal(15,2) NOT NULL COMMENT 'Subtotal (qty * price)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retail_non_food_items_retail_non_food_id_foreign` (`retail_non_food_id`),
  KEY `retail_non_food_items_item_name_index` (`item_name`),
  CONSTRAINT `retail_non_food_items_retail_non_food_id_foreign` FOREIGN KEY (`retail_non_food_id`) REFERENCES `retail_non_food` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items table for retail non food transactions';

-- =====================================================
-- SAMPLE DATA (Optional - for testing)
-- =====================================================

-- Uncomment the lines below if you want to insert sample data
/*
INSERT INTO `retail_non_food` (`retail_number`, `outlet_id`, `warehouse_outlet_id`, `created_by`, `transaction_date`, `total_amount`, `notes`, `status`, `created_at`, `updated_at`) VALUES
('RNF202501200001', 1, 1, 1, '2025-01-20', 150000.00, 'Sample retail non food transaction', 'approved', NOW(), NOW());

INSERT INTO `retail_non_food_items` (`retail_non_food_id`, `item_name`, `qty`, `unit`, `price`, `subtotal`, `created_at`, `updated_at`) VALUES
(1, 'Tissue', 10.00, 'pcs', 5000.00, 50000.00, NOW(), NOW()),
(1, 'Sabun Cuci', 5.00, 'pcs', 20000.00, 100000.00, NOW(), NOW());
*/ 