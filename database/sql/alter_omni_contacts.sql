-- =====================================================
-- Kontak omnichannel: satu baris per nomor (dedupe nama+HP)
-- + FK dari omni_conversations ke omni_contacts
-- Jalankan sekali setelah create_omni_inbox_tables.sql (atau setelah DB sudah ada).
-- =====================================================

CREATE TABLE IF NOT EXISTS `omni_contacts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone_normalized` VARCHAR(32) NOT NULL,
    `display_name` VARCHAR(255) NULL,
    `member_apps_member_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `omni_contacts_phone_normalized_unique` (`phone_normalized`),
    KEY `omni_contacts_member_apps_member_id_index` (`member_apps_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom tautan (abaikan jika sudah ada)
SET @dbname = DATABASE();
SET @tablename = 'omni_conversations';
SET @columnname = 'omni_contact_id';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
    ) > 0,
    'SELECT 1',
    'ALTER TABLE `omni_conversations` ADD COLUMN `omni_contact_id` BIGINT UNSIGNED NULL AFTER `member_apps_member_id`'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Isi kontak dari percakapan yang sudah ada (satu baris per nomor; aman dijalankan ulang)
INSERT IGNORE INTO `omni_contacts` (`phone_normalized`, `display_name`, `member_apps_member_id`, `created_at`, `updated_at`)
SELECT
    `external_contact_id` AS `phone_normalized`,
    MAX(`contact_name`) AS `display_name`,
    MAX(`member_apps_member_id`) AS `member_apps_member_id`,
    NOW(),
    NOW()
FROM `omni_conversations`
WHERE `channel` = 'whatsapp'
  AND `external_contact_id` <> ''
GROUP BY `external_contact_id`;

UPDATE `omni_conversations` `c`
INNER JOIN `omni_contacts` `t` ON `t`.`phone_normalized` = `c`.`external_contact_id`
SET `c`.`omni_contact_id` = `t`.`id`
WHERE `c`.`channel` = 'whatsapp' AND (`c`.`omni_contact_id` IS NULL OR `c`.`omni_contact_id` <> `t`.`id`);

-- FK (InnoDB membuat index pada omni_contact_id bila belum ada)
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @dbname
          AND TABLE_NAME = @tablename
          AND CONSTRAINT_NAME = 'omni_conversations_omni_contact_id_foreign'
    ) > 0,
    'SELECT 1',
    'ALTER TABLE `omni_conversations` ADD CONSTRAINT `omni_conversations_omni_contact_id_foreign` FOREIGN KEY (`omni_contact_id`) REFERENCES `omni_contacts` (`id`) ON DELETE SET NULL'
));
PREPARE fkIfNotExists FROM @preparedStatement;
EXECUTE fkIfNotExists;
DEALLOCATE PREPARE fkIfNotExists;
