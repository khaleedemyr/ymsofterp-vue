-- Lost & Breakage ↔ PR Asset ↔ GR Asset integration
-- Jalankan setelah lost_breakage_replacements.sql

-- Link baris L&B ke PR Asset
CREATE TABLE IF NOT EXISTS `lost_breakage_pr_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lost_breakage_detail_id` bigint unsigned NOT NULL,
  `purchase_requisition_id` bigint unsigned NOT NULL,
  `purchase_requisition_item_id` bigint unsigned DEFAULT NULL,
  `qty_planned` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lb_pr_lines_detail_id` (`lost_breakage_detail_id`),
  KEY `lb_pr_lines_pr_id` (`purchase_requisition_id`),
  KEY `lb_pr_lines_pri_id` (`purchase_requisition_item_id`),
  CONSTRAINT `lb_pr_lines_detail_fk`
    FOREIGN KEY (`lost_breakage_detail_id`) REFERENCES `lost_breakage_details` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sumber pencatatan penggantian (manual / GR asset)
ALTER TABLE `lost_breakage_replacements`
  ADD COLUMN `source` enum('manual','asset_gr') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' AFTER `replaced_by`,
  ADD COLUMN `asset_good_receive_id` bigint unsigned DEFAULT NULL AFTER `source`,
  ADD COLUMN `asset_good_receive_item_id` bigint unsigned DEFAULT NULL AFTER `asset_good_receive_id`,
  ADD COLUMN `purchase_requisition_id` bigint unsigned DEFAULT NULL AFTER `asset_good_receive_item_id`;
