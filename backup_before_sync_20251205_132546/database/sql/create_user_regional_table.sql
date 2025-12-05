-- Create user_regional table for Regional menu
-- This table manages which outlets a user can access

CREATE TABLE `user_regional` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_regional_user_id_outlet_id_unique` (`user_id`, `outlet_id`),
  KEY `user_regional_user_id_foreign` (`user_id`),
  KEY `user_regional_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `user_regional_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_regional_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
-- INSERT INTO `user_regional` (`user_id`, `outlet_id`, `created_at`, `updated_at`) VALUES
-- (1, 1, NOW(), NOW()),
-- (1, 2, NOW(), NOW()),
-- (2, 1, NOW(), NOW()),
-- (2, 3, NOW(), NOW());
