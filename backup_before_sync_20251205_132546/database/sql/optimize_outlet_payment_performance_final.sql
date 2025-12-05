-- Final optimization for outlet payment performance
-- Run this SQL to add critical indexes for better performance

-- Index untuk outlet_payments (most critical)
CREATE INDEX IF NOT EXISTS idx_outlet_payments_gr_id ON outlet_payments(gr_id);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_status ON outlet_payments(status);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_outlet_id ON outlet_payments(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_date ON outlet_payments(date);

-- Index untuk outlet_food_good_receives (most critical)
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_outlet_id ON outlet_food_good_receives(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_delivery_order_id ON outlet_food_good_receives(delivery_order_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_receive_date ON outlet_food_good_receives(receive_date);

-- Index untuk outlet_food_good_receive_items (most critical)
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_gr_id ON outlet_food_good_receive_items(outlet_food_good_receive_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receive_items_item_id ON outlet_food_good_receive_items(item_id);

-- Index untuk delivery_orders (most critical)
CREATE INDEX IF NOT EXISTS idx_delivery_orders_floor_order_id ON delivery_orders(floor_order_id);

-- Index untuk food_floor_order_items (most critical)
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_floor_order_id ON food_floor_order_items(floor_order_id);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_item_id ON food_floor_order_items(item_id);

-- Index untuk tbl_data_outlet (most critical)
CREATE INDEX IF NOT EXISTS idx_tbl_data_outlet_status ON tbl_data_outlet(status);

-- Composite index for better performance
CREATE INDEX IF NOT EXISTS idx_outlet_payments_gr_status ON outlet_payments(gr_id, status);
CREATE INDEX IF NOT EXISTS idx_food_floor_order_items_composite ON food_floor_order_items(floor_order_id, item_id);
