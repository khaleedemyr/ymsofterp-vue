-- Add PH Bonus field to payroll_generated_details table
ALTER TABLE `payroll_generated_details` 
ADD COLUMN `ph_bonus` DECIMAL(15, 2) DEFAULT 0 AFTER `city_ledger_total`;

