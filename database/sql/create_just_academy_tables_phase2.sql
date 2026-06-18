-- =====================================================
-- Just Academy — tabel Fase 2 (sertifikat & compliance)
-- Jalankan setelah create_just_academy_tables.sql
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ja_certificate_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `template_html` LONGTEXT NULL,
    `background_path` VARCHAR(500) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_certificate_templates_is_active_idx` (`is_active`),
    CONSTRAINT `ja_certificate_templates_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_certificates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `template_id` BIGINT UNSIGNED NULL,
    `certificate_no` VARCHAR(100) NOT NULL,
    `issued_at` DATETIME NOT NULL,
    `file_path` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_certificates_no_unique` (`certificate_no`),
    UNIQUE KEY `ja_certificates_schedule_user_unique` (`schedule_id`, `user_id`),
    KEY `ja_certificates_user_id_idx` (`user_id`),
    KEY `ja_certificates_template_id_idx` (`template_id`),
    CONSTRAINT `ja_certificates_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_certificates_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_certificates_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `ja_certificate_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compliance wajib per jabatan (fase nanti)
CREATE TABLE IF NOT EXISTS `ja_jabatan_required_programs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jabatan_id` INT UNSIGNED NOT NULL COMMENT 'tbl_data_jabatan.id_jabatan',
    `program_id` BIGINT UNSIGNED NOT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_jabatan_required_programs_unique` (`jabatan_id`, `program_id`),
    KEY `ja_jabatan_required_programs_program_id_idx` (`program_id`),
    CONSTRAINT `ja_jabatan_required_programs_program_id_foreign`
        FOREIGN KEY (`program_id`) REFERENCES `ja_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
