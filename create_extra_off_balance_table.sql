-- Create extra_off_balance table untuk menyimpan saldo extra off karyawan
CREATE TABLE IF NOT EXISTS `extra_off_balance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID karyawan',
  `balance` int(11) NOT NULL DEFAULT 0 COMMENT 'Saldo extra off yang tersedia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extra_off_balance_user_id_unique` (`user_id`),
  CONSTRAINT `extra_off_balance_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel saldo extra off karyawan';

-- Create extra_off_transactions table untuk tracking transaksi extra off
CREATE TABLE IF NOT EXISTS `extra_off_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID karyawan',
  `transaction_type` enum('earned','used') NOT NULL COMMENT 'Jenis transaksi: earned (dapat) atau used (gunakan)',
  `amount` int(11) NOT NULL COMMENT 'Jumlah hari (1 untuk earned, -1 untuk used)',
  `source_type` enum('unscheduled_work','manual_adjustment','holiday_work') NOT NULL COMMENT 'Sumber extra off',
  `source_date` date NULL DEFAULT NULL COMMENT 'Tanggal sumber (untuk unscheduled_work atau holiday_work)',
  `description` text NOT NULL COMMENT 'Deskripsi transaksi',
  `used_date` date NULL DEFAULT NULL COMMENT 'Tanggal penggunaan (untuk transaction_type = used)',
  `approved_by` bigint(20) unsigned NULL DEFAULT NULL COMMENT 'ID yang approve (untuk manual_adjustment)',
  `status` enum('pending','approved','cancelled') NOT NULL DEFAULT 'approved' COMMENT 'Status transaksi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `extra_off_transactions_user_id_foreign` (`user_id`),
  KEY `extra_off_transactions_approved_by_foreign` (`approved_by`),
  KEY `extra_off_transactions_source_date_index` (`source_date`),
  KEY `extra_off_transactions_transaction_type_status_index` (`transaction_type`, `status`),
  CONSTRAINT `extra_off_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `extra_off_transactions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel transaksi extra off karyawan';

-- Insert initial balance untuk semua user yang aktif (jika belum ada)
INSERT IGNORE INTO `extra_off_balance` (`user_id`, `balance`, `created_at`, `updated_at`)
SELECT 
    u.id as user_id,
    0 as balance,
    NOW() as created_at,
    NOW() as updated_at
FROM `users` u
WHERE u.status = 'A' 
AND u.id NOT IN (SELECT user_id FROM `extra_off_balance`);
