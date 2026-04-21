-- Jalankan manual di MySQL (tanpa migration)

CREATE TABLE IF NOT EXISTS `outlet_revenue_target_headers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `outlet_id` INT NOT NULL,
  `target_month` DATE NOT NULL COMMENT 'Gunakan tanggal awal bulan, mis. 2026-04-01',
  `monthly_target` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outlet_month` (`outlet_id`, `target_month`),
  KEY `idx_target_month` (`target_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `outlet_revenue_target_details` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `header_id` BIGINT UNSIGNED NOT NULL,
  `forecast_date` DATE NOT NULL,
  `forecast_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_header_date` (`header_id`, `forecast_date`),
  KEY `idx_forecast_date` (`forecast_date`),
  CONSTRAINT `fk_revenue_target_header`
    FOREIGN KEY (`header_id`) REFERENCES `outlet_revenue_target_headers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

