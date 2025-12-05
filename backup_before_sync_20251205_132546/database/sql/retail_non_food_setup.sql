-- Retail Non Food Tables Setup
-- Run this SQL in your MySQL database

-- Drop tables if they exist (optional - uncomment if needed)
-- DROP TABLE IF EXISTS `retail_non_food_items`;
-- DROP TABLE IF EXISTS `retail_non_food`;

-- Create retail_non_food table
CREATE TABLE `retail_non_food` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `retail_number` varchar(255) NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_outlet_id` bigint(20) UNSIGNED NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `transaction_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retail_non_food_retail_number_unique` (`retail_number`),
  KEY `retail_non_food_outlet_id_foreign` (`outlet_id`),
  KEY `retail_non_food_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `retail_non_food_created_by_foreign` (`created_by`),
  CONSTRAINT `retail_non_food_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  CONSTRAINT `retail_non_food_warehouse_outlet_id_foreign` FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `retail_non_food_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create retail_non_food_items table
CREATE TABLE `retail_non_food_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `retail_non_food_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retail_non_food_items_retail_non_food_id_foreign` (`retail_non_food_id`),
  CONSTRAINT `retail_non_food_items_retail_non_food_id_foreign` FOREIGN KEY (`retail_non_food_id`) REFERENCES `retail_non_food` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Show success message
SELECT 'Retail Non Food tables created successfully!' as message; 