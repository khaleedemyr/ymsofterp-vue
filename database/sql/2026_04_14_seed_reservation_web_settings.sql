-- =====================================================
-- Seed pengaturan reservasi website (Justus Kunest)
-- Tabel: web_profile_settings (sudah ada, TIDAK perlu ALTER)
-- Key-value: tinggal insert/update baris setting
-- =====================================================

CREATE TABLE IF NOT EXISTS `web_profile_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` TEXT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'text',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `web_profile_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Under Maintenance (1 = tampil maintenance di Justus Kunest, 0 = buka)
INSERT INTO `web_profile_settings` (`key`, `value`, `type`, `created_at`, `updated_at`)
VALUES ('reservation_maintenance_enabled', '1', 'boolean', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `type` = VALUES(`type`),
  `updated_at` = NOW();

-- Pemilihan meja tamu (1 = wajib pilih meja, 0 = skip availability + pilih meja)
INSERT INTO `web_profile_settings` (`key`, `value`, `type`, `created_at`, `updated_at`)
VALUES ('reservation_guest_table_selection_enabled', '1', 'boolean', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `type` = VALUES(`type`),
  `updated_at` = NOW();
