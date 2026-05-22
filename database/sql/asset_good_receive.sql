-- Asset Good Receive Tables
-- Run manually: DO NOT use Laravel migrations

-- 1. asset_good_receives (GR header)
CREATE TABLE IF NOT EXISTS `asset_good_receives` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gr_number` VARCHAR(50) NOT NULL,
  `po_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to purchase_order_ops.id',
  `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to tbl_data_outlet.id_outlet',
  `warehouse_outlet_id` BIGINT UNSIGNED NULL COMMENT 'FK to warehouse_outlets.id',
  `receive_date` DATE NOT NULL,
  `received_by` BIGINT UNSIGNED NOT NULL COMMENT 'FK to users.id',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, completed',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_gr_number` (`gr_number`),
  KEY `idx_asset_gr_po` (`po_id`),
  KEY `idx_asset_gr_outlet` (`outlet_id`),
  KEY `idx_asset_gr_date` (`receive_date`),
  KEY `idx_asset_gr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. asset_good_receive_items (GR line items)
CREATE TABLE IF NOT EXISTS `asset_good_receive_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_good_receive_id` BIGINT UNSIGNED NOT NULL,
  `po_item_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to purchase_order_ops_items.id',
  `item_id` BIGINT UNSIGNED NULL COMMENT 'FK to items.id (resolved from PO item_name)',
  `unit_id` BIGINT UNSIGNED NULL COMMENT 'FK to units.id',
  `qty_ordered` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `qty_received` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `price` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Unit price from PO',
  `total` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'qty_received * price',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asset_gri_gr` (`asset_good_receive_id`),
  KEY `idx_asset_gri_po_item` (`po_item_id`),
  KEY `idx_asset_gri_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. asset_inventory_items (asset inventory master per item)
CREATE TABLE IF NOT EXISTS `asset_inventory_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to items.id',
  `small_unit_id` BIGINT UNSIGNED NULL COMMENT 'FK to units.id',
  `medium_unit_id` BIGINT UNSIGNED NULL COMMENT 'FK to units.id',
  `large_unit_id` BIGINT UNSIGNED NULL COMMENT 'FK to units.id',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_inv_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. asset_inventory_stocks (stock per outlet + warehouse)
CREATE TABLE IF NOT EXISTS `asset_inventory_stocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to asset_inventory_items.id',
  `outlet_id` INT UNSIGNED NOT NULL COMMENT 'FK to tbl_data_outlet.id_outlet',
  `warehouse_outlet_id` BIGINT UNSIGNED NULL COMMENT 'FK to warehouse_outlets.id',
  `qty_small` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `qty_medium` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `qty_large` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `value` DECIMAL(20,2) NOT NULL DEFAULT 0 COMMENT 'Total stock value',
  `last_cost_small` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `last_cost_medium` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `last_cost_large` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_stock_loc` (`inventory_item_id`, `outlet_id`, `warehouse_outlet_id`),
  KEY `idx_asset_stock_outlet` (`outlet_id`),
  KEY `idx_asset_stock_wh` (`warehouse_outlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. asset_inventory_cards (stock movement ledger)
CREATE TABLE IF NOT EXISTS `asset_inventory_cards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NOT NULL,
  `warehouse_outlet_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `reference_type` VARCHAR(50) NOT NULL COMMENT 'e.g. asset_good_receive',
  `reference_id` BIGINT UNSIGNED NOT NULL,
  `in_qty_small` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `in_qty_medium` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `in_qty_large` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `out_qty_small` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `out_qty_medium` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `out_qty_large` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `cost_per_small` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cost_per_medium` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `cost_per_large` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `value_in` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `value_out` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `saldo_qty_small` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `saldo_qty_medium` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `saldo_qty_large` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `saldo_value` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asset_card_inv` (`inventory_item_id`),
  KEY `idx_asset_card_outlet` (`outlet_id`),
  KEY `idx_asset_card_date` (`date`),
  KEY `idx_asset_card_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. asset_inventory_cost_histories (MAC tracking)
CREATE TABLE IF NOT EXISTS `asset_inventory_cost_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` INT UNSIGNED NOT NULL,
  `warehouse_outlet_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `reference_type` VARCHAR(50) NOT NULL,
  `reference_id` BIGINT UNSIGNED NOT NULL,
  `old_cost_small` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `old_cost_medium` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `old_cost_large` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `new_cost_small` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `new_cost_medium` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `new_cost_large` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `qty` DECIMAL(15,4) NOT NULL DEFAULT 0,
  `value` DECIMAL(20,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asset_cost_inv` (`inventory_item_id`),
  KEY `idx_asset_cost_outlet` (`outlet_id`),
  KEY `idx_asset_cost_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ownership (pemilik vs lokasi): jalankan database/sql/asset_ownership.sql pada DB yang sudah ada.
