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

-- Opsional (untuk schema existing, tanpa DROP/CREATE): materialized metrics agar list lebih cepat.
-- Jalankan jika tabel instagram_posts Anda belum punya kolom berikut.
-- ALTER TABLE `instagram_posts`
--   ADD COLUMN `likes_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `comments_count`,
--   ADD COLUMN `views_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `likes_count`,
--   ADD COLUMN `media_url` VARCHAR(1024) NULL AFTER `owner_username`;

-- Backfill sekali setelah kolom ditambahkan (opsional):
-- UPDATE `instagram_posts`
-- SET
--   `likes_count` = CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.likesCount')), '0') AS UNSIGNED),
--   `views_count` = CAST(COALESCE(
--       JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.videoViewCount')),
--       JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.videoPlayCount')),
--       JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.video_view_count')),
--       '0'
--   ) AS UNSIGNED),
--   `media_url` = NULLIF(COALESCE(
--       JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.displayUrl')),
--       JSON_UNQUOTE(JSON_EXTRACT(`raw_json`, '$.images[0]')),
--       ''
--   ), '');

-- Opsional (direkomendasikan): metadata AI untuk komentar Instagram
-- agar report bisa tampilkan akun/post asal komentar dan skip yang sudah pernah diklasifikasi.
-- ALTER TABLE `google_review_ai_items`
--   ADD COLUMN `source_item_id` BIGINT UNSIGNED NULL AFTER `summary_id`,
--   ADD COLUMN `source_account` VARCHAR(64) NULL AFTER `source_item_id`,
--   ADD COLUMN `source_post_url` VARCHAR(512) NULL AFTER `source_account`,
--   ADD COLUMN `source_post_shortcode` VARCHAR(32) NULL AFTER `source_post_url`,
--   ADD KEY `google_review_ai_items_source_item_id_index` (`source_item_id`);

-- Backfill metadata source untuk report Instagram lama (tanpa klasifikasi ulang)
-- Prasyarat: kolom source_* di google_review_ai_items sudah ada.
-- Metode:
-- 1) Isi by exact match username + text (paling aman untuk data lama).
-- 2) Jika source_item_id sudah ada, pastikan source_* sinkron dari relasi komentar→post.
--
-- STEP 1: backfill by username + text untuk report source instagram_comments_db
-- UPDATE `google_review_ai_items` i
-- JOIN `google_review_ai_reports` r
--   ON r.id = i.report_id
--  AND r.source = 'instagram_comments_db'
--  AND r.status = 'completed'
-- JOIN `instagram_comments` c
--   ON c.username = i.author
--  AND c.text = i.text
-- JOIN `instagram_posts` p
--   ON p.id = c.instagram_post_id
-- SET
--   i.source_item_id = COALESCE(i.source_item_id, c.id),
--   i.source_account = COALESCE(NULLIF(i.source_account, ''), p.profile_key),
--   i.source_post_url = COALESCE(NULLIF(i.source_post_url, ''), p.post_url),
--   i.source_post_shortcode = COALESCE(NULLIF(i.source_post_shortcode, ''), p.short_code),
--   i.rating = CASE WHEN i.rating IS NULL OR i.rating = '' THEN p.profile_key ELSE i.rating END
-- WHERE
--   (i.source_account IS NULL OR i.source_account = '' OR i.source_post_url IS NULL OR i.source_post_url = '');
--
-- STEP 2: sinkronkan ulang source_* by source_item_id (jika sudah ada)
-- UPDATE `google_review_ai_items` i
-- JOIN `google_review_ai_reports` r
--   ON r.id = i.report_id
--  AND r.source = 'instagram_comments_db'
--  AND r.status = 'completed'
-- JOIN `instagram_comments` c
--   ON c.id = i.source_item_id
-- JOIN `instagram_posts` p
--   ON p.id = c.instagram_post_id
-- SET
--   i.source_account = p.profile_key,
--   i.source_post_url = p.post_url,
--   i.source_post_shortcode = p.short_code,
--   i.rating = CASE WHEN i.rating IS NULL OR i.rating = '' THEN p.profile_key ELSE i.rating END
-- WHERE
--   i.source_item_id IS NOT NULL;
