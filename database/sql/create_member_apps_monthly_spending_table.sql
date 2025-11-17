-- Create table for tracking monthly spending per member
-- This is used for rolling 12-month window tier calculation
CREATE TABLE IF NOT EXISTS `member_apps_monthly_spending` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `year` int(4) NOT NULL COMMENT 'Year (e.g., 2024)',
  `month` tinyint(2) NOT NULL COMMENT 'Month (1-12)',
  `total_spending` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total spending for this month',
  `transaction_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of transactions in this month',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member_year_month` (`member_id`, `year`, `month`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_year_month` (`year`, `month`),
  KEY `idx_member_year_month` (`member_id`, `year`, `month`),
  CONSTRAINT `fk_monthly_spending_member` FOREIGN KEY (`member_id`) REFERENCES `member_apps_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monthly spending tracking for rolling 12-month tier calculation';

