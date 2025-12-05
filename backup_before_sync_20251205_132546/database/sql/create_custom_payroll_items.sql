-- Tabel untuk custom payroll earnings dan deductions
CREATE TABLE `custom_payroll_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `payroll_period_month` int(2) NOT NULL,
  `payroll_period_year` int(4) NOT NULL,
  `item_type` enum('earn','deduction') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `item_description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_outlet_period` (`user_id`, `outlet_id`, `payroll_period_month`, `payroll_period_year`),
  KEY `idx_item_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
