-- Template balasan cepat inbox omnichannel (trigger / di composer chat)

CREATE TABLE IF NOT EXISTS `omni_message_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(120) NOT NULL,
    `shortcut` VARCHAR(64) NULL COMMENT 'Opsional, untuk filter setelah / (tanpa slash)',
    `body` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `omni_message_templates_is_active_sort_order_index` (`is_active`, `sort_order`),
    KEY `omni_message_templates_shortcut_index` (`shortcut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
