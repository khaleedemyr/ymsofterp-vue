-- Add used_in_outlet_id column to member_apps_member_vouchers table
ALTER TABLE `member_apps_member_vouchers`
ADD COLUMN `used_in_outlet_id` INT NULL AFTER `used_in_transaction_id`;

-- Add index for faster lookup
CREATE INDEX `idx_used_in_outlet_id` ON `member_apps_member_vouchers` (`used_in_outlet_id`);

