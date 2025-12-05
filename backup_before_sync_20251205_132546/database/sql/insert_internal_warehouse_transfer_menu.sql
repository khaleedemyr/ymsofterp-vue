-- Create table for internal warehouse transfers (transfers between warehouse outlets within the same outlet)
CREATE TABLE `internal_warehouse_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) NOT NULL,
  `transfer_date` date NOT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `warehouse_outlet_from_id` bigint(20) unsigned NOT NULL,
  `warehouse_outlet_to_id` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `internal_warehouse_transfers_transfer_number_unique` (`transfer_number`),
  KEY `internal_warehouse_transfers_outlet_id_foreign` (`outlet_id`),
  KEY `internal_warehouse_transfers_warehouse_outlet_from_id_foreign` (`warehouse_outlet_from_id`),
  KEY `internal_warehouse_transfers_warehouse_outlet_to_id_foreign` (`warehouse_outlet_to_id`),
  KEY `internal_warehouse_transfers_created_by_foreign` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for internal warehouse transfer items
CREATE TABLE `internal_warehouse_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internal_warehouse_transfer_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `qty_small` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty_medium` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty_large` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_small` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `cost_medium` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `cost_large` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `internal_warehouse_transfer_items_internal_warehouse_transfer_id_foreign` (`internal_warehouse_transfer_id`),
  KEY `internal_warehouse_transfer_items_item_id_foreign` (`item_id`),
  KEY `internal_warehouse_transfer_items_unit_id_foreign` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert menu for Internal Warehouse Transfer
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Internal Warehouse Transfer', 'internal_warehouse_transfer', 4, '/internal-warehouse-transfer', 'fas fa-exchange-alt', NOW(), NOW());

-- Insert permissions for Internal Warehouse Transfer
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(LAST_INSERT_ID(), 'view', 'internal_warehouse_transfer_view', NOW(), NOW()),
(LAST_INSERT_ID(), 'create', 'internal_warehouse_transfer_create', NOW(), NOW()),
(LAST_INSERT_ID(), 'update', 'internal_warehouse_transfer_update', NOW(), NOW()),
(LAST_INSERT_ID(), 'delete', 'internal_warehouse_transfer_delete', NOW(), NOW());
