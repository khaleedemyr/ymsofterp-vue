-- Add 'voucher-purchase' to redemption_type enum in member_apps_point_redemptions table
-- Note: MySQL doesn't support direct ALTER ENUM, so we need to recreate the column

ALTER TABLE `member_apps_point_redemptions` 
MODIFY COLUMN `redemption_type` ENUM('product', 'discount-voucher', 'cash', 'voucher-purchase') NOT NULL;

