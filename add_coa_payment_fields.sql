-- Add payment-related fields to chart_of_accounts table
ALTER TABLE `chart_of_accounts` 
ADD COLUMN `show_in_menu_payment` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_active`,
ADD COLUMN `static_or_dynamic` varchar(20) DEFAULT NULL AFTER `show_in_menu_payment`,
ADD COLUMN `menu_id` int(11) DEFAULT NULL AFTER `static_or_dynamic`,
ADD COLUMN `mode_payment` varchar(50) DEFAULT NULL AFTER `menu_id`,
ADD COLUMN `budget_limit` decimal(15,2) DEFAULT NULL AFTER `mode_payment`;

-- Add foreign key constraint for menu_id
ALTER TABLE `chart_of_accounts` 
ADD CONSTRAINT `chart_of_accounts_menu_id_foreign` 
FOREIGN KEY (`menu_id`) REFERENCES `erp_menu` (`id`) ON DELETE SET NULL;

-- Add index for better query performance
CREATE INDEX `idx_show_in_menu_payment` ON `chart_of_accounts` (`show_in_menu_payment`);
CREATE INDEX `idx_mode_payment` ON `chart_of_accounts` (`mode_payment`);
CREATE INDEX `idx_menu_id` ON `chart_of_accounts` (`menu_id`);

