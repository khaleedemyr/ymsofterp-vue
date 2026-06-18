-- =====================================================
-- Just Academy — skema tabel MVP (tanpa Laravel migration)
-- Jalankan manual di database ymsofterp (MySQL/MariaDB)
-- Urutan: jalankan file ini, lalu insert_just_academy_erp_menu.sql
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Master kategori & program
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ja_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_categories_name_unique` (`name`),
    KEY `ja_categories_is_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_programs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NULL,
    `code` VARCHAR(50) NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `duration_hours` DECIMAL(8, 2) NULL COMMENT 'Estimasi jam training',
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_programs_code_unique` (`code`),
    KEY `ja_programs_category_id_idx` (`category_id`),
    KEY `ja_programs_status_idx` (`status`),
    KEY `ja_programs_created_by_idx` (`created_by`),
    CONSTRAINT `ja_programs_category_id_foreign`
        FOREIGN KEY (`category_id`) REFERENCES `ja_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `ja_programs_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    KEY `ja_materials_created_by_idx` (`created_by`),
    CONSTRAINT `ja_materials_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_program_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` BIGINT UNSIGNED NOT NULL,
    `item_type` ENUM('material', 'quiz') NOT NULL,
    `material_id` BIGINT UNSIGNED NULL,
    `quiz_id` BIGINT UNSIGNED NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = wajib selesai',
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

-- -----------------------------------------------------
-- Quiz (library, urutan diatur via ja_program_items)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ja_quizzes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `pass_score` DECIMAL(5, 2) NOT NULL DEFAULT 70.00 COMMENT 'Nilai lulus (persen)',
    `time_limit_min` INT UNSIGNED NULL COMMENT 'Menit total quiz jika time_limit_mode=quiz',
    `time_limit_mode` ENUM('none', 'quiz', 'question') NOT NULL DEFAULT 'none',
    `time_limit_question_sec` INT UNSIGNED NULL COMMENT 'Detik per soal jika time_limit_mode=question',
    `questions_per_attempt` INT UNSIGNED NULL COMMENT 'Jumlah soal per tes. NULL = semua soal di bank',
    `randomize_questions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = acak pilihan & urutan soal',
    `randomize_options` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = acak urutan opsi jawaban',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_quizzes_is_active_idx` (`is_active`),
    CONSTRAINT `ja_quizzes_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_quiz_questions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `question` TEXT NOT NULL,
    `type` ENUM('mcq', 'essay') NOT NULL DEFAULT 'mcq',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `points` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_quiz_questions_quiz_id_idx` (`quiz_id`),
    CONSTRAINT `ja_quiz_questions_quiz_id_foreign`
        FOREIGN KEY (`quiz_id`) REFERENCES `ja_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_quiz_options` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `option_text` VARCHAR(500) NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_quiz_options_question_id_idx` (`question_id`),
    CONSTRAINT `ja_quiz_options_question_id_foreign`
        FOREIGN KEY (`question_id`) REFERENCES `ja_quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Jadwal training offline
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ja_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `start_at` DATETIME NOT NULL,
    `end_at` DATETIME NOT NULL,
    `location` VARCHAR(255) NULL,
    `outlet_id` INT UNSIGNED NULL COMMENT 'tbl_data_outlet.id_outlet',
    `region_id` INT UNSIGNED NULL,
    `capacity` INT UNSIGNED NULL,
    `status` ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    `qr_token` VARCHAR(64) NULL COMMENT 'Token unik untuk check-in QR',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_schedules_qr_token_unique` (`qr_token`),
    KEY `ja_schedules_program_id_idx` (`program_id`),
    KEY `ja_schedules_start_at_idx` (`start_at`),
    KEY `ja_schedules_status_idx` (`status`),
    KEY `ja_schedules_outlet_id_idx` (`outlet_id`),
    KEY `ja_schedules_region_id_idx` (`region_id`),
    KEY `ja_schedules_created_by_idx` (`created_by`),
    CONSTRAINT `ja_schedules_program_id_foreign`
        FOREIGN KEY (`program_id`) REFERENCES `ja_programs` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `ja_schedules_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_schedule_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `invite_source` ENUM('manual', 'jabatan', 'outlet', 'mixed') NOT NULL DEFAULT 'manual',
    `status` ENUM('invited', 'confirmed', 'declined') NOT NULL DEFAULT 'invited',
    `invited_at` DATETIME NULL,
    `invited_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_schedule_participants_schedule_user_unique` (`schedule_id`, `user_id`),
    KEY `ja_schedule_participants_user_id_idx` (`user_id`),
    KEY `ja_schedule_participants_status_idx` (`schedule_id`, `status`),
    CONSTRAINT `ja_schedule_participants_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_schedule_participants_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_schedule_participants_invited_by_foreign`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_schedule_trainers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `trainer_type` ENUM('internal', 'external') NOT NULL DEFAULT 'internal',
    `user_id` BIGINT UNSIGNED NULL,
    `external_name` VARCHAR(255) NULL,
    `role` ENUM('primary', 'assistant') NOT NULL DEFAULT 'assistant',
    `hours` DECIMAL(8, 2) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_schedule_trainers_schedule_user_unique` (`schedule_id`, `user_id`),
    KEY `ja_schedule_trainers_user_id_idx` (`user_id`),
    KEY `ja_schedule_trainers_type_idx` (`schedule_id`, `trainer_type`),
    CONSTRAINT `ja_schedule_trainers_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_schedule_trainers_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log filter undangan massal (jabatan / outlet) untuk audit
CREATE TABLE IF NOT EXISTS `ja_schedule_invite_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `invited_by` BIGINT UNSIGNED NULL,
    `filter_type` ENUM('users', 'jabatan', 'outlet', 'mixed') NOT NULL,
    `filter_payload` JSON NULL COMMENT 'user_ids, jabatan_ids, outlet_ids yang dipakai',
    `participants_added` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_schedule_invite_logs_schedule_id_idx` (`schedule_id`),
    CONSTRAINT `ja_schedule_invite_logs_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_schedule_invite_logs_invited_by_foreign`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Absensi, progress materi, quiz attempt, feedback
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ja_attendances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `check_in_at` DATETIME NULL,
    `check_out_at` DATETIME NULL,
    `method` ENUM('qr', 'manual') NOT NULL DEFAULT 'qr',
    `marked_by` BIGINT UNSIGNED NULL COMMENT 'Admin yang mark manual',
    `notes` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_attendances_schedule_user_unique` (`schedule_id`, `user_id`),
    KEY `ja_attendances_user_id_idx` (`user_id`),
    CONSTRAINT `ja_attendances_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_attendances_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_attendances_marked_by_foreign`
        FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_material_progress` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `material_id` BIGINT UNSIGNED NOT NULL,
    `completed_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_material_progress_unique` (`schedule_id`, `user_id`, `material_id`),
    KEY `ja_material_progress_user_id_idx` (`user_id`),
    KEY `ja_material_progress_material_id_idx` (`material_id`),
    CONSTRAINT `ja_material_progress_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_material_progress_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_material_progress_material_id_foreign`
        FOREIGN KEY (`material_id`) REFERENCES `ja_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_quiz_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `quiz_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `question_ids` JSON NULL COMMENT 'Urutan ID soal yang ditampilkan pada attempt ini',
    `option_orders` JSON NULL COMMENT 'Map question_id => urutan option_id untuk attempt ini',
    `quiz_progress` JSON NULL COMMENT 'Progress mode per-soal: current_index, question_started_at',
    `score` DECIMAL(8, 2) NULL,
    `passed` TINYINT(1) NOT NULL DEFAULT 0,
    `started_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ja_quiz_attempts_schedule_user_idx` (`schedule_id`, `user_id`),
    KEY `ja_quiz_attempts_quiz_id_idx` (`quiz_id`),
    KEY `ja_quiz_attempts_user_id_idx` (`user_id`),
    CONSTRAINT `ja_quiz_attempts_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_quiz_attempts_quiz_id_foreign`
        FOREIGN KEY (`quiz_id`) REFERENCES `ja_quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_quiz_attempts_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_quiz_answers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `option_id` BIGINT UNSIGNED NULL,
    `answer_text` TEXT NULL,
    `is_correct` TINYINT(1) NULL,
    `points_earned` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_quiz_answers_attempt_question_unique` (`attempt_id`, `question_id`),
    KEY `ja_quiz_answers_question_id_idx` (`question_id`),
    KEY `ja_quiz_answers_option_id_idx` (`option_id`),
    CONSTRAINT `ja_quiz_answers_attempt_id_foreign`
        FOREIGN KEY (`attempt_id`) REFERENCES `ja_quiz_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_quiz_answers_question_id_foreign`
        FOREIGN KEY (`question_id`) REFERENCES `ja_quiz_questions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_quiz_answers_option_id_foreign`
        FOREIGN KEY (`option_id`) REFERENCES `ja_quiz_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ja_feedbacks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `trainer_id` BIGINT UNSIGNED NULL,
    `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ja_feedbacks_schedule_user_unique` (`schedule_id`, `user_id`),
    KEY `ja_feedbacks_trainer_id_idx` (`trainer_id`),
    CONSTRAINT `ja_feedbacks_schedule_id_foreign`
        FOREIGN KEY (`schedule_id`) REFERENCES `ja_schedules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_feedbacks_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ja_feedbacks_trainer_id_foreign`
        FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verifikasi:
-- SHOW TABLES LIKE 'ja_%';
-- SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ja_%';
