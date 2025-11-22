-- Create table for tracking member challenge progress
CREATE TABLE IF NOT EXISTS `member_apps_challenge_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to member_apps_members.id',
  `challenge_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to member_apps_challenges.id',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'When member started the challenge',
  `progress_data` json DEFAULT NULL COMMENT 'Current progress: spending, visits, transactions, etc.',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether challenge is completed',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When challenge was completed',
  `reward_claimed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether reward has been claimed',
  `reward_claimed_at` timestamp NULL DEFAULT NULL COMMENT 'When reward was claimed',
  `reward_expires_at` timestamp NULL DEFAULT NULL COMMENT 'When reward expires (completed_at + validity_period_days)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_challenge_progress_unique` (`member_id`, `challenge_id`),
  KEY `member_challenge_progress_member_id_foreign` (`member_id`),
  KEY `member_challenge_progress_challenge_id_foreign` (`challenge_id`),
  KEY `member_challenge_progress_completed_index` (`is_completed`),
  KEY `member_challenge_progress_expires_index` (`reward_expires_at`),
  CONSTRAINT `member_challenge_progress_member_id_foreign` 
    FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_challenge_progress_challenge_id_foreign` 
    FOREIGN KEY (`challenge_id`) REFERENCES `member_apps_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

