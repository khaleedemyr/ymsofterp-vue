-- Add retail_non_food_id column to non_food_payments table
ALTER TABLE `non_food_payments` 
ADD COLUMN `retail_non_food_id` BIGINT(20) UNSIGNED NULL AFTER `purchase_requisition_id`,
ADD INDEX `idx_non_food_payments_retail_non_food_id` (`retail_non_food_id`);

