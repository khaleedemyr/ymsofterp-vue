-- Add image column to member_apps_vouchers table
ALTER TABLE `member_apps_vouchers` 
ADD COLUMN `image` varchar(255) NULL DEFAULT NULL COMMENT 'Voucher image/banner' 
AFTER `exclude_categories`;

