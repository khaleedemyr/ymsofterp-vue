-- Log percobaan GR Nomor Seri yang ditolak (scan gagal — tetap tidak masuk GR)
-- Jalankan manual di MySQL/MariaDB

CREATE TABLE IF NOT EXISTS `outlet_serial_receive_reject_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(50) NOT NULL,
  `serial_id` bigint unsigned DEFAULT NULL,
  `reject_reason` varchar(64) NOT NULL COMMENT 'not_found|not_dispatched|already_received|incomplete_do_data|wrong_outlet|duplicate_scan',
  `reject_message` varchar(500) NOT NULL,
  `scanned_by` bigint unsigned DEFAULT NULL,
  `scanner_name` varchar(255) DEFAULT NULL,
  `scanner_outlet_id` varchar(20) DEFAULT NULL,
  `scanner_outlet_name` varchar(255) DEFAULT NULL,
  `serial_target_outlet_id` varchar(20) DEFAULT NULL,
  `serial_target_outlet_name` varchar(255) DEFAULT NULL,
  `delivery_order_id` bigint unsigned DEFAULT NULL,
  `delivery_order_number` varchar(50) DEFAULT NULL,
  `warehouse_outlet_id` bigint unsigned DEFAULT NULL,
  `warehouse_outlet_name` varchar(255) DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `is_out` tinyint(1) DEFAULT NULL,
  `is_received` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `osrrl_serial_number_index` (`serial_number`),
  KEY `osrrl_created_at_index` (`created_at`),
  KEY `osrrl_reject_reason_index` (`reject_reason`),
  KEY `osrrl_scanner_outlet_index` (`scanner_outlet_id`),
  KEY `osrrl_target_outlet_index` (`serial_target_outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
