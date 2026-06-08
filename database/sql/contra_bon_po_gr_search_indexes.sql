-- Index untuk percepat pencarian PO/GR di form Contra Bon
-- Jalankan sekali di database production (maintenance window disarankan)

CREATE INDEX IF NOT EXISTS idx_fgri_good_receive_id ON food_good_receive_items(good_receive_id);
CREATE INDEX IF NOT EXISTS idx_fcbi_gr_item_id ON food_contra_bon_items(gr_item_id);
CREATE INDEX IF NOT EXISTS idx_fgr_po_id ON food_good_receives(po_id);
CREATE INDEX IF NOT EXISTS idx_fgr_receive_date ON food_good_receives(receive_date);
CREATE INDEX IF NOT EXISTS idx_pof_supplier_id ON purchase_order_foods(supplier_id);
CREATE INDEX IF NOT EXISTS idx_pof_number ON purchase_order_foods(number);
CREATE INDEX IF NOT EXISTS idx_fgr_gr_number ON food_good_receives(gr_number);
CREATE INDEX IF NOT EXISTS idx_poi_pof_id ON purchase_order_food_items(purchase_order_food_id);
CREATE INDEX IF NOT EXISTS idx_poi_ro_id ON purchase_order_food_items(ro_id);
