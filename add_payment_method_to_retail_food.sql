-- Menambahkan field payment_method ke tabel retail_food
ALTER TABLE retail_food ADD COLUMN payment_method ENUM('cash', 'contra_bon') NOT NULL DEFAULT 'cash' AFTER notes;

-- Menambahkan index untuk optimasi query
CREATE INDEX idx_retail_food_payment_method ON retail_food(payment_method);
