-- Just Academy — data awal (opsional)
INSERT INTO `ja_categories` (`name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`)
VALUES ('General Training', 'Kategori default', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
