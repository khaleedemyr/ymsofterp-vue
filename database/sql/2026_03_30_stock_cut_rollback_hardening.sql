-- Hardening stock cut rollback agar tidak menghapus kartu stok lintas proses
-- Jalankan manual di database production (tanpa migration Laravel)

-- 1) Tambah kolom penghubung kartu stok -> stock_cut_logs
ALTER TABLE outlet_food_inventory_cards
ADD COLUMN stock_cut_log_id BIGINT UNSIGNED NULL AFTER warehouse_outlet_id;

-- 2) Index untuk query rollback dan laporan
ALTER TABLE outlet_food_inventory_cards
ADD INDEX idx_ofic_stock_cut_log_id (stock_cut_log_id),
ADD INDEX idx_ofic_outlet_date_ref (id_outlet, date, reference_type);

-- 3) (Opsional tapi direkomendasikan) FK untuk menjaga integritas data
-- Pastikan engine tabel InnoDB sebelum menjalankan FK ini.
ALTER TABLE outlet_food_inventory_cards
ADD CONSTRAINT fk_ofic_stock_cut_log_id
FOREIGN KEY (stock_cut_log_id) REFERENCES stock_cut_logs(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- 4) Cek hasil perubahan
-- SHOW COLUMNS FROM outlet_food_inventory_cards LIKE 'stock_cut_log_id';
-- SHOW INDEX FROM outlet_food_inventory_cards;
