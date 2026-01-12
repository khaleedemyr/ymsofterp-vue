-- Query untuk membuat tabel non_food_payment_outlets
-- Jalankan query ini langsung di database

CREATE TABLE IF NOT EXISTS `non_food_payment_outlets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `non_food_payment_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` BIGINT UNSIGNED NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `bank_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_non_food_payment_id` (`non_food_payment_id`),
  INDEX `idx_outlet_id` (`outlet_id`),
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_bank_id` (`bank_id`),
  CONSTRAINT `non_food_payment_outlets_non_food_payment_id_foreign` 
    FOREIGN KEY (`non_food_payment_id`) 
    REFERENCES `non_food_payments` (`id`) 
    ON DELETE CASCADE,
  CONSTRAINT `non_food_payment_outlets_outlet_id_foreign` 
    FOREIGN KEY (`outlet_id`) 
    REFERENCES `tbl_data_outlet` (`id_outlet`) 
    ON DELETE SET NULL,
  CONSTRAINT `non_food_payment_outlets_category_id_foreign` 
    FOREIGN KEY (`category_id`) 
    REFERENCES `purchase_requisition_categories` (`id`) 
    ON DELETE SET NULL,
  CONSTRAINT `non_food_payment_outlets_bank_id_foreign` 
    FOREIGN KEY (`bank_id`) 
    REFERENCES `bank_accounts` (`id`) 
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
