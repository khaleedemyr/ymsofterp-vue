-- Pengajuan Lembur — tabel utama + detail karyawan
-- Eksekusi sekali di MySQL (tanpa migrate). Aman diulang: CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS `overtime_submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(255) NOT NULL,
  `submission_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `overtime_submissions_number_unique` (`number`),
  KEY `overtime_submissions_submission_date_index` (`submission_date`),
  KEY `overtime_submissions_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `overtime_submission_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `overtime_date` DATE NOT NULL,
  `requested_hours` DECIMAL(8,2) NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `overtime_submission_items_submission_id_foreign` (`submission_id`),
  KEY `overtime_submission_items_user_id_overtime_date_index` (`user_id`, `overtime_date`),
  CONSTRAINT `overtime_submission_items_submission_id_foreign`
    FOREIGN KEY (`submission_id`) REFERENCES `overtime_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
