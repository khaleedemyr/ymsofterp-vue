-- Add serial_code column to member_apps_rewards table
ALTER TABLE `member_apps_rewards` 
ADD COLUMN `serial_code` VARCHAR(50) NULL DEFAULT NULL AFTER `is_active`,
ADD UNIQUE KEY `unique_serial_code` (`serial_code`);

