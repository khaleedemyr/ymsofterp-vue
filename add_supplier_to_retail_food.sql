-- Menambahkan field supplier_id ke tabel retail_food
ALTER TABLE retail_food ADD COLUMN supplier_id INT NULL AFTER payment_method;

-- Menambahkan foreign key constraint
ALTER TABLE retail_food ADD CONSTRAINT fk_retail_food_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id);

-- Menambahkan index untuk optimasi query
CREATE INDEX idx_retail_food_supplier_id ON retail_food(supplier_id);
