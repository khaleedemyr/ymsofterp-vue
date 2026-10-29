-- Cleaning IPAL — create table (sekali eksekusi)

CREATE TABLE IF NOT EXISTS `cleaning_ipal_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int NOT NULL,
  `outlet_qr_code` varchar(255) DEFAULT NULL,
  `outlet_name` varchar(255) DEFAULT NULL,
  `cleaning_date` date NOT NULL,
  `keterangan` text DEFAULT NULL,
  `photo_before` LONGTEXT NULL COMMENT 'JSON array of storage paths',
  `photo_after` LONGTEXT NULL COMMENT 'JSON array of storage paths',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cleaning_ipal_outlet_date_index` (`outlet_id`, `cleaning_date`),
  KEY `cleaning_ipal_qr_index` (`outlet_qr_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
