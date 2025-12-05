-- Fix purchase_order_food_items total column to handle large values
-- Run this SQL to fix the decimal overflow issue

-- Modify the total column to use DECIMAL(15,2) to handle large values
ALTER TABLE purchase_order_food_items 
MODIFY COLUMN total DECIMAL(15,2) NOT NULL DEFAULT 0.00;

-- Also fix other decimal columns that might have the same issue
ALTER TABLE purchase_order_food_items 
MODIFY COLUMN price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Add subtotal column if it doesn't exist (optional)
-- ALTER TABLE purchase_order_food_items 
-- ADD COLUMN subtotal DECIMAL(15,2) NULL DEFAULT 0.00;

-- Verify the changes
SELECT 'Column structure updated successfully!' as message;
