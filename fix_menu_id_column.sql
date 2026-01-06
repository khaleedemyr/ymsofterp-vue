-- Fix menu_id column name (rename menu_ids to menu_id)
ALTER TABLE `chart_of_accounts` 
CHANGE COLUMN `menu_ids` `menu_id` JSON DEFAULT NULL;

