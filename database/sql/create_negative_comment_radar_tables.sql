-- =====================================================
-- Negative Comment Radar (Just Academy)
-- Sumber: feedback_cases (CVCC) yang negatif, dikelompokkan AI
-- Niche: staff_friendliness, speed_serving_time, wrong_transaction,
--        food_quality, cleanliness, grooming
-- Jalankan manual di MySQL (bukan Laravel migrate).
-- =====================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ncr_runs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date_from` DATE NOT NULL,
    `date_to` DATE NOT NULL,
    `id_outlet` INT NULL COMMENT 'NULL = semua outlet',
    `status` ENUM('pending', 'processing', 'done', 'failed') NOT NULL DEFAULT 'pending',
    `total_cases` INT UNSIGNED NOT NULL DEFAULT 0,
    `classified_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `niche_summary` JSON NULL COMMENT 'Agregat count per niche',
    `training_recommendations` JSON NULL COMMENT 'Saran training per niche + ringkas keseluruhan',
    `ai_provider` VARCHAR(40) NULL,
    `error_message` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `started_at` DATETIME NULL,
    `finished_at` DATETIME NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `ncr_runs_date_range_idx` (`date_from`, `date_to`),
    KEY `ncr_runs_status_idx` (`status`),
    KEY `ncr_runs_outlet_idx` (`id_outlet`),
    KEY `ncr_runs_created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ncr_classifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `run_id` BIGINT UNSIGNED NOT NULL,
    `feedback_case_id` BIGINT UNSIGNED NOT NULL,
    `primary_niche` VARCHAR(40) NOT NULL,
    `niches` JSON NOT NULL COMMENT 'Array niche codes (1–3)',
    `confidence` DECIMAL(5, 2) NULL COMMENT '0–100',
    `severity` VARCHAR(30) NULL,
    `id_outlet` INT NULL,
    `outlet_name` VARCHAR(150) NULL,
    `event_at` DATETIME NULL,
    `author_name` VARCHAR(150) NULL,
    `summary_text` VARCHAR(500) NULL,
    `raw_excerpt` VARCHAR(600) NULL,
    `ai_reason` VARCHAR(500) NULL,
    `training_hint` VARCHAR(400) NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ncr_classifications_run_case_unique` (`run_id`, `feedback_case_id`),
    KEY `ncr_classifications_primary_niche_idx` (`primary_niche`),
    KEY `ncr_classifications_outlet_idx` (`id_outlet`),
    KEY `ncr_classifications_case_idx` (`feedback_case_id`),
    CONSTRAINT `ncr_classifications_run_id_foreign`
        FOREIGN KEY (`run_id`) REFERENCES `ncr_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
