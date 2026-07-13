-- Tambah kolom production_date (tanggal produksi serial) pada inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN production_date DATE NULL AFTER exp_date;
