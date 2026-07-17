-- Input manual complaint Customer Service (staging sebelum / untuk sync ke CVCC)
-- Eksekusi sekali di MySQL. Aman diulang: CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS `manual_cs_complaints` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(255) NOT NULL,
  `id_outlet` BIGINT UNSIGNED NULL,
  `author_name` VARCHAR(255) NOT NULL,
  `customer_contact` VARCHAR(120) NULL,
  `customer_email` VARCHAR(255) NULL,
  `input_channel` VARCHAR(32) NOT NULL DEFAULT 'phone',
  `event_at` DATETIME NOT NULL,
  `severity` VARCHAR(32) NOT NULL DEFAULT 'major',
  `topics` JSON NULL,
  `summary` VARCHAR(500) NULL,
  `complaint_text` TEXT NOT NULL,
  `notes` TEXT NULL,
  `sync_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `feedback_case_id` BIGINT UNSIGNED NULL,
  `synced_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_cs_complaints_number_unique` (`number`),
  KEY `manual_cs_complaints_id_outlet_index` (`id_outlet`),
  KEY `manual_cs_complaints_event_at_index` (`event_at`),
  KEY `manual_cs_complaints_sync_status_index` (`sync_status`),
  KEY `manual_cs_complaints_feedback_case_id_index` (`feedback_case_id`),
  KEY `manual_cs_complaints_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
