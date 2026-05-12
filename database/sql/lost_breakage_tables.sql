-- =============================================================
-- Lost & Breakage Tables
-- =============================================================

CREATE TABLE IF NOT EXISTS `lost_breakage_headers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `outlet_id` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lost_breakage_headers_number_unique` (`number`),
  KEY `lost_breakage_headers_outlet_id_index` (`outlet_id`),
  KEY `lost_breakage_headers_created_by_index` (`created_by`),
  KEY `lost_breakage_headers_status_index` (`status`),
  KEY `lost_breakage_headers_date_index` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lost_breakage_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `header_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_id` bigint unsigned NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lost_breakage_details_header_id_index` (`header_id`),
  KEY `lost_breakage_details_item_id_index` (`item_id`),
  CONSTRAINT `lost_breakage_details_header_id_fk`
    FOREIGN KEY (`header_id`) REFERENCES `lost_breakage_headers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lost_breakage_approval_flows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `header_id` bigint unsigned NOT NULL,
  `approver_id` bigint unsigned NOT NULL,
  `approval_level` int NOT NULL DEFAULT 1,
  `status` enum('PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lost_breakage_approval_flows_header_id_index` (`header_id`),
  KEY `lost_breakage_approval_flows_approver_id_index` (`approver_id`),
  CONSTRAINT `lost_breakage_approval_flows_header_id_fk`
    FOREIGN KEY (`header_id`) REFERENCES `lost_breakage_headers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Menu registration (run once)
-- =============================================================
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `icon`, `route`, `parent_id`, `sort_order`, `created_at`, `updated_at`)
VALUES ('Lost & Breakage', 'lost_breakage', 'fa-solid fa-box-open', '/lost-breakage', NULL, 99, NOW(), NOW());

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'lost_breakage' LIMIT 1);

INSERT IGNORE INTO `erp_permission` (`menu_id`, `name`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'View Lost & Breakage', 'view', 'lost_breakage_view', NOW(), NOW());
