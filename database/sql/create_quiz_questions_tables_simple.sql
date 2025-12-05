-- Create quiz questions tables (Simple version)
-- Jalankan SQL ini di database untuk membuat tabel yang diperlukan

-- Create lms_quiz_questions table
CREATE TABLE IF NOT EXISTS `lms_quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','essay','true_false') NOT NULL DEFAULT 'multiple_choice',
  `points` int(11) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_questions_quiz_id_foreign` (`quiz_id`),
  CONSTRAINT `lms_quiz_questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create lms_quiz_options table
CREATE TABLE IF NOT EXISTS `lms_quiz_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_options_question_id_foreign` (`question_id`),
  CONSTRAINT `lms_quiz_options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
