-- Add challenge_type_id and validity_period_days columns to member_apps_challenges table

-- Add challenge_type_id column (string/varchar to store challenge type identifier)
ALTER TABLE `member_apps_challenges`
ADD COLUMN `challenge_type_id` VARCHAR(50) NULL DEFAULT NULL AFTER `description`;

-- Add validity_period_days column
ALTER TABLE `member_apps_challenges`
ADD COLUMN `validity_period_days` INT(11) NULL DEFAULT NULL AFTER `challenge_type_id`;

-- Add index for challenge_type_id if needed for performance
ALTER TABLE `member_apps_challenges`
ADD INDEX `idx_challenge_type_id` (`challenge_type_id`);

