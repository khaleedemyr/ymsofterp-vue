-- =====================================================
-- CHALLENGE MANAGEMENT DATABASE STRUCTURE
-- =====================================================

-- 1. Challenge Types Master Table
CREATE TABLE `challenge_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Spending-based, Product-based, Multi-condition, Recurring, Custom',
  `description` text,
  `parameters_config` json COMMENT 'JSON structure for dynamic form fields',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Challenge Items Master Table (for rewards)
CREATE TABLE `challenge_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL COMMENT 'Food, Beverage, Dessert, Voucher, Points',
  `description` text,
  `is_available_for_reward` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `challenge_items_category_index` (`category`),
  KEY `challenge_items_available_index` (`is_available_for_reward`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Challenge Rules Table
CREATE TABLE `challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_type_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `rules` json NOT NULL COMMENT 'Challenge parameters based on type',
  `validity_period_days` int(11) NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `challenges_type_id_foreign` (`challenge_type_id`),
  KEY `challenges_created_by_foreign` (`created_by`),
  KEY `challenges_active_index` (`is_active`),
  KEY `challenges_dates_index` (`start_date`, `end_date`),
  CONSTRAINT `challenges_type_id_foreign` FOREIGN KEY (`challenge_type_id`) REFERENCES `challenge_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenges_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Challenge Outlet Scope Table (which outlets apply to challenge)
CREATE TABLE `challenge_outlets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` bigint(20) unsigned NOT NULL,
  `outlet_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_outlets_unique` (`challenge_id`, `outlet_id`),
  KEY `challenge_outlets_challenge_id_foreign` (`challenge_id`),
  KEY `challenge_outlets_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `challenge_outlets_challenge_id_foreign` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_outlets_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. User Challenge Progress Table
CREATE TABLE `user_challenge_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `challenge_id` bigint(20) unsigned NOT NULL,
  `progress_data` json COMMENT 'Current progress: spending, visits, transactions, etc.',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reward_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `reward_claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_challenge_progress_unique` (`user_id`, `challenge_id`),
  KEY `user_challenge_progress_user_id_foreign` (`user_id`),
  KEY `user_challenge_progress_challenge_id_foreign` (`challenge_id`),
  KEY `user_challenge_progress_completed_index` (`is_completed`),
  CONSTRAINT `user_challenge_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_challenge_progress_challenge_id_foreign` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Challenge Rewards History Table
CREATE TABLE `challenge_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `challenge_id` bigint(20) unsigned NOT NULL,
  `reward_type` enum('item','points','voucher','discount') NOT NULL,
  `reward_value` varchar(255) NOT NULL COMMENT 'Item name, points amount, voucher code, discount percentage',
  `reward_data` json COMMENT 'Additional reward information',
  `claimed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `challenge_rewards_user_id_foreign` (`user_id`),
  KEY `challenge_rewards_challenge_id_foreign` (`challenge_id`),
  KEY `challenge_rewards_type_index` (`reward_type`),
  KEY `challenge_rewards_used_index` (`is_used`),
  CONSTRAINT `challenge_rewards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_rewards_challenge_id_foreign` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT MASTER DATA
-- =====================================================

-- Insert Challenge Types
INSERT INTO `challenge_types` (`name`, `description`, `parameters_config`) VALUES
('Spending-based', 'User must spend minimum amount to get reward', '{"fields": ["min_amount", "reward_type", "reward_value", "immediate"]}'),
('Product-based', 'User must try specific products to get reward', '{"fields": ["product_category", "quantity_required", "reward_type", "reward_value"]}'),
('Multi-condition', 'User must meet multiple conditions (spending + visits + transactions)', '{"fields": ["min_spending", "min_transactions", "min_visits", "reward_type", "reward_value"]}'),
('Recurring', 'High-tier challenge with ongoing benefits', '{"fields": ["high_spending", "visit_requirements", "recurring_benefit", "frequency", "duration"]}'),
('Custom', 'Flexible challenge with custom parameters', '{"fields": ["custom_conditions", "reward_type", "reward_value"]}');

-- Insert Sample Challenge Items
INSERT INTO `challenge_items` (`name`, `category`, `description`, `is_available_for_reward`) VALUES
('Iced Coffee', 'Beverage', 'Free iced coffee drink', 1),
('Mocktail', 'Beverage', 'Free mocktail drink', 1),
('Steak', 'Food', 'Free steak dish', 1),
('Dessert', 'Dessert', 'Free dessert item', 1),
('Voucher IDR 100.000', 'Voucher', 'Cash voucher worth IDR 100.000', 1),
('Voucher IDR 50.000', 'Voucher', 'Cash voucher worth IDR 50.000', 1),
('Points 100', 'Points', '100 loyalty points', 1),
('Points 200', 'Points', '200 loyalty points', 1),
('Discount 50%', 'Discount', '50% discount on next purchase', 1),
('FREE Dishes Weekly', 'Recurring', 'Free dishes every week for a month', 1);

-- =====================================================
-- SAMPLE CHALLENGE DATA
-- =====================================================

-- Sample Challenge 1: Spending IDR 300.000 and Get Free Iced Coffee/Mocktail
INSERT INTO `challenges` (`challenge_type_id`, `name`, `description`, `rules`, `validity_period_days`, `is_active`) VALUES
(1, 'Spending IDR 300.000 and Get Free Iced Coffee/Mocktail', 'Spend minimum IDR 300.000 to get free iced coffee or mocktail', 
'{"min_amount": 300000, "reward_type": "item", "reward_options": ["Iced Coffee", "Mocktail"], "immediate": true, "before_tax": true}', 
30, 1);

-- Sample Challenge 2: Try Any 2 New Product Development
INSERT INTO `challenges` (`challenge_type_id`, `name`, `description`, `rules`, `validity_period_days`, `is_active`) VALUES
(2, 'Try Any 2 New Product Development', 'Try 2 new product development items to get 100 points', 
'{"product_category": "New Product Development", "quantity_required": 2, "reward_type": "points", "reward_value": 100}', 
30, 1);

-- Sample Challenge 3: Multi-condition Challenge
INSERT INTO `challenges` (`challenge_type_id`, `name`, `description`, `rules`, `validity_period_days`, `is_active`) VALUES
(3, '2x Transaction and total spending IDR 1.000.000 Get Justus Voucher 100.000', 'Make 2 transactions with total spending IDR 1.000.000 to get voucher', 
'{"min_spending": 1000000, "min_transactions": 2, "min_visits": 2, "reward_type": "voucher", "reward_value": "IDR 100.000"}', 
30, 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for better performance
CREATE INDEX `challenges_active_dates_index` ON `challenges` (`is_active`, `start_date`, `end_date`);
CREATE INDEX `user_challenge_progress_user_completed_index` ON `user_challenge_progress` (`user_id`, `is_completed`);
CREATE INDEX `challenge_rewards_user_type_index` ON `challenge_rewards` (`user_id`, `reward_type`, `is_used`);
CREATE INDEX `challenge_rewards_expires_index` ON `challenge_rewards` (`expires_at`, `is_used`);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- View for active challenges with progress
CREATE VIEW `active_challenges_view` AS
SELECT 
    c.id,
    c.name,
    c.description,
    ct.name as challenge_type,
    c.rules,
    c.validity_period_days,
    c.start_date,
    c.end_date,
    COUNT(ucp.id) as total_participants,
    COUNT(CASE WHEN ucp.is_completed = 1 THEN 1 END) as completed_count,
    COUNT(CASE WHEN ucp.reward_claimed = 1 THEN 1 END) as claimed_count
FROM challenges c
JOIN challenge_types ct ON c.challenge_type_id = ct.id
LEFT JOIN user_challenge_progress ucp ON c.id = ucp.challenge_id
WHERE c.is_active = 1
GROUP BY c.id, c.name, c.description, ct.name, c.rules, c.validity_period_days, c.start_date, c.end_date;

-- View for user challenge summary
CREATE VIEW `user_challenge_summary` AS
SELECT 
    u.id as user_id,
    u.nama_lengkap as user_name,
    c.id as challenge_id,
    c.name as challenge_name,
    ucp.progress_data,
    ucp.is_completed,
    ucp.completed_at,
    ucp.reward_claimed,
    ucp.reward_claimed_at,
    cr.reward_type,
    cr.reward_value,
    cr.expires_at
FROM users u
JOIN user_challenge_progress ucp ON u.id = ucp.user_id
JOIN challenges c ON ucp.challenge_id = c.id
LEFT JOIN challenge_rewards cr ON u.id = cr.user_id AND c.id = cr.challenge_id
WHERE c.is_active = 1;
