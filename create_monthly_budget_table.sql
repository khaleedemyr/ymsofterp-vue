-- =====================================================
-- CREATE TABLE FOR MONTHLY BUDGET
-- =====================================================

CREATE TABLE IF NOT EXISTS `outlet_monthly_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_qr_code` varchar(255) NOT NULL COMMENT 'QR Code outlet dari tbl_data_outlet',
  `month` int(2) NOT NULL COMMENT 'Bulan (1-12)',
  `year` int(4) NOT NULL COMMENT 'Tahun',
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Budget bulanan dalam rupiah',
  `created_by` bigint(20) unsigned NOT NULL COMMENT 'User yang membuat budget',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_monthly_budgets_unique` (`outlet_qr_code`, `month`, `year`),
  KEY `outlet_monthly_budgets_outlet_qr_code_index` (`outlet_qr_code`),
  KEY `outlet_monthly_budgets_month_year_index` (`month`, `year`),
  KEY `outlet_monthly_budgets_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel untuk menyimpan budget bulanan outlet'; 