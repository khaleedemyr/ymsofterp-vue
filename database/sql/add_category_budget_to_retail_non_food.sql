-- Add category_budget_id column to retail_non_food table
ALTER TABLE `retail_non_food` 
ADD COLUMN `category_budget_id` BIGINT UNSIGNED NULL AFTER `outlet_id`,
ADD INDEX `idx_category_budget_id` (`category_budget_id`);

-- Add foreign key constraint
ALTER TABLE `retail_non_food` 
ADD CONSTRAINT `fk_retail_non_food_category_budget` 
FOREIGN KEY (`category_budget_id`) REFERENCES `purchase_requisition_categories`(`id`) ON DELETE SET NULL;

