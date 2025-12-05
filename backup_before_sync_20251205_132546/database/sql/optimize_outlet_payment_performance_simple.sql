-- Simple optimization for outlet payment performance
-- Only add the most critical indexes

-- Index untuk outlet_payments (most critical)
CREATE INDEX IF NOT EXISTS idx_outlet_payments_gr_id ON outlet_payments(gr_id);
CREATE INDEX IF NOT EXISTS idx_outlet_payments_status ON outlet_payments(status);

-- Index untuk outlet_food_good_receives (most critical)
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_outlet_id ON outlet_food_good_receives(outlet_id);
CREATE INDEX IF NOT EXISTS idx_outlet_food_good_receives_delivery_order_id ON outlet_food_good_receives(delivery_order_id);

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
