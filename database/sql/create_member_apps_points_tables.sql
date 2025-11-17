-- Create table for Member Point Transactions (Main transaction log)
CREATE TABLE IF NOT EXISTS `member_apps_point_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_type` enum('earn', 'redeem', 'expired', 'bonus', 'adjustment') NOT NULL,
  `transaction_date` date NOT NULL,
  `point_amount` int(11) NOT NULL COMMENT 'Positive for earn/bonus, negative for redeem/expired',
  `transaction_amount` decimal(15,2) NULL DEFAULT NULL COMMENT 'Amount in Rupiah for earning transactions',
  `earning_rate` decimal(5,2) NULL DEFAULT NULL COMMENT 'Point earning rate (1.0, 1.5, 2.0)',
  `channel` enum('dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment') NULL DEFAULT NULL,
  `reference_id` varchar(255) NULL DEFAULT NULL COMMENT 'Reference to order/transaction ID',
  `description` text NULL DEFAULT NULL,
  `expires_at` date NULL DEFAULT NULL COMMENT 'Expiry date for earned points (1 year from transaction)',
  `is_expired` tinyint(1) NOT NULL DEFAULT 0,
  `expired_at` timestamp NULL DEFAULT NULL COMMENT 'When the point actually expired',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_is_expired` (`is_expired`),
  KEY `idx_channel` (`channel`),
  CONSTRAINT `fk_point_transaction_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Member Point Earnings (Detail earning dengan FIFO tracking)
CREATE TABLE IF NOT EXISTS `member_apps_point_earnings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `point_transaction_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Reference to point_transactions',
  `point_amount` int(11) NOT NULL COMMENT 'Original point amount earned',
  `remaining_points` int(11) NOT NULL COMMENT 'Remaining points after redemption',
  `earned_at` date NOT NULL COMMENT 'Date when point was earned',
  `expires_at` date NOT NULL COMMENT 'Expiry date (1 year from earned_at)',
  `is_expired` tinyint(1) NOT NULL DEFAULT 0,
  `expired_at` timestamp NULL DEFAULT NULL,
  `is_fully_redeemed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True if all points from this earning are redeemed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_point_transaction_id` (`point_transaction_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_is_expired` (`is_expired`),
  KEY `idx_is_fully_redeemed` (`is_fully_redeemed`),
  KEY `idx_earned_at` (`earned_at`),
  CONSTRAINT `fk_point_earning_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_point_earning_transaction` FOREIGN KEY (`point_transaction_id`) REFERENCES `member_apps_point_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Member Point Redemptions
CREATE TABLE IF NOT EXISTS `member_apps_point_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `point_transaction_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Reference to point_transactions',
  `redemption_type` enum('product', 'discount-voucher', 'cash') NOT NULL,
  `redemption_date` date NOT NULL,
  `point_amount` int(11) NOT NULL COMMENT 'Points used for redemption',
  `cash_value` decimal(15,2) NULL DEFAULT NULL COMMENT 'Cash value (point_amount * 250)',
  `product_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'If redemption_type = product',
  `product_name` varchar(255) NULL DEFAULT NULL,
  `product_price` decimal(15,2) NULL DEFAULT NULL COMMENT 'Product price in cash',
  `discount_voucher_type` enum('10%', '12.5%', '15%', '20%') NULL DEFAULT NULL COMMENT 'If redemption_type = discount-voucher',
  `discount_voucher_points` int(11) NULL DEFAULT NULL COMMENT 'Points used for discount voucher',
  `discount_voucher_code` varchar(255) NULL DEFAULT NULL COMMENT 'Generated voucher code',
  `discount_voucher_expires_at` date NULL DEFAULT NULL,
  `discount_voucher_used_at` timestamp NULL DEFAULT NULL,
  `reference_id` varchar(255) NULL DEFAULT NULL COMMENT 'Reference to order/transaction ID',
  `status` enum('pending', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_point_transaction_id` (`point_transaction_id`),
  KEY `idx_redemption_type` (`redemption_type`),
  KEY `idx_redemption_date` (`redemption_date`),
  KEY `idx_status` (`status`),
  KEY `idx_discount_voucher_code` (`discount_voucher_code`),
  CONSTRAINT `fk_point_redemption_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_point_redemption_transaction` FOREIGN KEY (`point_transaction_id`) REFERENCES `member_apps_point_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Point Redemption Details (FIFO tracking - which earning was used)
CREATE TABLE IF NOT EXISTS `member_apps_point_redemption_details` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `redemption_id` bigint(20) UNSIGNED NOT NULL,
  `point_earning_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Which earning was used (FIFO)',
  `point_amount` int(11) NOT NULL COMMENT 'Points taken from this earning',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_redemption_id` (`redemption_id`),
  KEY `idx_point_earning_id` (`point_earning_id`),
  CONSTRAINT `fk_redemption_detail_redemption` FOREIGN KEY (`redemption_id`) REFERENCES `member_apps_point_redemptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_redemption_detail_earning` FOREIGN KEY (`point_earning_id`) REFERENCES `member_apps_point_earnings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Point Campaigns (Double points day, etc)
CREATE TABLE IF NOT EXISTS `member_apps_point_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NULL DEFAULT NULL,
  `campaign_type` enum('double-points', 'bonus-points', 'multiplier') NOT NULL,
  `multiplier` decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Point multiplier (2.0 for double points)',
  `bonus_points` int(11) NULL DEFAULT NULL COMMENT 'Fixed bonus points if campaign_type = bonus-points',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NULL DEFAULT NULL COMMENT 'Start time for daily campaigns',
  `end_time` time NULL DEFAULT NULL COMMENT 'End time for daily campaigns',
  `applicable_channels` json NULL DEFAULT NULL COMMENT 'Array of channels: ["dine-in", "take-away", "delivery-restaurant"]',
  `applicable_member_levels` json NULL DEFAULT NULL COMMENT 'Array of levels: ["Silver", "Loyal", "Elite", "Prestige"]',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_type` (`campaign_type`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for Member Tier History (Track tier changes)
CREATE TABLE IF NOT EXISTS `member_apps_tier_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `old_tier` enum('Silver', 'Loyal', 'Elite', 'Prestige') NULL DEFAULT NULL,
  `new_tier` enum('Silver', 'Loyal', 'Elite', 'Prestige') NOT NULL,
  `total_spending` decimal(15,2) NOT NULL COMMENT 'Total spending at time of tier change',
  `spending_period_start` date NOT NULL COMMENT 'Start date of 12-month rolling period',
  `spending_period_end` date NOT NULL COMMENT 'End date of 12-month rolling period',
  `change_reason` enum('upgrade', 'downgrade', 'initial', 'reset') NOT NULL,
  `changed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_new_tier` (`new_tier`),
  KEY `idx_changed_at` (`changed_at`),
  CONSTRAINT `fk_tier_history_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

