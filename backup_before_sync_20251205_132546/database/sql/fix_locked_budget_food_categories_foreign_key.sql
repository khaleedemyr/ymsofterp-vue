-- Fix foreign key constraint for locked_budget_food_categories table
-- Change from lms_categories to categories table

-- Drop existing foreign key constraint
ALTER TABLE `locked_budget_food_categories` 
DROP FOREIGN KEY `locked_budget_food_categories_category_id_foreign`;

-- Add new foreign key constraint pointing to categories table
ALTER TABLE `locked_budget_food_categories` 
ADD CONSTRAINT `locked_budget_food_categories_category_id_foreign` 
FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
