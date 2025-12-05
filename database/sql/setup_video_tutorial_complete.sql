-- Complete Video Tutorial Setup Script
-- This script will set up everything needed for the video tutorial feature

-- 1. Create video_tutorial_groups table if not exists
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

-- 2. Create video_tutorials table if not exists
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

-- 3. Insert menu items for video tutorial groups (if not exists)
INSERT IGNORE INTO `erp_menu` (`id`, `parent_id`, `name`, `url`, `icon`, `order_number`, `status`) VALUES
(NULL, 3, 'Group Video Tutorial', '/video-tutorial-groups', 'fa-folder', 1, 'A');

-- 4. Insert menu items for video tutorials (if not exists)
INSERT IGNORE INTO `erp_menu` (`id`, `parent_id`, `name`, `url`, `icon`, `order_number`, `status`) VALUES
(NULL, 3, 'Video Tutorial', '/video-tutorials', 'fa-video', 2, 'A');

-- 5. Get the menu IDs for permissions
SET @group_menu_id = (SELECT id FROM erp_menu WHERE name = 'Group Video Tutorial' AND parent_id = 3 LIMIT 1);
SET @video_menu_id = (SELECT id FROM erp_menu WHERE name = 'Video Tutorial' AND parent_id = 3 LIMIT 1);

-- 6. Insert permissions for video tutorial groups (if not exists)
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `name`, `status`) VALUES
(NULL, @group_menu_id, 'view', 'A'),
(NULL, @group_menu_id, 'create', 'A'),
(NULL, @group_menu_id, 'edit', 'A'),
(NULL, @group_menu_id, 'delete', 'A');

-- 7. Insert permissions for video tutorials (if not exists)
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `name`, `status`) VALUES
(NULL, @video_menu_id, 'view', 'A'),
(NULL, @video_menu_id, 'create', 'A'),
(NULL, @video_menu_id, 'edit', 'A'),
(NULL, @video_menu_id, 'delete', 'A');

-- 8. Show setup completion message
SELECT 
    'Video Tutorial Setup Complete!' as message,
    (SELECT COUNT(*) FROM video_tutorial_groups) as groups_count,
    (SELECT COUNT(*) FROM video_tutorials) as videos_count; 