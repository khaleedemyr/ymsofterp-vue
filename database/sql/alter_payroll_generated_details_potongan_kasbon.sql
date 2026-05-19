-- Kolom potongan kasbon di payroll_generated_details
-- Setara migration opsional; jalankan sekali di MySQL

ALTER TABLE `payroll_generated_details`
  ADD COLUMN `potongan_kasbon` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `potongan_unpaid_leave`,
  ADD COLUMN `pr_kasbon_id` bigint unsigned NULL DEFAULT NULL AFTER `potongan_kasbon`,
  ADD COLUMN `kasbon_cicilan_ke` tinyint unsigned NULL DEFAULT NULL AFTER `pr_kasbon_id`;
