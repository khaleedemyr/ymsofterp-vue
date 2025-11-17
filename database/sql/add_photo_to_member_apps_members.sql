-- Add photo column to member_apps_members table
ALTER TABLE `member_apps_members`
ADD COLUMN `photo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Profile photo path' AFTER `member_id`;

