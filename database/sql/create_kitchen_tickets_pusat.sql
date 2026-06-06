-- Kitchen Display tickets — tabel di server pusat (disync saat payment POS)
-- Jalankan sekali di database server pusat (ymsofterp)

CREATE TABLE IF NOT EXISTS `kitchen_tickets` (
  `id` varchar(36) NOT NULL,
  `order_item_id` varchar(36) NOT NULL,
  `order_id` varchar(32) NOT NULL,
  `station` enum('kitchen_asian','kitchen_western','kitchen_checker','bar','checkmark') NOT NULL,
  `status` enum('pending','processing','done') NOT NULL DEFAULT 'pending',
  `is_add` tinyint(1) NOT NULL DEFAULT 0,
  `print_seq` int NULL DEFAULT NULL,
  `item_name` varchar(100) NULL,
  `qty` int NULL,
  `tally` varchar(8) NULL,
  `modifiers` longtext NULL,
  `notes` text NULL,
  `item_type` varchar(32) NULL,
  `table_name` varchar(50) NULL,
  `order_no` varchar(32) NULL,
  `order_time` varchar(12) NULL,
  `waiter_name` varchar(100) NULL,
  `order_mode` varchar(50) NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime NULL DEFAULT NULL,
  `done_at` datetime NULL DEFAULT NULL,
  `wait_seconds` int NULL DEFAULT NULL,
  `work_seconds` int NULL DEFAULT NULL,
  `kode_outlet` varchar(50) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_station_status` (`station`, `status`, `created_at`),
  KEY `idx_order_outlet` (`order_id`, `kode_outlet`),
  KEY `idx_order_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
