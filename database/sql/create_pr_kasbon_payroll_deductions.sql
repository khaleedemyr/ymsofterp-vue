-- Catatan potongan kasbon per generate payroll (idempotent saat regenerate periode sama)
-- Jalankan setelah create_pr_kasbons.sql

CREATE TABLE IF NOT EXISTS `pr_kasbon_payroll_deductions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pr_kasbon_id` bigint unsigned NOT NULL,
  `payroll_generated_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `installment_number` tinyint unsigned NOT NULL COMMENT 'cicilan ke-N (1..termin)',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_kasbon_payroll_deductions_payroll_kasbon_unique` (`payroll_generated_id`, `pr_kasbon_id`),
  KEY `pr_kasbon_payroll_deductions_user_index` (`user_id`),
  KEY `pr_kasbon_payroll_deductions_kasbon_index` (`pr_kasbon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
