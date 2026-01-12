-- Query untuk membuat tabel food_payment_outlets
-- Jalankan query ini langsung di database

CREATE TABLE IF NOT EXISTS `food_payment_outlets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `food_payment_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `bank_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_food_payment_id` (`food_payment_id`),
  INDEX `idx_outlet_id` (`outlet_id`),
  INDEX `idx_bank_id` (`bank_id`),
  CONSTRAINT `food_payment_outlets_food_payment_id_foreign` 
    FOREIGN KEY (`food_payment_id`) 
    REFERENCES `food_payments` (`id`) 
    ON DELETE CASCADE,
  CONSTRAINT `food_payment_outlets_outlet_id_foreign` 
    FOREIGN KEY (`outlet_id`) 
    REFERENCES `tbl_data_outlet` (`id_outlet`) 
    ON DELETE SET NULL,
  CONSTRAINT `food_payment_outlets_bank_id_foreign` 
    FOREIGN KEY (`bank_id`) 
    REFERENCES `bank_accounts` (`id`) 
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
