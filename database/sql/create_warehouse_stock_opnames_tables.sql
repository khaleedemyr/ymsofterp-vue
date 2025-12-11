-- Create table for Warehouse Stock Opname Header
CREATE TABLE IF NOT EXISTS `warehouse_stock_opnames` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `opname_number` varchar(50) NOT NULL COMMENT 'Format: WSO-YYYYMMDD-XXX',
  `warehouse_id` int(11) NOT NULL,
  `warehouse_division_id` int(11) DEFAULT NULL,
  `opname_date` date NOT NULL,
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED','COMPLETED') NOT NULL DEFAULT 'DRAFT',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `opname_number` (`opname_number`),
  KEY `idx_warehouse_division` (`warehouse_id`, `warehouse_division_id`),
  KEY `idx_status` (`status`),
  KEY `idx_opname_date` (`opname_date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Warehouse Stock Opname Items (Detail)
CREATE TABLE IF NOT EXISTS `warehouse_stock_opname_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_opname_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `qty_system_small` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Qty dari database (system)',
  `qty_system_medium` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_system_large` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_physical_small` decimal(15,2) DEFAULT NULL COMMENT 'Qty fisik yang diinput user',
  `qty_physical_medium` decimal(15,2) DEFAULT NULL,
  `qty_physical_large` decimal(15,2) DEFAULT NULL,
  `qty_diff_small` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Selisih (physical - system)',
  `qty_diff_medium` decimal(15,2) NOT NULL DEFAULT 0.00,
  `qty_diff_large` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL COMMENT 'Alasan selisih',
  `mac_before` decimal(15,4) DEFAULT NULL COMMENT 'MAC sebelum adjustment',
  `mac_after` decimal(15,4) DEFAULT NULL COMMENT 'MAC setelah adjustment',
  `value_adjustment` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Nilai adjustment (qty_diff * MAC)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stock_opname_id` (`stock_opname_id`),
  KEY `idx_inventory_item_id` (`inventory_item_id`),
  CONSTRAINT `fk_warehouse_opname_items_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `warehouse_stock_opnames` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warehouse_opname_items_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `food_inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Warehouse Stock Opname Approval Flows
CREATE TABLE IF NOT EXISTS `warehouse_stock_opname_approval_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_opname_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `approval_level` int(11) NOT NULL COMMENT 'Level 1 = terendah, level terakhir = tertinggi',
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stock_opname_id` (`stock_opname_id`),
  KEY `idx_approver_id` (`approver_id`),
  KEY `idx_status` (`status`),
  KEY `idx_approval_level` (`approval_level`),
  CONSTRAINT `fk_warehouse_opname_flows_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `warehouse_stock_opnames` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_warehouse_opname_flows_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

