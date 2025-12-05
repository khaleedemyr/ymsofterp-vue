-- Check and create video_tutorial_groups table if not exists
CREATE TABLE IF NOT EXISTS `video_tutorial_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nama group video tutorial',
  `description` text DEFAULT NULL COMMENT 'Deskripsi group',
  `status` enum('A','N') NOT NULL DEFAULT 'A' COMMENT 'A=Active, N=Inactive',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_tutorial_groups_created_by_foreign` (`created_by`),
  KEY `video_tutorial_groups_status_created_at_index` (`status`, `created_at`),
  CONSTRAINT `video_tutorial_groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check and create video_tutorials table if not exists
CREATE TABLE IF NOT EXISTS `video_tutorials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in seconds',
  `file_size` bigint(20) DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `status` enum('A','N') NOT NULL DEFAULT 'A' COMMENT 'A=Active, N=Inactive',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_tutorials_group_id_foreign` (`group_id`),
  KEY `video_tutorials_created_by_foreign` (`created_by`),
  KEY `video_tutorials_status_created_at_index` (`status`, `created_at`),
  CONSTRAINT `video_tutorials_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `video_tutorial_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `video_tutorials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Show confirmation
SELECT 'Video tutorial tables created successfully!' as message; 