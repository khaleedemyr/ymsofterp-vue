-- Query untuk menambahkan kolom arrival_date ke tabel food_floor_orders
ALTER TABLE food_floor_orders ADD COLUMN arrival_date DATE NULL AFTER fo_schedule_id;

-- Query untuk menambahkan kolom arrival_date ke tabel food_floor_order_items (jika diperlukan per item)
ALTER TABLE food_floor_order_items ADD COLUMN arrival_date DATE NULL AFTER warehouse_division_id;

-- Query untuk melihat struktur tabel setelah penambahan kolom
DESCRIBE food_floor_orders;
DESCRIBE food_floor_order_items;
