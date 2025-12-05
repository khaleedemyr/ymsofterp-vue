-- Fix purchase_order_food_items total column to handle large values
-- The current total column is decimal without precision/scale which causes overflow

-- First, let's check the current structure
DESCRIBE purchase_order_food_items;

-- Modify the total column to use DECIMAL(15,2) to handle large values
ALTER TABLE purchase_order_food_items 
MODIFY COLUMN total DECIMAL(15,2) NOT NULL DEFAULT 0.00;

-- Also fix other decimal columns that might have the same issue
ALTER TABLE purchase_order_food_items 
MODIFY COLUMN price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
MODIFY COLUMN subtotal DECIMAL(15,2) NULL DEFAULT 0.00;

-- Verify the changes
DESCRIBE purchase_order_food_items;

-- Show sample data to verify
SELECT id, item_id, quantity, price, total FROM purchase_order_food_items LIMIT 5;
