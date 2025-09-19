-- Optimize Outlet Payment Performance
-- Add database indexes to improve query performance

-- Index for outlet_payments table
CREATE INDEX IF NOT EXISTS idx_outlet_payments_gr_id_status ON outlet_payments(gr_id, status);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_outlet_id ON outlet_payments(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_date ON outlet_payments(date);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_status ON outlet_payments(status);

-- Index for outlet_food_good_receives table
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_outlet_id ON outlet_food_good_receives(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_delivery_order_id ON outlet_food_good_receives(delivery_order_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_receive_date ON outlet_food_good_receives(receive_date);

-- Index for outlet_food_good_receive_items table
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_gr_id ON outlet_food_good_receive_items(outlet_food_good_receive_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_item_id ON outlet_food_good_receive_items(item_id);

-- Index for delivery_orders table
CREATE INDEX IF NOT EXISTS idx_delivery_orders_floor_order_id ON delivery_orders(floor_order_id);

-- Index for food_floor_order_items table
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_floor_order_id_item_id ON food_floor_order_items(floor_order_id, item_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_item_id ON food_floor_order_items(item_id);

-- Index for tbl_data_outlet table
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_status ON tbl_data_outlet(status);

-- Index for items table
CREATE INDEX IF NOT EXISTS idx_items_name ON items(name);

-- Index for units table
CREATE INDEX IF NOT EXISTS idx_units_name ON units(name);
