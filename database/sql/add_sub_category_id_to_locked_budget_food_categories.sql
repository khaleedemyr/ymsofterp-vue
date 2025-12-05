-- Add sub_category_id column to existing locked_budget_food_categories table
ALTER TABLE `locked_budget_food_categories` 
ADD COLUMN `sub_category_id` bigint(20) UNSIGNED NOT NULL AFTER `category_id`;

-- Add foreign key constraint for sub_category_id
ALTER TABLE `locked_budget_food_categories` 
ADD CONSTRAINT `locked_budget_food_categories_sub_category_id_foreign` 
FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`id`) ON DELETE CASCADE;

-- Add index for sub_category_id
ALTER TABLE `locked_budget_food_categories` 
ADD KEY `locked_budget_food_categories_sub_category_id_foreign` (`sub_category_id`);

-- Drop old unique constraint
ALTER TABLE `locked_budget_food_categories` 
DROP INDEX `locked_budget_food_categories_category_outlet_unique`;

-- Add new unique constraint including sub_category_id
ALTER TABLE `locked_budget_food_categories` 
ADD UNIQUE KEY `locked_budget_food_categories_category_sub_outlet_unique` (`category_id`, `sub_category_id`, `outlet_id`);
