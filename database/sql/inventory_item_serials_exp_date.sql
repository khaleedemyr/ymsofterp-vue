-- Tambah kolom exp_date (opsional) pada inventory_item_serials
ALTER TABLE inventory_item_serials
    ADD COLUMN exp_date DATE NULL AFTER generated_at;
