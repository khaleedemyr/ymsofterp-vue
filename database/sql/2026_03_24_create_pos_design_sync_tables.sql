-- POS Design sync tables (1 arah: ymsoftpos -> ymsofterp)
-- Jalankan di database ymsofterp

CREATE TABLE IF NOT EXISTS `pos_design_sections_sync` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `source_section_id` BIGINT NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `synced_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outlet_source_section` (`kode_outlet`, `source_section_id`),
  KEY `idx_sections_kode_outlet` (`kode_outlet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_design_tables_sync` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `source_table_id` BIGINT NOT NULL,
  `source_section_id` BIGINT NOT NULL,
  `nama` VARCHAR(50) NOT NULL,
  `tipe` ENUM('biasa','takeaway','ojol') NULL DEFAULT 'biasa',
  `bentuk` ENUM('round','square') NULL DEFAULT 'round',
  `orientasi` ENUM('horizontal','vertical') NULL DEFAULT 'horizontal',
  `jumlah_kursi` INT NULL DEFAULT 4,
  `warna` VARCHAR(20) NULL DEFAULT '#2563eb',
  `x` INT NOT NULL,
  `y` INT NOT NULL,
  `synced_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outlet_source_table` (`kode_outlet`, `source_table_id`),
  KEY `idx_tables_kode_outlet` (`kode_outlet`),
  KEY `idx_tables_source_section` (`source_section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_design_accessories_sync` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `source_accessory_id` BIGINT NOT NULL,
  `source_section_id` BIGINT NOT NULL,
  `type` ENUM('divider','lemari','pot','pos','kasir') NOT NULL,
  `x` INT NOT NULL,
  `y` INT NOT NULL,
  `panjang` INT NULL DEFAULT NULL,
  `orientasi` ENUM('horizontal','vertical') NULL DEFAULT 'horizontal',
  `synced_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_outlet_source_accessory` (`kode_outlet`, `source_accessory_id`),
  KEY `idx_accessories_kode_outlet` (`kode_outlet`),
  KEY `idx_accessories_source_section` (`source_section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_design_sync_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `status` ENUM('success','failed','validation_failed') NOT NULL,
  `sections_count` INT NOT NULL DEFAULT 0,
  `tables_count` INT NOT NULL DEFAULT 0,
  `accessories_count` INT NOT NULL DEFAULT 0,
  `message` VARCHAR(500) NULL DEFAULT NULL,
  `synced_at` DATETIME NULL DEFAULT NULL,
  `request_payload` LONGTEXT NULL,
  `response_payload` LONGTEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sync_logs_kode_outlet` (`kode_outlet`),
  KEY `idx_sync_logs_status` (`status`),
  KEY `idx_sync_logs_synced_at` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
