-- =====================================================
-- POS Design table reservation settings
-- Tujuan: simpan setting on/off reservasi per meja agar tidak hilang saat sync ulang
-- Key: kode_outlet + source_table_id
-- =====================================================

CREATE TABLE IF NOT EXISTS `pos_design_table_reservation_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `source_table_id` BIGINT NOT NULL,
  `allow_reservation` TINYINT(1) NOT NULL DEFAULT 1,
  `smoking_type` ENUM('smoking','non_smoking') NOT NULL DEFAULT 'non_smoking',
  `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outlet_source_table_reservation` (`kode_outlet`, `source_table_id`),
  KEY `idx_reservation_setting_outlet` (`kode_outlet`),
  KEY `idx_reservation_setting_source_table` (`source_table_id`),
  KEY `idx_reservation_setting_allow` (`allow_reservation`),
  KEY `idx_reservation_setting_smoking_type` (`smoking_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
