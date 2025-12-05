-- Add receive_date column to outlet_food_good_receive_items table
ALTER TABLE outlet_food_good_receive_items ADD COLUMN receive_date DATE NULL AFTER remaining_qty;

-- Update existing records to use the receive_date from the parent good receive
UPDATE outlet_food_good_receive_items 
SET receive_date = (
    SELECT gr.receive_date 
    FROM outlet_food_good_receives gr 
    WHERE gr.id = outlet_food_good_receive_items.outlet_food_good_receive_id
)
WHERE receive_date IS NULL;
