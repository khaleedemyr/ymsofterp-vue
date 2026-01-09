-- Add bank_id column to order_payment table
-- This will store the bank account ID for bank-related payment types
-- Run this only if the column doesn't exist yet

-- Check if column exists before adding (manual check required)
-- If column already exists, skip this ALTER TABLE statement
ALTER TABLE `order_payment`
ADD COLUMN `bank_id` BIGINT UNSIGNED NULL
AFTER `payment_code`;

-- Add foreign key constraint (drop first if exists to avoid error)
-- ALTER TABLE `order_payment` DROP FOREIGN KEY IF EXISTS `order_payment_bank_id_foreign`;
ALTER TABLE `order_payment`
ADD CONSTRAINT `order_payment_bank_id_foreign`
FOREIGN KEY (`bank_id`)
REFERENCES `bank_accounts` (`id`)
ON DELETE SET NULL;

-- Add index for performance (drop first if exists to avoid error)
-- ALTER TABLE `order_payment` DROP INDEX IF EXISTS `idx_order_payment_bank_id`;
ALTER TABLE `order_payment`
ADD INDEX `idx_order_payment_bank_id` (`bank_id`);
