-- Create table for voucher outlets (pivot table)
CREATE TABLE IF NOT EXISTS `member_apps_voucher_outlets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id` INT UNSIGNED NOT NULL,
  `outlet_id` INT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_voucher_outlet` (`voucher_id`, `outlet_id`),
  KEY `idx_voucher_id` (`voucher_id`),
  KEY `idx_outlet_id` (`outlet_id`),
  CONSTRAINT `fk_voucher_outlets_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `member_apps_vouchers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voucher_outlets_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add all_outlets column to member_apps_vouchers table (optional, bisa juga tidak perlu jika hanya menggunakan pivot table)
-- ALTER TABLE `member_apps_vouchers`
-- ADD COLUMN `all_outlets` TINYINT(1) DEFAULT 1 AFTER `is_active`;

