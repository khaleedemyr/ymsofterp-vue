-- Create quiz questions tables
-- This script creates the necessary tables for quiz questions and options

-- Create lms_quiz_questions table
CREATE TABLE IF NOT EXISTS `lms_quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz yang terkait',
  `question_text` text NOT NULL COMMENT 'Teks pertanyaan',
  `question_type` enum('multiple_choice','essay','true_false') NOT NULL DEFAULT 'multiple_choice' COMMENT 'Tipe pertanyaan',
  `points` int(11) NOT NULL DEFAULT 1 COMMENT 'Poin untuk pertanyaan ini',
  `is_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Apakah pertanyaan wajib dijawab',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_questions_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_questions_question_type_index` (`question_type`),
  KEY `lms_quiz_questions_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel pertanyaan quiz';

-- Create lms_quiz_options table
CREATE TABLE IF NOT EXISTS `lms_quiz_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan yang terkait',
  `option_text` text NOT NULL COMMENT 'Teks opsi jawaban',
  `is_correct` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apakah opsi ini jawaban yang benar',
  `order_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Urutan opsi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_options_question_id_foreign` (`question_id`),
  KEY `lms_quiz_options_is_correct_index` (`is_correct`),
  KEY `lms_quiz_options_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel opsi jawaban quiz';

-- Create lms_quiz_attempts table (for future use)
CREATE TABLE IF NOT EXISTS `lms_quiz_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz yang dikerjakan',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user yang mengerjakan',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu mulai mengerjakan',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai mengerjakan',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Nilai yang diperoleh',
  `is_passed` tinyint(1) DEFAULT NULL COMMENT 'Apakah lulus',
  `attempt_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Nomor percobaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_attempts_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_attempts_user_id_foreign` (`user_id`),
  KEY `lms_quiz_attempts_score_index` (`score`),
  KEY `lms_quiz_attempts_is_passed_index` (`is_passed`),
  CONSTRAINT `lms_quiz_attempts_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel percobaan mengerjakan quiz';

-- Create lms_quiz_answers table (for future use)
CREATE TABLE IF NOT EXISTS `lms_quiz_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` bigint(20) unsigned NOT NULL COMMENT 'ID percobaan quiz',
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan',
  `selected_option_id` bigint(20) unsigned NULL COMMENT 'ID opsi yang dipilih (untuk multiple choice/true_false)',
  `essay_answer` text NULL COMMENT 'Jawaban essay',
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Apakah jawaban benar',
  `points_earned` decimal(5,2) DEFAULT NULL COMMENT 'Poin yang diperoleh',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_answers_attempt_id_foreign` (`attempt_id`),
  KEY `lms_quiz_answers_question_id_foreign` (`question_id`),
  KEY `lms_quiz_answers_selected_option_id_foreign` (`selected_option_id`),
  KEY `lms_quiz_answers_is_correct_index` (`is_correct`),
  CONSTRAINT `lms_quiz_answers_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `lms_quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_answers_selected_option_id_foreign` FOREIGN KEY (`selected_option_id`) REFERENCES `lms_quiz_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel jawaban quiz';
