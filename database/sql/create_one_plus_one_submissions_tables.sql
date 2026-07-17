-- One Plus One — pengurangan jam lembur per karyawan per tanggal
-- Eksekusi sekali di MySQL (tanpa migrate). Aman diulang: CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS `one_plus_one_submissions` (
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
  UNIQUE KEY `one_plus_one_submissions_number_unique` (`number`),
  KEY `one_plus_one_submissions_submission_date_index` (`submission_date`),
  KEY `one_plus_one_submissions_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `one_plus_one_submission_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `one_plus_one_date` DATE NOT NULL,
  `deduction_hours` DECIMAL(8,2) NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `one_plus_one_submission_items_submission_id_foreign` (`submission_id`),
  KEY `one_plus_one_submission_items_user_id_one_plus_one_date_index` (`user_id`, `one_plus_one_date`),
  CONSTRAINT `one_plus_one_submission_items_submission_id_foreign`
    FOREIGN KEY (`submission_id`) REFERENCES `one_plus_one_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
