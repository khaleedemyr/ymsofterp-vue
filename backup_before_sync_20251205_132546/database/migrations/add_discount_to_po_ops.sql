-- Add discount fields to purchase_order_ops_items table (discount per item)
ALTER TABLE `purchase_order_ops_items` 
ADD COLUMN `discount_percent` DECIMAL(5,2) NULL DEFAULT 0.00 AFTER `price`,
ADD COLUMN `discount_amount` DECIMAL(15,2) NULL DEFAULT 0.00 AFTER `discount_percent`;

-- Add discount total fields to purchase_order_ops table
ALTER TABLE `purchase_order_ops` 
ADD COLUMN `discount_total_percent` DECIMAL(5,2) NULL DEFAULT 0.00 AFTER `subtotal`,
ADD COLUMN `discount_total_amount` DECIMAL(15,2) NULL DEFAULT 0.00 AFTER `discount_total_percent`;

