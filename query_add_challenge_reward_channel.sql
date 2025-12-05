-- Add 'challenge_reward' to channel enum in member_apps_point_transactions table
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `channel` ENUM('dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment', 'voucher-purchase', 'challenge_reward') NULL DEFAULT NULL;

