-- Create pivot table for reward outlets
CREATE TABLE IF NOT EXISTS `member_apps_reward_outlets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reward_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reward_outlets_unique` (`reward_id`, `outlet_id`),
  KEY `reward_outlets_reward_id_foreign` (`reward_id`),
  KEY `reward_outlets_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `reward_outlets_reward_id_foreign` FOREIGN KEY (`reward_id`) REFERENCES `member_apps_rewards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_outlets_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

