-- Add fields for voucher sale feature
ALTER TABLE `member_apps_vouchers` 
ADD COLUMN `is_for_sale` TINYINT(1) DEFAULT 0 AFTER `is_active`,
ADD COLUMN `points_required` INT NULL DEFAULT NULL AFTER `is_for_sale`;

