-- Opsional: simpan referensi master item & unit pada baris RWF (serial & laporan).
-- Jalankan sekali jika kolom belum ada.

ALTER TABLE retail_warehouse_food_items
    ADD COLUMN item_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER retail_warehouse_food_id,
    ADD COLUMN unit_id INT UNSIGNED NULL DEFAULT NULL AFTER unit;
