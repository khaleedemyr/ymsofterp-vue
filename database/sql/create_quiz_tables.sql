-- Create table lms_quizzes
CREATE TABLE `lms_quizzes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Judul quiz',
  `description` text DEFAULT NULL COMMENT 'Deskripsi quiz',
  `instructions` text DEFAULT NULL COMMENT 'Instruksi untuk peserta quiz',
  `time_limit_type` enum('total','per_question') DEFAULT NULL COMMENT 'Tipe batas waktu',
  `time_limit_minutes` int(11) DEFAULT NULL COMMENT 'Batas waktu total dalam menit',
  `time_per_question_seconds` int(11) DEFAULT NULL COMMENT 'Batas waktu per pertanyaan dalam detik',
  `passing_score` int(11) NOT NULL DEFAULT 70 COMMENT 'Nilai minimum untuk lulus (0-100)',
  `max_attempts` int(11) DEFAULT NULL COMMENT 'Maksimal percobaan (NULL = tidak terbatas)',
  `is_randomized` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apakah urutan pertanyaan diacak',
  `show_results` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Apakah menampilkan hasil setelah selesai',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft' COMMENT 'Status quiz',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang membuat quiz',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User yang terakhir mengupdate quiz',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quizzes_created_by_foreign` (`created_by`),
  KEY `lms_quizzes_updated_by_foreign` (`updated_by`),
  KEY `lms_quizzes_status_index` (`status`),
  KEY `lms_quizzes_deleted_at_index` (`deleted_at`),
  CONSTRAINT `lms_quizzes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lms_quizzes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan data quiz';

-- Create table lms_quiz_questions
CREATE TABLE `lms_quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz',
  `question_text` text NOT NULL COMMENT 'Teks pertanyaan',
  `question_type` enum('multiple_choice','essay','true_false') NOT NULL DEFAULT 'multiple_choice' COMMENT 'Tipe pertanyaan',
  `points` int(11) NOT NULL DEFAULT 1 COMMENT 'Poin untuk pertanyaan ini',
  `is_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Apakah pertanyaan wajib dijawab',
  `order_number` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_questions_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_questions_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan pertanyaan quiz';

-- Create table lms_quiz_options
CREATE TABLE `lms_quiz_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan',
  `option_text` text NOT NULL COMMENT 'Teks opsi jawaban',
  `is_correct` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apakah opsi ini benar',
  `order_number` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan opsi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_options_question_id_foreign` (`question_id`),
  KEY `lms_quiz_options_is_correct_index` (`is_correct`),
  KEY `lms_quiz_options_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan opsi jawaban quiz';

-- Create table lms_quiz_attempts
CREATE TABLE `lms_quiz_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user yang mengikuti quiz',
  `enrollment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID enrollment (jika ada)',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu mulai quiz',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai quiz',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Nilai yang didapat (0-100)',
  `total_points` int(11) DEFAULT NULL COMMENT 'Total poin yang bisa didapat',
  `earned_points` int(11) DEFAULT NULL COMMENT 'Poin yang berhasil didapat',
  `is_passed` tinyint(1) DEFAULT NULL COMMENT 'Apakah lulus quiz',
  `time_taken_minutes` int(11) DEFAULT NULL COMMENT 'Waktu yang digunakan dalam menit',
  `status` enum('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress' COMMENT 'Status percobaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_attempts_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_attempts_user_id_foreign` (`user_id`),
  KEY `lms_quiz_attempts_enrollment_id_foreign` (`enrollment_id`),
  KEY `lms_quiz_attempts_status_index` (`status`),
  KEY `lms_quiz_attempts_is_passed_index` (`is_passed`),
  CONSTRAINT `lms_quiz_attempts_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_attempts_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `lms_enrollments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan percobaan quiz';

-- Create table lms_quiz_answers
CREATE TABLE `lms_quiz_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` bigint(20) unsigned NOT NULL COMMENT 'ID percobaan quiz',
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan',
  `selected_option_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID opsi yang dipilih (untuk multiple choice)',
  `essay_answer` text DEFAULT NULL COMMENT 'Jawaban essay',
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Apakah jawaban benar',
  `points_earned` decimal(5,2) DEFAULT NULL COMMENT 'Poin yang didapat untuk jawaban ini',
  `answered_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu menjawab',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan jawaban quiz';
