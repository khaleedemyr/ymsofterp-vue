-- Create void_bill_logs table
CREATE TABLE IF NOT EXISTS `void_bill_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(100) NOT NULL,
  `order_nomor` VARCHAR(100) NOT NULL,
  `kode_outlet` VARCHAR(50) NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `username` VARCHAR(255) NULL,
  `reason` TEXT NOT NULL,
  `waktu` TIMESTAMP NOT NULL,
  `extra_info` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_order_nomor` (`order_nomor`),
  INDEX `idx_kode_outlet` (`kode_outlet`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_order_id_kode_outlet` (`order_id`, `kode_outlet`),
  INDEX `idx_waktu` (`waktu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create void_bill_detail_logs table
CREATE TABLE IF NOT EXISTS `void_bill_detail_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `void_log_id` BIGINT UNSIGNED NOT NULL,
  `order_id` VARCHAR(100) NOT NULL,
  `order_nomor` VARCHAR(100) NOT NULL,
  `order_data` LONGTEXT NOT NULL COMMENT 'JSON data of the order',
  `items_data` LONGTEXT NOT NULL COMMENT 'JSON data of the items',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_void_log_id` (`void_log_id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_order_nomor` (`order_nomor`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_void_bill_detail_logs_void_log_id` 
    FOREIGN KEY (`void_log_id`) 
    REFERENCES `void_bill_logs` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
