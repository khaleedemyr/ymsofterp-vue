-- Lacak komentar IG/FB yang sudah diproses (notifikasi admin inbox)
CREATE TABLE IF NOT EXISTS `omni_social_comment_seen` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `platform` VARCHAR(20) NOT NULL,
    `account_id` VARCHAR(64) NOT NULL,
    `account_label` VARCHAR(120) NULL,
    `post_meta_id` VARCHAR(128) NOT NULL,
    `post_preview` VARCHAR(500) NULL,
    `comment_meta_id` VARCHAR(128) NOT NULL,
    `commenter_name` VARCHAR(255) NULL,
    `comment_preview` VARCHAR(500) NULL,
    `comment_at` TIMESTAMP NULL,
    `notified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `omni_social_comment_seen_unique` (`platform`, `comment_meta_id`),
    KEY `omni_social_comment_seen_post_idx` (`platform`, `account_id`, `post_meta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
