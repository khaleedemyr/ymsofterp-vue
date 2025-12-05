-- Add redeemed_outlet_id column to member_apps_challenge_progress table
ALTER TABLE `member_apps_challenge_progress`
ADD COLUMN `redeemed_outlet_id` INT NULL AFTER `reward_redeemed_at`;

