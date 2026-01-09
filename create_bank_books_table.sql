-- Query untuk membuat tabel bank_books
-- Jalankan query ini langsung di database

CREATE TABLE IF NOT EXISTS `bank_books` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` ENUM('debit', 'credit') NOT NULL COMMENT 'debit = keluar, credit = masuk',
  `amount` DECIMAL(15, 2) NOT NULL,
  `description` TEXT NULL,
  `reference_type` VARCHAR(255) NULL COMMENT 'outlet_payment, food_payment, non_food_payment, manual, dll',
  `reference_id` BIGINT UNSIGNED NULL COMMENT 'ID dari transaksi yang direferensikan',
  `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0 COMMENT 'Saldo setelah transaksi',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_bank_account_id` (`bank_account_id`),
  INDEX `idx_transaction_date` (`transaction_date`),
  INDEX `idx_reference` (`reference_type`, `reference_id`),
  INDEX `idx_transaction_type` (`transaction_type`),
  CONSTRAINT `bank_books_bank_account_id_foreign` 
    FOREIGN KEY (`bank_account_id`) 
    REFERENCES `bank_accounts` (`id`) 
    ON DELETE CASCADE,
  CONSTRAINT `bank_books_created_by_foreign` 
    FOREIGN KEY (`created_by`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL,
  CONSTRAINT `bank_books_updated_by_foreign` 
    FOREIGN KEY (`updated_by`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
