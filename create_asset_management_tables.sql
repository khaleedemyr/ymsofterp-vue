-- =====================================================
-- Asset Management System - Database Schema
-- =====================================================
-- Created for Restaurant Asset Management
-- Focus: Kitchen Equipment & Furniture
-- =====================================================

-- =====================================================
-- 1. asset_categories
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_categories_code_unique` (`code`),
  KEY `idx_asset_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. assets
-- =====================================================
CREATE TABLE IF NOT EXISTS `assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `current_outlet_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('Active','Maintenance','Disposed','Lost','Transfer') DEFAULT 'Active',
  `photos` json DEFAULT NULL,
  `description` text DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `qr_code_image` varchar(255) DEFAULT NULL,
  `useful_life` int(11) DEFAULT NULL COMMENT 'In years',
  `warranty_expiry_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assets_asset_code_unique` (`asset_code`),
  KEY `idx_assets_category_id` (`category_id`),
  KEY `idx_assets_current_outlet_id` (`current_outlet_id`),
  KEY `idx_assets_status` (`status`),
  KEY `idx_assets_created_by` (`created_by`),
  KEY `idx_assets_serial_number` (`serial_number`),
  KEY `idx_assets_purchase_date` (`purchase_date`),
  CONSTRAINT `assets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assets_current_outlet_id_foreign` FOREIGN KEY (`current_outlet_id`) REFERENCES `data_outlet` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. asset_transfers
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `from_outlet_id` bigint(20) unsigned NOT NULL,
  `to_outlet_id` bigint(20) unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Rejected') DEFAULT 'Pending',
  `requested_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_transfers_asset_id` (`asset_id`),
  KEY `idx_asset_transfers_from_outlet_id` (`from_outlet_id`),
  KEY `idx_asset_transfers_to_outlet_id` (`to_outlet_id`),
  KEY `idx_asset_transfers_status` (`status`),
  KEY `idx_asset_transfers_requested_by` (`requested_by`),
  KEY `idx_asset_transfers_approved_by` (`approved_by`),
  KEY `idx_asset_transfers_transfer_date` (`transfer_date`),
  CONSTRAINT `asset_transfers_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_from_outlet_id_foreign` FOREIGN KEY (`from_outlet_id`) REFERENCES `data_outlet` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_to_outlet_id_foreign` FOREIGN KEY (`to_outlet_id`) REFERENCES `data_outlet` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_transfers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. asset_maintenance_schedules
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_maintenance_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `maintenance_type` enum('Cleaning','Service','Repair','Inspection') NOT NULL,
  `frequency` enum('Daily','Weekly','Monthly','Quarterly','Yearly') NOT NULL,
  `next_maintenance_date` date NOT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_maintenance_schedules_asset_id` (`asset_id`),
  KEY `idx_asset_maintenance_schedules_next_date` (`next_maintenance_date`),
  KEY `idx_asset_maintenance_schedules_is_active` (`is_active`),
  CONSTRAINT `asset_maintenance_schedules_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. asset_maintenances
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_maintenances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `maintenance_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('Cleaning','Service','Repair','Inspection') NOT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_maintenances_asset_id` (`asset_id`),
  KEY `idx_asset_maintenances_schedule_id` (`maintenance_schedule_id`),
  KEY `idx_asset_maintenances_maintenance_date` (`maintenance_date`),
  KEY `idx_asset_maintenances_status` (`status`),
  KEY `idx_asset_maintenances_performed_by` (`performed_by`),
  CONSTRAINT `asset_maintenances_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_maintenances_schedule_id_foreign` FOREIGN KEY (`maintenance_schedule_id`) REFERENCES `asset_maintenance_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `asset_maintenances_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. asset_disposals
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_disposals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `disposal_date` date NOT NULL,
  `disposal_method` enum('Sold','Broken','Donated','Scrapped') NOT NULL,
  `disposal_value` decimal(15,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Rejected') DEFAULT 'Pending',
  `requested_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_disposals_asset_id` (`asset_id`),
  KEY `idx_asset_disposals_status` (`status`),
  KEY `idx_asset_disposals_requested_by` (`requested_by`),
  KEY `idx_asset_disposals_approved_by` (`approved_by`),
  KEY `idx_asset_disposals_disposal_date` (`disposal_date`),
  CONSTRAINT `asset_disposals_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_disposals_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `asset_disposals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. asset_documents
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `document_type` enum('Invoice','Warranty','Manual','Maintenance Record','Other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL COMMENT 'In bytes',
  `description` text DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_documents_asset_id` (`asset_id`),
  KEY `idx_asset_documents_document_type` (`document_type`),
  KEY `idx_asset_documents_uploaded_by` (`uploaded_by`),
  CONSTRAINT `asset_documents_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. asset_depreciations
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_depreciations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `purchase_price` decimal(15,2) NOT NULL,
  `useful_life` int(11) NOT NULL COMMENT 'In years',
  `depreciation_method` enum('Straight-Line') DEFAULT 'Straight-Line',
  `depreciation_rate` decimal(10,4) DEFAULT NULL COMMENT 'Auto-calculated: 1 / useful_life',
  `annual_depreciation` decimal(15,2) DEFAULT NULL COMMENT 'Auto-calculated: purchase_price / useful_life',
  `current_value` decimal(15,2) DEFAULT NULL COMMENT 'Auto-calculated',
  `accumulated_depreciation` decimal(15,2) DEFAULT 0,
  `last_calculated_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_depreciations_asset_id_unique` (`asset_id`),
  KEY `idx_asset_depreciations_last_calculated` (`last_calculated_date`),
  CONSTRAINT `asset_depreciations_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. asset_depreciation_history
-- =====================================================
CREATE TABLE IF NOT EXISTS `asset_depreciation_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `calculation_date` date NOT NULL,
  `purchase_price` decimal(15,2) NOT NULL COMMENT 'Snapshot',
  `useful_life` int(11) NOT NULL COMMENT 'Snapshot in years',
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `years_used` decimal(10,2) DEFAULT NULL COMMENT 'Years from purchase date to calculation date',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asset_depreciation_history_asset_id` (`asset_id`),
  KEY `idx_asset_depreciation_history_calculation_date` (`calculation_date`),
  CONSTRAINT `asset_depreciation_history_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Indexes for better performance
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX `idx_assets_category_status` ON `assets` (`category_id`, `status`);
CREATE INDEX `idx_assets_outlet_status` ON `assets` (`current_outlet_id`, `status`);
CREATE INDEX `idx_asset_transfers_asset_status` ON `asset_transfers` (`asset_id`, `status`);
CREATE INDEX `idx_asset_maintenances_asset_date` ON `asset_maintenances` (`asset_id`, `maintenance_date`);
CREATE INDEX `idx_asset_maintenances_asset_status` ON `asset_maintenances` (`asset_id`, `status`);

-- =====================================================
-- Notes:
-- =====================================================
-- 1. Asset Code format: AST-YYYY-XXXX (e.g., AST-2026-0001)
-- 2. Photos stored as JSON array of file paths
-- 3. QR Code stored as string and image file path
-- 4. All dates use date type (not datetime)
-- 5. Foreign keys use appropriate ON DELETE actions:
--    - RESTRICT: Prevent deletion if referenced
--    - CASCADE: Delete related records
--    - SET NULL: Set to NULL if referenced record deleted
-- 6. All tables use utf8mb4 charset for proper Unicode support
-- 7. Timestamps use timestamp type with NULL default for created_at/updated_at
-- =====================================================

