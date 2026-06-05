-- Tambah field pembayaran payroll per fase (Gajian 1 & Gajian 2)
-- Jalankan sekali di MySQL (manual SQL, tanpa migration).

ALTER TABLE `payroll_generated`
  ADD COLUMN `gajian1_paid_at` DATETIME NULL DEFAULT NULL AFTER `gajian2_generated_at`,
  ADD COLUMN `gajian1_paid_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian1_paid_at`,
  ADD COLUMN `gajian1_paid_transfer_bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian1_paid_by`,
  ADD COLUMN `gajian1_paid_cash_bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian1_paid_transfer_bank_account_id`,
  ADD COLUMN `gajian1_paid_no_jurnal` VARCHAR(50) NULL DEFAULT NULL AFTER `gajian1_paid_cash_bank_account_id`,
  ADD COLUMN `gajian2_paid_at` DATETIME NULL DEFAULT NULL AFTER `gajian1_paid_no_jurnal`,
  ADD COLUMN `gajian2_paid_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian2_paid_at`,
  ADD COLUMN `gajian2_paid_transfer_bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian2_paid_by`,
  ADD COLUMN `gajian2_paid_cash_bank_account_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gajian2_paid_transfer_bank_account_id`,
  ADD COLUMN `gajian2_paid_no_jurnal` VARCHAR(50) NULL DEFAULT NULL AFTER `gajian2_paid_cash_bank_account_id`;

