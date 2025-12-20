-- Add supplier_id and payment_method columns to retail_non_food table
ALTER TABLE `retail_non_food` 
ADD COLUMN `supplier_id` BIGINT(20) UNSIGNED NULL AFTER `category_budget_id`,
ADD COLUMN `payment_method` ENUM('cash', 'contra_bon') NULL DEFAULT 'cash' AFTER `supplier_id`,
ADD INDEX `idx_retail_non_food_supplier_id` (`supplier_id`);

-- Add foreign key constraint if suppliers table exists
-- ALTER TABLE `retail_non_food` 
-- ADD CONSTRAINT `fk_retail_non_food_supplier` 
-- FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;
