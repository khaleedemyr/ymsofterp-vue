-- Table untuk menyimpan target per hari
CREATE TABLE IF NOT EXISTS `outlet_daily_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_qr_code` varchar(255) NOT NULL COMMENT 'QR Code outlet dari tbl_data_outlet',
  `month` int(2) NOT NULL COMMENT 'Bulan (1-12)',
  `year` int(4) NOT NULL COMMENT 'Tahun',
  `day_of_week` int(1) NOT NULL COMMENT '0=Minggu, 1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat, 6=Sabtu',
  `target_revenue` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Target revenue per hari',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat target',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_daily_targets_unique` (`outlet_qr_code`, `month`, `year`, `day_of_week`),
  KEY `outlet_daily_targets_outlet_qr_code_index` (`outlet_qr_code`),
  KEY `outlet_daily_targets_month_year_index` (`month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan target revenue per hari outlet';

-- Table untuk menyimpan setting forecast
CREATE TABLE IF NOT EXISTS `outlet_forecast_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_qr_code` varchar(255) NOT NULL COMMENT 'QR Code outlet dari tbl_data_outlet',
  `month` int(2) NOT NULL COMMENT 'Bulan (1-12)',
  `year` int(4) NOT NULL COMMENT 'Tahun',
  `weekday_target` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Target per hari weekday (Senin-Jumat)',
  `weekend_target` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Target per hari weekend (Sabtu-Minggu)',
  `auto_calculate` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Auto calculate, 0=Manual setting',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat setting',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_forecast_settings_unique` (`outlet_qr_code`, `month`, `year`),
  KEY `outlet_forecast_settings_outlet_qr_code_index` (`outlet_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan setting forecast outlet'; 