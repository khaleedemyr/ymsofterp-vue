-- Kontrol Suhu Chiller & Freezer — create table (sekali eksekusi)

CREATE TABLE IF NOT EXISTS `chiller_temperature_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_serial_id` bigint unsigned NOT NULL,
  `serial_number` varchar(50) NOT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `outlet_id` int NOT NULL,
  `warehouse_outlet_id` int DEFAULT NULL,
  `check_date` date NOT NULL,
  `check_time` varchar(5) NOT NULL COMMENT '10:00 | 16:00 | 22:00',
  `temp_display` decimal(6,2) NOT NULL,
  `temp_actual` decimal(6,2) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chiller_temp_serial_date_time_unique` (`asset_serial_id`, `check_date`, `check_time`),
  KEY `chiller_temp_outlet_date_index` (`outlet_id`, `check_date`),
  KEY `chiller_temp_warehouse_index` (`warehouse_outlet_id`),
  KEY `chiller_temp_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
