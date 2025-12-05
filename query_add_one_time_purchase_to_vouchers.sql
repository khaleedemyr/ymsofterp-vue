-- Add one_time_purchase field to member_apps_vouchers table
ALTER TABLE `member_apps_vouchers` 
ADD COLUMN `one_time_purchase` TINYINT(1) DEFAULT 0 AFTER `points_required`;

