-- =====================================================
-- LMS (Learning Management System) Database Setup
-- =====================================================

-- 1. Create lms_courses table
CREATE TABLE IF NOT EXISTS `lms_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Judul kursus',
  `description` text DEFAULT NULL COMMENT 'Deskripsi kursus',
  `thumbnail_path` varchar(500) DEFAULT NULL COMMENT 'Path thumbnail kursus',
  `duration_hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Durasi kursus dalam jam',
  `difficulty_level` enum('beginner','intermediate','advanced') DEFAULT 'beginner' COMMENT 'Level kesulitan',
  `category_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID kategori kursus',
  `instructor_id` bigint(20) unsigned NOT NULL COMMENT 'ID instruktur',
  `max_students` int(11) DEFAULT NULL COMMENT 'Maksimal jumlah siswa',
  `price` decimal(10,2) DEFAULT 0.00 COMMENT 'Harga kursus',
  `is_free` tinyint(1) DEFAULT 1 COMMENT 'Apakah kursus gratis',
  `status` enum('draft','published','archived') DEFAULT 'draft' COMMENT 'Status kursus',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_courses_category_id_foreign` (`category_id`),
  KEY `lms_courses_instructor_id_foreign` (`instructor_id`),
  KEY `lms_courses_created_by_foreign` (`created_by`),
  KEY `lms_courses_status_index` (`status`),
  KEY `lms_courses_difficulty_level_index` (`difficulty_level`),
  CONSTRAINT `lms_courses_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `lms_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lms_courses_instructor_id_foreign` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_courses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel utama untuk kursus LMS';

-- 2. Create lms_categories table
CREATE TABLE IF NOT EXISTS `lms_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama kategori',
  `description` text DEFAULT NULL COMMENT 'Deskripsi kategori',
  `parent_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID kategori parent',
  `icon` varchar(100) DEFAULT NULL COMMENT 'Icon kategori',
  `color` varchar(7) DEFAULT '#3B82F6' COMMENT 'Warna kategori (hex)',
  `status` enum('A','N') DEFAULT 'A' COMMENT 'A=Active, N=Inactive',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_categories_parent_id_foreign` (`parent_id`),
  KEY `lms_categories_created_by_foreign` (`created_by`),
  KEY `lms_categories_status_index` (`status`),
  CONSTRAINT `lms_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `lms_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lms_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori kursus LMS';

-- 3. Create lms_lessons table
CREATE TABLE IF NOT EXISTS `lms_lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `title` varchar(255) NOT NULL COMMENT 'Judul lesson',
  `description` text DEFAULT NULL COMMENT 'Deskripsi lesson',
  `content` longtext DEFAULT NULL COMMENT 'Konten lesson (HTML)',
  `video_path` varchar(500) DEFAULT NULL COMMENT 'Path video lesson',
  `video_duration` int(11) DEFAULT NULL COMMENT 'Durasi video dalam detik',
  `thumbnail_path` varchar(500) DEFAULT NULL COMMENT 'Path thumbnail lesson',
  `lesson_type` enum('video','document','quiz','assignment') DEFAULT 'video' COMMENT 'Tipe lesson',
  `order_number` int(11) DEFAULT 0 COMMENT 'Urutan lesson',
  `is_free` tinyint(1) DEFAULT 0 COMMENT 'Apakah lesson gratis',
  `status` enum('draft','published','archived') DEFAULT 'draft' COMMENT 'Status lesson',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_lessons_course_id_foreign` (`course_id`),
  KEY `lms_lessons_created_by_foreign` (`created_by`),
  KEY `lms_lessons_status_index` (`status`),
  KEY `lms_lessons_order_number_index` (`order_number`),
  CONSTRAINT `lms_lessons_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_lessons_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lesson dalam kursus';

-- 4. Create lms_enrollments table
CREATE TABLE IF NOT EXISTS `lms_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user yang enroll',
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal enroll',
  `completion_date` timestamp NULL DEFAULT NULL COMMENT 'Tanggal selesai',
  `progress_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'Persentase progress',
  `status` enum('enrolled','in_progress','completed','dropped') DEFAULT 'enrolled' COMMENT 'Status enrollment',
  `certificate_path` varchar(500) DEFAULT NULL COMMENT 'Path sertifikat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_enrollments_course_user_unique` (`course_id`, `user_id`),
  KEY `lms_enrollments_user_id_foreign` (`user_id`),
  KEY `lms_enrollments_status_index` (`status`),
  KEY `lms_enrollments_enrollment_date_index` (`enrollment_date`),
  CONSTRAINT `lms_enrollments_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_enrollments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enrollment siswa ke kursus';

-- 5. Create lms_lesson_progress table
CREATE TABLE IF NOT EXISTS `lms_lesson_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint(20) unsigned NOT NULL COMMENT 'ID enrollment',
  `lesson_id` bigint(20) unsigned NOT NULL COMMENT 'ID lesson',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user',
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started' COMMENT 'Status progress',
  `watched_duration` int(11) DEFAULT 0 COMMENT 'Durasi yang sudah ditonton (detik)',
  `completion_date` timestamp NULL DEFAULT NULL COMMENT 'Tanggal selesai',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Nilai (untuk quiz/assignment)',
  `notes` text DEFAULT NULL COMMENT 'Catatan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_lesson_progress_enrollment_lesson_unique` (`enrollment_id`, `lesson_id`),
  KEY `lms_lesson_progress_user_id_foreign` (`user_id`),
  KEY `lms_lesson_progress_lesson_id_foreign` (`lesson_id`),
  KEY `lms_lesson_progress_status_index` (`status`),
  CONSTRAINT `lms_lesson_progress_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `lms_enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_lesson_progress_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lms_lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_lesson_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Progress lesson per user';

-- 6. Create lms_quizzes table
CREATE TABLE IF NOT EXISTS `lms_quizzes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` bigint(20) unsigned NOT NULL COMMENT 'ID lesson',
  `title` varchar(255) NOT NULL COMMENT 'Judul quiz',
  `description` text DEFAULT NULL COMMENT 'Deskripsi quiz',
  `time_limit` int(11) DEFAULT NULL COMMENT 'Batas waktu dalam menit',
  `passing_score` decimal(5,2) DEFAULT 70.00 COMMENT 'Nilai minimum untuk lulus',
  `max_attempts` int(11) DEFAULT 3 COMMENT 'Maksimal percobaan',
  `is_randomized` tinyint(1) DEFAULT 0 COMMENT 'Apakah soal diacak',
  `status` enum('draft','published','archived') DEFAULT 'draft' COMMENT 'Status quiz',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quizzes_lesson_id_foreign` (`lesson_id`),
  KEY `lms_quizzes_created_by_foreign` (`created_by`),
  KEY `lms_quizzes_status_index` (`status`),
  CONSTRAINT `lms_quizzes_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lms_lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quizzes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quiz dalam lesson';

-- 7. Create lms_quiz_questions table
CREATE TABLE IF NOT EXISTS `lms_quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz',
  `question_text` text NOT NULL COMMENT 'Teks pertanyaan',
  `question_type` enum('multiple_choice','true_false','essay') DEFAULT 'multiple_choice' COMMENT 'Tipe pertanyaan',
  `points` decimal(5,2) DEFAULT 1.00 COMMENT 'Poin untuk pertanyaan',
  `order_number` int(11) DEFAULT 0 COMMENT 'Urutan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_questions_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_questions_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_questions_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pertanyaan dalam quiz';

-- 8. Create lms_quiz_options table
CREATE TABLE IF NOT EXISTS `lms_quiz_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan',
  `option_text` text NOT NULL COMMENT 'Teks opsi',
  `is_correct` tinyint(1) DEFAULT 0 COMMENT 'Apakah opsi benar',
  `order_number` int(11) DEFAULT 0 COMMENT 'Urutan opsi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_options_question_id_foreign` (`question_id`),
  KEY `lms_quiz_options_order_number_index` (`order_number`),
  CONSTRAINT `lms_quiz_options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opsi jawaban quiz';

-- 9. Create lms_quiz_attempts table
CREATE TABLE IF NOT EXISTS `lms_quiz_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) unsigned NOT NULL COMMENT 'ID quiz',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user',
  `enrollment_id` bigint(20) unsigned NOT NULL COMMENT 'ID enrollment',
  `attempt_number` int(11) DEFAULT 1 COMMENT 'Nomor percobaan',
  `start_time` timestamp NULL DEFAULT NULL COMMENT 'Waktu mulai',
  `end_time` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Nilai yang didapat',
  `is_passed` tinyint(1) DEFAULT 0 COMMENT 'Apakah lulus',
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress' COMMENT 'Status percobaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_attempts_quiz_id_foreign` (`quiz_id`),
  KEY `lms_quiz_attempts_user_id_foreign` (`user_id`),
  KEY `lms_quiz_attempts_enrollment_id_foreign` (`enrollment_id`),
  KEY `lms_quiz_attempts_status_index` (`status`),
  CONSTRAINT `lms_quiz_attempts_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_attempts_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `lms_enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Percobaan quiz user';

-- 10. Create lms_quiz_answers table
CREATE TABLE IF NOT EXISTS `lms_quiz_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` bigint(20) unsigned NOT NULL COMMENT 'ID percobaan',
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ID pertanyaan',
  `selected_option_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID opsi yang dipilih',
  `essay_answer` text DEFAULT NULL COMMENT 'Jawaban essay',
  `is_correct` tinyint(1) DEFAULT 0 COMMENT 'Apakah jawaban benar',
  `points_earned` decimal(5,2) DEFAULT 0.00 COMMENT 'Poin yang didapat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_quiz_answers_attempt_id_foreign` (`attempt_id`),
  KEY `lms_quiz_answers_question_id_foreign` (`question_id`),
  KEY `lms_quiz_answers_selected_option_id_foreign` (`selected_option_id`),
  CONSTRAINT `lms_quiz_answers_attempt_id_foreign` FOREIGN KEY (`attempt_id`) REFERENCES `lms_quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_quiz_answers_selected_option_id_foreign` FOREIGN KEY (`selected_option_id`) REFERENCES `lms_quiz_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jawaban quiz user';

-- 11. Create lms_assignments table
CREATE TABLE IF NOT EXISTS `lms_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` bigint(20) unsigned NOT NULL COMMENT 'ID lesson',
  `title` varchar(255) NOT NULL COMMENT 'Judul assignment',
  `description` text DEFAULT NULL COMMENT 'Deskripsi assignment',
  `instructions` text DEFAULT NULL COMMENT 'Instruksi assignment',
  `due_date` timestamp NULL DEFAULT NULL COMMENT 'Batas waktu pengumpulan',
  `max_points` decimal(5,2) DEFAULT 100.00 COMMENT 'Nilai maksimal',
  `passing_score` decimal(5,2) DEFAULT 70.00 COMMENT 'Nilai minimum untuk lulus',
  `allow_late_submission` tinyint(1) DEFAULT 0 COMMENT 'Apakah boleh terlambat',
  `status` enum('draft','published','archived') DEFAULT 'draft' COMMENT 'Status assignment',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_assignments_lesson_id_foreign` (`lesson_id`),
  KEY `lms_assignments_created_by_foreign` (`created_by`),
  KEY `lms_assignments_status_index` (`status`),
  CONSTRAINT `lms_assignments_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lms_lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assignment dalam lesson';

-- 12. Create lms_assignment_submissions table
CREATE TABLE IF NOT EXISTS `lms_assignment_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) unsigned NOT NULL COMMENT 'ID assignment',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user',
  `enrollment_id` bigint(20) unsigned NOT NULL COMMENT 'ID enrollment',
  `submission_text` text DEFAULT NULL COMMENT 'Teks submission',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Path file submission',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu submission',
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Nilai yang didapat',
  `feedback` text DEFAULT NULL COMMENT 'Feedback dari instruktur',
  `is_late` tinyint(1) DEFAULT 0 COMMENT 'Apakah terlambat',
  `status` enum('submitted','graded','returned') DEFAULT 'submitted' COMMENT 'Status submission',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_assignment_submissions_assignment_id_foreign` (`assignment_id`),
  KEY `lms_assignment_submissions_user_id_foreign` (`user_id`),
  KEY `lms_assignment_submissions_enrollment_id_foreign` (`enrollment_id`),
  KEY `lms_assignment_submissions_status_index` (`status`),
  CONSTRAINT `lms_assignment_submissions_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_assignment_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_assignment_submissions_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `lms_enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Submission assignment user';

-- 13. Create lms_certificates table
CREATE TABLE IF NOT EXISTS `lms_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint(20) unsigned NOT NULL COMMENT 'ID enrollment',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user',
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `certificate_number` varchar(100) NOT NULL COMMENT 'Nomor sertifikat',
  `issue_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal terbit',
  `completion_date` timestamp NULL DEFAULT NULL COMMENT 'Tanggal selesai',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Path file sertifikat',
  `status` enum('pending','issued','revoked') DEFAULT 'pending' COMMENT 'Status sertifikat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_certificates_certificate_number_unique` (`certificate_number`),
  KEY `lms_certificates_enrollment_id_foreign` (`enrollment_id`),
  KEY `lms_certificates_user_id_foreign` (`user_id`),
  KEY `lms_certificates_course_id_foreign` (`course_id`),
  KEY `lms_certificates_status_index` (`status`),
  CONSTRAINT `lms_certificates_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `lms_enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_certificates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_certificates_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sertifikat kelulusan';

-- 14. Create lms_discussions table
CREATE TABLE IF NOT EXISTS `lms_discussions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user yang membuat',
  `title` varchar(255) NOT NULL COMMENT 'Judul diskusi',
  `content` text NOT NULL COMMENT 'Konten diskusi',
  `is_pinned` tinyint(1) DEFAULT 0 COMMENT 'Apakah di-pin',
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'Apakah dikunci',
  `status` enum('active','closed','archived') DEFAULT 'active' COMMENT 'Status diskusi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_discussions_course_id_foreign` (`course_id`),
  KEY `lms_discussions_user_id_foreign` (`user_id`),
  KEY `lms_discussions_status_index` (`status`),
  CONSTRAINT `lms_discussions_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_discussions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diskusi dalam kursus';

-- 15. Create lms_discussion_replies table
CREATE TABLE IF NOT EXISTS `lms_discussion_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `discussion_id` bigint(20) unsigned NOT NULL COMMENT 'ID diskusi',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'ID user yang reply',
  `parent_reply_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID reply parent (untuk nested replies)',
  `content` text NOT NULL COMMENT 'Konten reply',
  `is_solution` tinyint(1) DEFAULT 0 COMMENT 'Apakah ini solusi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lms_discussion_replies_discussion_id_foreign` (`discussion_id`),
  KEY `lms_discussion_replies_user_id_foreign` (`user_id`),
  KEY `lms_discussion_replies_parent_reply_id_foreign` (`parent_reply_id`),
  CONSTRAINT `lms_discussion_replies_discussion_id_foreign` FOREIGN KEY (`discussion_id`) REFERENCES `lms_discussions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_discussion_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_discussion_replies_parent_reply_id_foreign` FOREIGN KEY (`parent_reply_id`) REFERENCES `lms_discussion_replies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reply dalam diskusi';

-- Show completion message
SELECT 
    'LMS Database Setup Complete!' as message,
    'All LMS tables have been created successfully.' as details; 