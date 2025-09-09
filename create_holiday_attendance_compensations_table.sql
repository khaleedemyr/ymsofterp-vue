-- Create holiday_attendance_compensations table
CREATE TABLE IF NOT EXISTS `holiday_attendance_compensations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID karyawan',
  `holiday_date` date NOT NULL COMMENT 'Tanggal libur',
  `compensation_type` enum('extra_off','bonus') NOT NULL COMMENT 'Jenis kompensasi: extra_off atau bonus',
  `compensation_amount` decimal(10,2) NOT NULL COMMENT 'Jumlah kompensasi (1 untuk extra_off, nominal untuk bonus)',
  `compensation_description` text NOT NULL COMMENT 'Deskripsi kompensasi',
  `status` enum('pending','approved','used','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Status kompensasi',
  `used_date` date NULL DEFAULT NULL COMMENT 'Tanggal penggunaan (untuk extra_off)',
  `notes` text NULL DEFAULT NULL COMMENT 'Catatan tambahan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holiday_attendance_compensations_user_id_foreign` (`user_id`),
  KEY `holiday_attendance_compensations_user_id_holiday_date_index` (`user_id`, `holiday_date`),
  KEY `holiday_attendance_compensations_compensation_type_status_index` (`compensation_type`, `status`),
  KEY `holiday_attendance_compensations_holiday_date_index` (`holiday_date`),
  CONSTRAINT `holiday_attendance_compensations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel kompensasi kehadiran di hari libur';
