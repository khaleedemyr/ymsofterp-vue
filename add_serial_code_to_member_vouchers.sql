-- Add serial_code column to member_apps_member_vouchers table
ALTER TABLE `member_apps_member_vouchers`
ADD COLUMN `serial_code` VARCHAR(50) NULL UNIQUE AFTER `voucher_code`;

-- Add index for faster lookup
CREATE INDEX `idx_serial_code` ON `member_apps_member_vouchers` (`serial_code`);

