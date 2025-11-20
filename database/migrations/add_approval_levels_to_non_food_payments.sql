-- Add approval level tracking columns to non_food_payments table
-- Finance Manager approval (Level 1)
ALTER TABLE `non_food_payments`
ADD COLUMN `approved_finance_manager_by` BIGINT(20) UNSIGNED NULL AFTER `approved_at`,
ADD COLUMN `approved_finance_manager_at` DATETIME NULL AFTER `approved_finance_manager_by`;

-- GM Finance approval (Level 2)
ALTER TABLE `non_food_payments`
ADD COLUMN `approved_gm_finance_by` BIGINT(20) UNSIGNED NULL AFTER `approved_finance_manager_at`,
ADD COLUMN `approved_gm_finance_at` DATETIME NULL AFTER `approved_gm_finance_by`;

-- Add foreign keys
ALTER TABLE `non_food_payments`
ADD CONSTRAINT `fk_non_food_payments_approved_finance_manager_by`
    FOREIGN KEY (`approved_finance_manager_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_non_food_payments_approved_gm_finance_by`
    FOREIGN KEY (`approved_gm_finance_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Add indexes
CREATE INDEX `idx_non_food_payments_approved_finance_manager_by` ON `non_food_payments` (`approved_finance_manager_by`);
CREATE INDEX `idx_non_food_payments_approved_gm_finance_by` ON `non_food_payments` (`approved_gm_finance_by`);

