-- Add coa_id column to bank_accounts table
-- This links each bank account to a Chart of Account entry
-- Run this only if the column doesn't exist yet

-- Check if column exists before adding (manual check required)
-- If column already exists, skip this ALTER TABLE statement
ALTER TABLE `bank_accounts`
ADD COLUMN `coa_id` BIGINT UNSIGNED NULL
AFTER `outlet_id`;

-- Add foreign key constraint
ALTER TABLE `bank_accounts`
ADD CONSTRAINT `bank_accounts_coa_id_foreign`
FOREIGN KEY (`coa_id`)
REFERENCES `chart_of_accounts` (`id`)
ON DELETE SET NULL;

-- Add index for performance
ALTER TABLE `bank_accounts`
ADD INDEX `idx_bank_accounts_coa_id` (`coa_id`);
