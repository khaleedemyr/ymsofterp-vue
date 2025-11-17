-- Create table for Member Apps Feedbacks
CREATE TABLE IF NOT EXISTS `member_apps_feedbacks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Outlet ID from tbl_data_outlet',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `rating` tinyint(1) NULL DEFAULT NULL COMMENT 'Rating 1-5',
  `status` enum('pending', 'read', 'replied', 'resolved') NOT NULL DEFAULT 'pending',
  `admin_reply` text NULL DEFAULT NULL,
  `replied_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Admin user ID who replied',
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_outlet_id` (`outlet_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_feedback_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

