-- POS Sync Tables (based on dummy server schema)
-- Requirement: every table must have kode_outlet
-- Run this on SERVER PUSAT database

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `orders_dummy` (
  `id` varchar(50) NOT NULL,
  `nomor` varchar(50) NOT NULL,
  `table` varchar(255) DEFAULT '-',
  `paid_number` varchar(50) DEFAULT NULL,
  `waiters` varchar(255) DEFAULT '-',
  `member_id` varchar(50) DEFAULT NULL,
  `member_name` varchar(255) DEFAULT NULL,
  `mode` varchar(100) DEFAULT NULL,
  `pax` int DEFAULT 0,
  `total` decimal(18,2) DEFAULT 0,
  `discount` decimal(18,2) DEFAULT 0,
  `cashback` decimal(18,2) DEFAULT 0,
  `dpp` decimal(18,2) DEFAULT 0,
  `pb1` decimal(18,2) DEFAULT 0,
  `service` decimal(18,2) DEFAULT 0,
  `grand_total` decimal(18,2) DEFAULT 0,
  `status` varchar(50) DEFAULT 'paid',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `joined_tables` text,
  `promo_ids` text,
  `commfee` decimal(18,2) DEFAULT 0,
  `rounding` decimal(18,2) DEFAULT 0,
  `sales_lead` varchar(50) DEFAULT NULL,
  `redeem_amount` decimal(18,2) DEFAULT 0,
  `manual_discount_amount` decimal(18,2) DEFAULT 0,
  `manual_discount_reason` text,
  `voucher_info` longtext,
  `inactive_promo_items` longtext,
  `promo_discount_info` longtext,
  `issync` tinyint(1) DEFAULT 1,
  `reservation_id` bigint DEFAULT NULL,
  `kode_outlet` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orders_dummy_status` (`status`),
  KEY `idx_orders_dummy_paid_number` (`paid_number`),
  KEY `idx_orders_dummy_kode_outlet` (`kode_outlet`),
  KEY `idx_orders_dummy_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items_dummy` (
  `id` varchar(50) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `item_id` bigint DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(18,2) DEFAULT 0,
  `price` decimal(18,2) DEFAULT 0,
  `tally` varchar(50) DEFAULT NULL,
  `modifiers` longtext,
  `notes` text,
  `subtotal` decimal(18,2) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `kode_outlet` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_dummy_order_id` (`order_id`),
  KEY `idx_order_items_dummy_kode_outlet` (`kode_outlet`),
  CONSTRAINT `fk_order_items_dummy_order` FOREIGN KEY (`order_id`) REFERENCES `orders_dummy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_promos_dummy` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(50) NOT NULL,
  `promo_id` bigint DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `kode_outlet` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_promos_dummy_order_id` (`order_id`),
  KEY `idx_order_promos_dummy_promo_id` (`promo_id`),
  KEY `idx_order_promos_dummy_kode_outlet` (`kode_outlet`),
  CONSTRAINT `fk_order_promos_dummy_order` FOREIGN KEY (`order_id`) REFERENCES `orders_dummy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_payment_dummy` (
  `id` varchar(50) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `paid_number` varchar(50) DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `payment_code` varchar(50) DEFAULT NULL,
  `bank_id` bigint DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT 0,
  `card_first4` varchar(4) DEFAULT NULL,
  `card_last4` varchar(4) DEFAULT NULL,
  `approval_code` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `kasir` varchar(255) DEFAULT '-',
  `note` text,
  `change` decimal(18,2) DEFAULT 0,
  `kode_outlet` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_payment_dummy_order_id` (`order_id`),
  KEY `idx_order_payment_dummy_paid_number` (`paid_number`),
  KEY `idx_order_payment_dummy_payment_code` (`payment_code`),
  KEY `idx_order_payment_dummy_kode_outlet` (`kode_outlet`),
  KEY `idx_order_payment_dummy_created_at` (`created_at`),
  CONSTRAINT `fk_order_payment_dummy_order` FOREIGN KEY (`order_id`) REFERENCES `orders_dummy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure kode_outlet exists and NOT NULL on dummy sync tables
SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db AND table_name = 'orders_dummy' AND column_name = 'kode_outlet'
    ),
    'UPDATE orders_dummy SET kode_outlet = '''' WHERE kode_outlet IS NULL; ALTER TABLE orders_dummy MODIFY COLUMN kode_outlet VARCHAR(50) NOT NULL;',
    'ALTER TABLE orders_dummy ADD COLUMN kode_outlet VARCHAR(50) NOT NULL DEFAULT '''';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db AND table_name = 'order_items_dummy' AND column_name = 'kode_outlet'
    ),
    'UPDATE order_items_dummy SET kode_outlet = '''' WHERE kode_outlet IS NULL; ALTER TABLE order_items_dummy MODIFY COLUMN kode_outlet VARCHAR(50) NOT NULL;',
    'ALTER TABLE order_items_dummy ADD COLUMN kode_outlet VARCHAR(50) NOT NULL DEFAULT '''';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db AND table_name = 'order_promos_dummy' AND column_name = 'kode_outlet'
    ),
    'UPDATE order_promos_dummy SET kode_outlet = '''' WHERE kode_outlet IS NULL; ALTER TABLE order_promos_dummy MODIFY COLUMN kode_outlet VARCHAR(50) NOT NULL;',
    'ALTER TABLE order_promos_dummy ADD COLUMN kode_outlet VARCHAR(50) NOT NULL DEFAULT '''';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @db AND table_name = 'order_payment_dummy' AND column_name = 'kode_outlet'
    ),
    'UPDATE order_payment_dummy SET kode_outlet = '''' WHERE kode_outlet IS NULL; ALTER TABLE order_payment_dummy MODIFY COLUMN kode_outlet VARCHAR(50) NOT NULL;',
    'ALTER TABLE order_payment_dummy ADD COLUMN kode_outlet VARCHAR(50) NOT NULL DEFAULT '''';'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
