-- Instagram scraper (ymsofterp) — jalankan manual di MySQL/MariaDB.
-- Urutan: comments dulu di-drop jika ada, lalu posts.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `instagram_comments`;
DROP TABLE IF EXISTS `instagram_posts`;

CREATE TABLE `instagram_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_key` varchar(64) NOT NULL,
  `short_code` varchar(32) NOT NULL,
  `post_url` varchar(512) NOT NULL,
  `caption` text DEFAULT NULL,
  `comments_count` int unsigned NOT NULL DEFAULT 0,
  `owner_username` varchar(255) DEFAULT NULL,
  `post_timestamp` timestamp NULL DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `instagram_posts_profile_key_short_code_unique` (`profile_key`, `short_code`),
  KEY `instagram_posts_profile_key_index` (`profile_key`),
  KEY `instagram_posts_post_timestamp_index` (`post_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `instagram_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instagram_post_id` bigint unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `commented_at` timestamp NULL DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `instagram_comments_post_external_unique` (`instagram_post_id`, `external_id`),
  KEY `instagram_comments_commented_at_index` (`commented_at`),
  CONSTRAINT `instagram_comments_instagram_post_id_foreign`
    FOREIGN KEY (`instagram_post_id`) REFERENCES `instagram_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
