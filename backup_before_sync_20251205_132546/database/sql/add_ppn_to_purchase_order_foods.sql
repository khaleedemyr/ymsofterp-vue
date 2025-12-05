-- Add PPN fields to purchase_order_foods table
ALTER TABLE purchase_order_foods 
ADD COLUMN ppn_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN ppn_amount DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN subtotal DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN grand_total DECIMAL(15,2) DEFAULT 0.00;

-- Update existing records to calculate subtotal and grand_total
UPDATE purchase_order_foods po 
SET subtotal = (
    SELECT COALESCE(SUM(total), 0) 
    FROM purchase_order_food_items 
    WHERE purchase_order_food_id = po.id
),
grand_total = (
    SELECT COALESCE(SUM(total), 0) 
    FROM purchase_order_food_items 
    WHERE purchase_order_food_id = po.id
) + COALESCE(ppn_amount, 0);
