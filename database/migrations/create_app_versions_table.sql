-- Create app_versions table for managing mobile app version updates
CREATE TABLE IF NOT EXISTS `app_versions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` enum('android','ios') NOT NULL COMMENT 'Platform: android or ios',
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g., 1.0.0, 2.1.3)',
  `play_store_url` varchar(500) DEFAULT NULL COMMENT 'Google Play Store URL for Android',
  `app_store_url` varchar(500) DEFAULT NULL COMMENT 'Apple App Store URL for iOS',
  `force_update` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Force update required (1) or optional (0)',
  `update_message` text DEFAULT NULL COMMENT 'Custom update message to show to users',
  `whats_new` text DEFAULT NULL COMMENT 'What\'s new in this version',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is this version active (1) or inactive (0)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_active` (`platform`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (adjust version numbers and URLs as needed)
INSERT INTO `app_versions` (`platform`, `version`, `play_store_url`, `app_store_url`, `force_update`, `update_message`, `whats_new`, `is_active`, `created_at`, `updated_at`) VALUES
('android', '1.0.0', 'https://play.google.com/store/apps/details?id=com.justusgroup.memberapp', NULL, 0, 'A new version of the app is available. Please update to continue.', 'Initial release', 1, NOW(), NOW()),
('ios', '1.0.0', NULL, 'https://apps.apple.com/app/id123456789', 0, 'A new version of the app is available. Please update to continue.', 'Initial release', 1, NOW(), NOW());

