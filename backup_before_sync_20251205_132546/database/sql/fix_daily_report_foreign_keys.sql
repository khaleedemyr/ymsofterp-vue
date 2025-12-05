-- Fix foreign key reference for daily_reports table
-- Drop existing foreign key constraint
ALTER TABLE `daily_reports` DROP FOREIGN KEY `daily_reports_outlet_id_foreign`;

-- Add correct foreign key constraint
ALTER TABLE `daily_reports` 
ADD CONSTRAINT `daily_reports_outlet_id_foreign` 
FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet`(`id_outlet`);
