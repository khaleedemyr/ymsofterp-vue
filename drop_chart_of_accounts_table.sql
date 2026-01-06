-- Drop chart_of_accounts table and all its constraints
-- WARNING: This will delete all data in the table!

-- Drop foreign key constraint first
ALTER TABLE `chart_of_accounts` 
DROP FOREIGN KEY IF EXISTS `chart_of_accounts_parent_id_foreign`;

-- Drop the table
DROP TABLE IF EXISTS `chart_of_accounts`;

