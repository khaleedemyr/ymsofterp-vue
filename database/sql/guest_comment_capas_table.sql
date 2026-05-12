-- Tabel CAPA untuk Guest Comment yang severity-nya critical/major/minor.
-- Jalankan manual di MySQL/MariaDB (tanpa migration Laravel).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `guest_comment_capas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `guest_comment_form_id` bigint unsigned NOT NULL,
  `kronologi` text NOT NULL COMMENT 'Kronologi kejadian',
  `corrective_action` text NOT NULL COMMENT 'Tindakan perbaikan segera',
  `preventive_action` text NOT NULL COMMENT 'Tindakan pencegahan agar tidak terulang',
  `filled_by` bigint unsigned DEFAULT NULL COMMENT 'User yang mengisi CAPA',
  `filled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_comment_capas_form_id_index` (`guest_comment_form_id`),
  KEY `guest_comment_capas_filled_by_index` (`filled_by`),
  CONSTRAINT `guest_comment_capas_form_id_fk`
    FOREIGN KEY (`guest_comment_form_id`) REFERENCES `guest_comment_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
