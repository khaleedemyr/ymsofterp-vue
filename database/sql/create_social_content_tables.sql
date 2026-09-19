-- =====================================================
-- Tabel konten + metrik Social Performance (IG + FB)
-- Jalankan sekali di DB production/staging jika tidak pakai migrate
-- =====================================================

CREATE TABLE IF NOT EXISTS `social_content_posts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `platform` VARCHAR(20) NOT NULL,
    `account_id` VARCHAR(64) NOT NULL,
    `account_label` VARCHAR(120) NULL,
    `external_id` VARCHAR(128) NOT NULL,
    `caption` TEXT NULL,
    `permalink` VARCHAR(500) NULL,
    `thumbnail_url` VARCHAR(1000) NULL,
    `media_type` VARCHAR(40) NULL,
    `posted_at` TIMESTAMP NULL,
    `last_synced_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `social_content_posts_platform_ext_unique` (`platform`, `external_id`),
    KEY `social_content_posts_acct_posted_idx` (`platform`, `account_id`, `posted_at`),
    KEY `social_content_posts_posted_idx` (`posted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_content_metrics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `social_content_post_id` BIGINT UNSIGNED NOT NULL,
    `likes` BIGINT UNSIGNED NULL,
    `comments` BIGINT UNSIGNED NULL,
    `shares` BIGINT UNSIGNED NULL,
    `saved` BIGINT UNSIGNED NULL,
    `impressions` BIGINT UNSIGNED NULL,
    `reach` BIGINT UNSIGNED NULL,
    `clicks` BIGINT UNSIGNED NULL,
    `synced_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `social_content_metrics_post_unique` (`social_content_post_id`),
    CONSTRAINT `social_content_metrics_post_fk`
        FOREIGN KEY (`social_content_post_id`) REFERENCES `social_content_posts` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
