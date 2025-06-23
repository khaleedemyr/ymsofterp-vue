-- Check and Create Retail Non Food Tables
-- Run this script to ensure tables exist

-- Check if retail_non_food table exists
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'retail_non_food');

-- Create retail_non_food table if it doesn't exist
SET @sql = IF(@table_exists = 0, 
    'CREATE TABLE `retail_non_food` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `retail_number` varchar(255) NOT NULL,
      `outlet_id` bigint(20) UNSIGNED NOT NULL,
      `warehouse_outlet_id` bigint(20) UNSIGNED NULL,
      `created_by` bigint(20) UNSIGNED NOT NULL,
      `transaction_date` date NOT NULL,
      `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
      `notes` text NULL,
      `status` enum(\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'approved\',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    'SELECT \'retail_non_food table already exists\' as message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if retail_non_food_items table exists
SET @items_table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                          WHERE table_schema = DATABASE() 
                          AND table_name = 'retail_non_food_items');

-- Create retail_non_food_items table if it doesn't exist
SET @sql_items = IF(@items_table_exists = 0, 
    'CREATE TABLE `retail_non_food_items` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    'SELECT \'retail_non_food_items table already exists\' as message;'
);

PREPARE stmt_items FROM @sql_items;
EXECUTE stmt_items;
DEALLOCATE PREPARE stmt_items;

-- Show final status
SELECT 
    CASE 
        WHEN @table_exists = 0 THEN 'retail_non_food table created'
        ELSE 'retail_non_food table already exists'
    END as retail_non_food_status,
    CASE 
        WHEN @items_table_exists = 0 THEN 'retail_non_food_items table created'
        ELSE 'retail_non_food_items table already exists'
    END as retail_non_food_items_status; 