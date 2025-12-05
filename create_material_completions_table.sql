-- Create material_completions table for tracking material completion
-- Execute this SQL query directly in your database

CREATE TABLE `material_completions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `schedule_id` bigint(20) unsigned NOT NULL,
  `session_id` bigint(20) unsigned NOT NULL,
  `session_item_id` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NOT NULL,
  `time_spent_seconds` int(11) DEFAULT NULL,
  `completion_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_completions_user_id_material_id_schedule_id_unique` (`user_id`,`material_id`,`schedule_id`),
  KEY `material_completions_user_id_schedule_id_index` (`user_id`,`schedule_id`),
  KEY `material_completions_material_id_schedule_id_index` (`material_id`,`schedule_id`),
  KEY `material_completions_session_id_session_item_id_index` (`session_id`,`session_item_id`),
  KEY `material_completions_user_id_foreign` (`user_id`),
  KEY `material_completions_material_id_foreign` (`material_id`),
  KEY `material_completions_schedule_id_foreign` (`schedule_id`),
  KEY `material_completions_session_id_foreign` (`session_id`),
  KEY `material_completions_session_item_id_foreign` (`session_item_id`),
  CONSTRAINT `material_completions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_completions_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `lms_curriculum_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_completions_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `training_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_completions_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `lms_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_completions_session_item_id_foreign` FOREIGN KEY (`session_item_id`) REFERENCES `lms_session_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
