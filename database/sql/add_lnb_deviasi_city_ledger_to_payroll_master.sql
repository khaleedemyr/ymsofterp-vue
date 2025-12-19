-- Add Deviasi and City Ledger fields to payroll_master table (as DEDUCTION)
ALTER TABLE `payroll_master` 
ADD COLUMN `deviasi` TINYINT(1) DEFAULT 0 AFTER `lb`,
ADD COLUMN `city_ledger` TINYINT(1) DEFAULT 0 AFTER `deviasi`;

