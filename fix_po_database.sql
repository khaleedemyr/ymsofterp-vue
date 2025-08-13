-- Fix purchase_order_food_items table structure
-- Run this SQL to fix the decimal overflow issue

-- Backup current data (optional)
-- CREATE TABLE purchase_order_food_items_backup AS SELECT * FROM purchase_order_food_items;

-- Modify columns to handle large values
ALTER TABLE purchase_order_food_items 
MODIFY COLUMN total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
MODIFY COLUMN price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- If subtotal column exists, fix it too
-- ALTER TABLE purchase_order_food_items 
-- MODIFY COLUMN subtotal DECIMAL(15,2) NULL DEFAULT 0.00;

-- Verify the changes
SELECT 'Database structure updated successfully!' as message;
