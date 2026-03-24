-- Self Order Tables (MVP)
-- Jalankan sekali di database YMSoftERP

CREATE TABLE IF NOT EXISTS `self_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(50) NOT NULL,
  `menu_book_id` BIGINT UNSIGNED NOT NULL,
  `outlet_id` INT NOT NULL,
  `kode_outlet` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(30) NULL,
  `order_type` ENUM('dine_in', 'take_away') NOT NULL DEFAULT 'dine_in',
  `notes` VARCHAR(500) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `total_item` INT NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `grand_total` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_self_orders_order_no` (`order_no`),
  KEY `idx_self_orders_menu_book_id` (`menu_book_id`),
  KEY `idx_self_orders_outlet_id` (`outlet_id`),
  KEY `idx_self_orders_kode_outlet` (`kode_outlet`),
  KEY `idx_self_orders_status` (`status`),
  KEY `idx_self_orders_created_at` (`created_at`),
  CONSTRAINT `fk_self_orders_menu_book` FOREIGN KEY (`menu_book_id`) REFERENCES `menu_books` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `self_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `self_order_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `modifiers` LONGTEXT NULL,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_self_order_items_self_order_id` (`self_order_id`),
  KEY `idx_self_order_items_item_id` (`item_id`),
  CONSTRAINT `fk_self_order_items_self_order` FOREIGN KEY (`self_order_id`) REFERENCES `self_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_self_order_items_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backward compatibility: tambah kolom modifiers jika tabel sudah terlanjur dibuat
SET @self_order_items_has_modifiers := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'self_order_items'
    AND COLUMN_NAME = 'modifiers'
);

SET @self_order_items_alter_sql := IF(
  @self_order_items_has_modifiers = 0,
  'ALTER TABLE `self_order_items` ADD COLUMN `modifiers` LONGTEXT NULL AFTER `price`',
  'SELECT 1'
);

PREPARE self_order_items_stmt FROM @self_order_items_alter_sql;
EXECUTE self_order_items_stmt;
DEALLOCATE PREPARE self_order_items_stmt;
