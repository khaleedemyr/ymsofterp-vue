-- Create table for Employee/User Device Tokens (for push notifications from approval app)
CREATE TABLE IF NOT EXISTS `employee_device_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `device_token` text NOT NULL COMMENT 'FCM token from mobile app',
  `device_type` enum('android', 'ios') NOT NULL DEFAULT 'android',
  `device_id` varchar(255) DEFAULT NULL COMMENT 'Unique device identifier',
  `app_version` varchar(50) DEFAULT NULL COMMENT 'App version',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_user_active` (`user_id`, `is_active`),
  KEY `idx_device_type` (`device_type`),
  CONSTRAINT `fk_employee_device_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

