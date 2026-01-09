-- Create pivot table for bank_accounts, payment_types, and outlets
-- This allows payment types to have different bank accounts per outlet
-- Structure: payment_type_id + outlet_id (nullable) + bank_account_id
-- If outlet_id is NULL, it means the bank can be used for all outlets (Head Office)
CREATE TABLE IF NOT EXISTS `bank_account_payment_type` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_type_id` BIGINT UNSIGNED NOT NULL,
    `outlet_id` INT UNSIGNED NULL COMMENT 'NULL means can be used for all outlets (Head Office)',
    `bank_account_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_bank_account_payment_type_payment_type_id` (`payment_type_id`),
    INDEX `idx_bank_account_payment_type_outlet_id` (`outlet_id`),
    INDEX `idx_bank_account_payment_type_bank_account_id` (`bank_account_id`),
    UNIQUE KEY `unique_bank_account_payment_type_outlet` (`payment_type_id`, `outlet_id`, `bank_account_id`),
    CONSTRAINT `fk_bank_account_payment_type_payment_type_id` 
        FOREIGN KEY (`payment_type_id`) 
        REFERENCES `payment_types` (`id`) 
        ON DELETE CASCADE,
    CONSTRAINT `fk_bank_account_payment_type_outlet_id` 
        FOREIGN KEY (`outlet_id`) 
        REFERENCES `tbl_data_outlet` (`id_outlet`) 
        ON DELETE CASCADE,
    CONSTRAINT `fk_bank_account_payment_type_bank_account_id` 
        FOREIGN KEY (`bank_account_id`) 
        REFERENCES `bank_accounts` (`id`) 
        ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
