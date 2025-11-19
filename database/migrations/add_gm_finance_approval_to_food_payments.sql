-- Add GM Finance approval fields to food_payments table
ALTER TABLE `food_payments` 
ADD COLUMN `gm_finance_approved_at` DATETIME NULL AFTER `finance_manager_note`,
ADD COLUMN `gm_finance_approved_by` BIGINT UNSIGNED NULL AFTER `gm_finance_approved_at`,
ADD COLUMN `gm_finance_note` TEXT NULL AFTER `gm_finance_approved_by`;

-- Add foreign key constraint for gm_finance_approved_by
ALTER TABLE `food_payments` 
ADD CONSTRAINT `food_payments_gm_finance_approved_by_foreign` 
FOREIGN KEY (`gm_finance_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

