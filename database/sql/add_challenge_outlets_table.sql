-- Create pivot table for challenge outlets
CREATE TABLE IF NOT EXISTS `member_apps_challenge_outlets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_outlets_unique` (`challenge_id`, `outlet_id`),
  KEY `challenge_outlets_challenge_id_foreign` (`challenge_id`),
  KEY `challenge_outlets_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `challenge_outlets_challenge_id_foreign` FOREIGN KEY (`challenge_id`) REFERENCES `member_apps_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_outlets_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

