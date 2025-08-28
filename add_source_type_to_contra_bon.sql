-- Menambahkan field source_type dan source_id ke tabel food_contra_bons
ALTER TABLE food_contra_bons ADD COLUMN source_type ENUM('purchase_order', 'retail_food') NOT NULL DEFAULT 'purchase_order' AFTER supplier_invoice_number;
ALTER TABLE food_contra_bons ADD COLUMN source_id INT NULL AFTER source_type;

-- Menambahkan index untuk optimasi query
CREATE INDEX idx_food_contra_bons_source_type ON food_contra_bons(source_type);
CREATE INDEX idx_food_contra_bons_source_id ON food_contra_bons(source_id);
