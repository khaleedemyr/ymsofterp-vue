-- Ticketing team settings: auto-assign team by category + region/outlet
-- Run after deploy if migration is not used: php artisan migrate

CREATE TABLE IF NOT EXISTS `ticket_team_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NULL,
  `status` CHAR(1) NOT NULL DEFAULT 'A',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_team_settings_category_status_idx` (`category_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_team_setting_regions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_team_setting_id` BIGINT UNSIGNED NOT NULL,
  `region_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tts_region_unique` (`ticket_team_setting_id`, `region_id`),
  KEY `ticket_team_setting_regions_region_id_idx` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_team_setting_outlets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_team_setting_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tts_outlet_unique` (`ticket_team_setting_id`, `outlet_id`),
  KEY `ticket_team_setting_outlets_outlet_id_idx` (`outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_team_setting_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_team_setting_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tts_user_unique` (`ticket_team_setting_id`, `user_id`),
  KEY `ticket_team_setting_users_user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
