-- Add reward_redeemed_at column to member_apps_challenge_progress table
-- This field indicates when reward was redeemed at POS (different from reward_claimed_at which is when user claimed the reward)

ALTER TABLE `member_apps_challenge_progress`
ADD COLUMN `reward_redeemed_at` timestamp NULL DEFAULT NULL COMMENT 'When reward was redeemed at POS' AFTER `reward_claimed_at`;

