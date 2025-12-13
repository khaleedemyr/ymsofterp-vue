-- Create table for member notifications
CREATE TABLE IF NOT EXISTS `member_apps_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'point_earned, point_returned, tier_upgraded, tier_downgraded, voucher_received, challenge_completed, etc.',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `url` varchar(500) DEFAULT NULL COMMENT 'Optional URL for notification action',
  `data` json DEFAULT NULL COMMENT 'Additional data in JSON format',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_member_apps_notifications_member_id` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

