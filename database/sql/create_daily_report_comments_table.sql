-- Create daily_report_comments table
CREATE TABLE IF NOT EXISTS `daily_report_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `daily_report_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned NULL DEFAULT NULL COMMENT 'For replies to comments',
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_report_comments_daily_report_id_foreign` (`daily_report_id`),
  KEY `daily_report_comments_user_id_foreign` (`user_id`),
  KEY `daily_report_comments_parent_id_foreign` (`parent_id`),
  CONSTRAINT `daily_report_comments_daily_report_id_foreign` FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_report_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_report_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `daily_report_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
