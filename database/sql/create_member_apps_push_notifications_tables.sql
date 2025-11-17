-- Create table for Member Device Tokens (for push notifications)
CREATE TABLE IF NOT EXISTS `member_apps_device_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `device_token` varchar(255) NOT NULL COMMENT 'FCM device token',
  `device_type` enum('android', 'ios', 'web') NOT NULL DEFAULT 'android',
  `device_id` varchar(255) NULL DEFAULT NULL COMMENT 'Unique device identifier',
  `app_version` varchar(50) NULL DEFAULT NULL COMMENT 'App version',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_device_token` (`device_token`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_device_type` (`device_type`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_device_token_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Push Notification History
CREATE TABLE IF NOT EXISTS `member_apps_push_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('general', 'promo', 'voucher', 'point', 'transaction', 'system') NOT NULL DEFAULT 'general',
  `target_type` enum('all', 'specific', 'filter') NOT NULL DEFAULT 'all' COMMENT 'all=all members, specific=specific member IDs, filter=by criteria',
  `target_member_ids` json NULL DEFAULT NULL COMMENT 'Array of member IDs if target_type = specific',
  `target_filter_criteria` json NULL DEFAULT NULL COMMENT 'Filter criteria if target_type = filter',
  `image_url` varchar(500) NULL DEFAULT NULL COMMENT 'Notification image URL',
  `action_url` varchar(500) NULL DEFAULT NULL COMMENT 'Deep link or action URL',
  `data` json NULL DEFAULT NULL COMMENT 'Additional data payload',
  `sent_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of notifications sent',
  `delivered_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of notifications delivered',
  `opened_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of notifications opened',
  `scheduled_at` timestamp NULL DEFAULT NULL COMMENT 'Schedule notification for later',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'User ID who created',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_target_type` (`target_type`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Push Notification Recipients (tracking)
CREATE TABLE IF NOT EXISTS `member_apps_push_notification_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `device_token_id` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `status` enum('pending', 'sent', 'delivered', 'failed', 'opened') NOT NULL DEFAULT 'pending',
  `fcm_message_id` varchar(255) NULL DEFAULT NULL COMMENT 'FCM message ID',
  `error_message` text NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_id` (`notification_id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_device_token_id` (`device_token_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_notification_recipient_notification` FOREIGN KEY (`notification_id`) REFERENCES `member_apps_push_notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notification_recipient_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notification_recipient_device_token` FOREIGN KEY (`device_token_id`) REFERENCES `member_apps_device_tokens` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

