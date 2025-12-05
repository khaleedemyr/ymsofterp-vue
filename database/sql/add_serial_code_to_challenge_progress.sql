-- Add serial_code column to member_apps_challenge_progress table
ALTER TABLE `member_apps_challenge_progress` 
ADD COLUMN `serial_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Serial code generated when reward is claimed' 
AFTER `reward_expires_at`;

