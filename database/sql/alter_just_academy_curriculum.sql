-- =====================================================
-- Just Academy — migrasi ke library materi/quiz + curriculum
-- Jalankan jika sudah pernah menjalankan create_just_academy_tables.sql versi lama
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Library materi (terpisah dari program)
CREATE TABLE IF NOT EXISTS `ja_materials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `type` ENUM('pdf', 'video', 'link', 'doc', 'other') NOT NULL DEFAULT 'pdf',
    `file_path` VARCHAR(500) NULL,
    `url` VARCHAR(500) NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_materials_is_active_idx` (`is_active`),
    CONSTRAINT `ja_materials_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pindahkan data lama ja_program_materials → ja_materials (pertahankan id)
INSERT IGNORE INTO `ja_materials` (`id`, `title`, `type`, `file_path`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT `id`, `title`, `type`, `file_path`, `url`, `is_active`, `created_at`, `updated_at`
FROM `ja_program_materials`;

-- Urutan item di program (materi / quiz campur)
CREATE TABLE IF NOT EXISTS `ja_program_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` BIGINT UNSIGNED NOT NULL,
    `item_type` ENUM('material', 'quiz') NOT NULL,
    `material_id` BIGINT UNSIGNED NULL,
    `quiz_id` BIGINT UNSIGNED NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = wajib selesai sebelum lanjut',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_program_items_program_sort_idx` (`program_id`, `sort_order`),
    CONSTRAINT `ja_program_items_program_id_foreign`
        FOREIGN KEY (`program_id`) REFERENCES `ja_programs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_program_items_material_id_foreign`
        FOREIGN KEY (`material_id`) REFERENCES `ja_materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_program_items_quiz_id_foreign`
        FOREIGN KEY (`quiz_id`) REFERENCES `ja_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ja_program_items` (`program_id`, `item_type`, `material_id`, `quiz_id`, `sort_order`, `is_required`, `created_at`, `updated_at`)
SELECT `program_id`, 'material', `id`, NULL, `sort_order`, `is_pre_read`, `created_at`, `updated_at`
FROM `ja_program_materials`;

INSERT IGNORE INTO `ja_program_items` (`program_id`, `item_type`, `material_id`, `quiz_id`, `sort_order`, `is_required`, `created_at`, `updated_at`)
SELECT `program_id`, 'quiz', NULL, `id`, 999 + `id`, 0, `created_at`, `updated_at`
FROM `ja_quizzes`
WHERE `program_id` IS NOT NULL;

-- Quiz jadi library (tanpa program_id & type pre/post)
ALTER TABLE `ja_quizzes` DROP FOREIGN KEY `ja_quizzes_program_id_foreign`;
ALTER TABLE `ja_quizzes` DROP INDEX `ja_quizzes_program_id_idx`;
ALTER TABLE `ja_quizzes` DROP INDEX `ja_quizzes_type_idx`;
ALTER TABLE `ja_quizzes` DROP COLUMN `program_id`;
ALTER TABLE `ja_quizzes` DROP COLUMN `type`;

-- Progress materi → referensi ja_materials
ALTER TABLE `ja_material_progress` DROP FOREIGN KEY `ja_material_progress_material_id_foreign`;
ALTER TABLE `ja_material_progress`
    ADD CONSTRAINT `ja_material_progress_material_id_foreign`
        FOREIGN KEY (`material_id`) REFERENCES `ja_materials` (`id`) ON DELETE CASCADE;

DROP TABLE IF EXISTS `ja_program_materials`;

SET FOREIGN_KEY_CHECKS = 1;
