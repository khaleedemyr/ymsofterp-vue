-- Add L & B, Deviasi, and City Ledger fields to payroll_generated table
ALTER TABLE `payroll_generated` 
ADD COLUMN `lb_amount` DECIMAL(15, 2) DEFAULT 0 AFTER `service_charge`,
ADD COLUMN `deviasi_amount` DECIMAL(15, 2) DEFAULT 0 AFTER `lb_amount`,
ADD COLUMN `city_ledger_amount` DECIMAL(15, 2) DEFAULT 0 AFTER `deviasi_amount`;

-- Add L & B, Deviasi, and City Ledger fields to payroll_generated_details table (if exists)
ALTER TABLE `payroll_generated_details` 
ADD COLUMN `lb_total` DECIMAL(15, 2) DEFAULT 0 AFTER `bpjs_tk`,
ADD COLUMN `deviasi_total` DECIMAL(15, 2) DEFAULT 0 AFTER `lb_total`,
ADD COLUMN `city_ledger_total` DECIMAL(15, 2) DEFAULT 0 AFTER `deviasi_total`;

