-- Create table for Web Device Tokens (for push notifications from web browser)
CREATE TABLE IF NOT EXISTS `web_device_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `device_token` text NOT NULL COMMENT 'FCM token from web browser',
  `browser` varchar(50) DEFAULT NULL COMMENT 'Browser name (Chrome, Firefox, Safari, etc)',
  `user_agent` text DEFAULT NULL COMMENT 'Full user agent string',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_user_active` (`user_id`, `is_active`),
  CONSTRAINT `fk_web_device_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

