-- =============================================================
-- Lost & Breakage: partial replacement ledger (run once)
-- =============================================================

CREATE TABLE IF NOT EXISTS `lost_breakage_replacements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `detail_id` bigint unsigned NOT NULL,
  `qty_replaced` decimal(12,2) NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `replacement_item_id` bigint unsigned DEFAULT NULL COMMENT 'NULL = identical to lost line item',
  `note` text COLLATE utf8mb4_unicode_ci,
  `replaced_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lost_breakage_replacements_detail_id_index` (`detail_id`),
  KEY `lost_breakage_replacements_replaced_by_index` (`replaced_by`),
  CONSTRAINT `lost_breakage_replacements_detail_fk`
    FOREIGN KEY (`detail_id`) REFERENCES `lost_breakage_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lost_breakage_replacements_unit_fk`
    FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `lost_breakage_replacements_replacement_item_fk`
    FOREIGN KEY (`replacement_item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
