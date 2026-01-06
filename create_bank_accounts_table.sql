-- Create table for bank accounts master data
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_name` VARCHAR(255) NOT NULL COMMENT 'Nama Bank',
  `account_number` VARCHAR(100) NOT NULL COMMENT 'Nomor Rekening',
  `account_name` VARCHAR(255) NOT NULL COMMENT 'Nama Pemilik Rekening',
  `outlet_id` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'ID Outlet (NULL untuk semua outlet)',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status Aktif (1=Active, 0=Inactive)',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_outlet_id` (`outlet_id`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_bank_accounts_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master Data Bank Accounts';

