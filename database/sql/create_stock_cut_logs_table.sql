-- Tabel untuk mencatat history potong stock per outlet per tanggal
CREATE TABLE `stock_cut_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int(11) NOT NULL COMMENT 'ID outlet yang melakukan potong stock',
  `tanggal` date NOT NULL COMMENT 'Tanggal potong stock',
  `type_filter` varchar(50) DEFAULT NULL COMMENT 'Filter type yang digunakan (food/beverages/null)',
  `total_items_cut` int(11) DEFAULT 0 COMMENT 'Total item yang dipotong stocknya',
  `total_modifiers_cut` int(11) DEFAULT 0 COMMENT 'Total modifier yang dipotong stocknya',
  `status` enum('success','failed') NOT NULL DEFAULT 'success' COMMENT 'Status potong stock',
  `error_message` text DEFAULT NULL COMMENT 'Pesan error jika gagal',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang melakukan potong stock',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_date_type` (`outlet_id`, `tanggal`, `type_filter`),
  KEY `idx_outlet_id` (`outlet_id`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log history potong stock per outlet per tanggal';
