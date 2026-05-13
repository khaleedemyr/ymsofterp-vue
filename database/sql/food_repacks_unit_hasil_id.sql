-- Tambahkan kolom satuan hasil jika belum ada (cek: DESCRIBE food_repacks;).
-- Hapus baris ini jika kolom unit_hasil_id sudah ada.

ALTER TABLE food_repacks
  ADD COLUMN unit_hasil_id BIGINT UNSIGNED NULL
    COMMENT 'FK ke units — satuan untuk qty_hasil'
    AFTER item_hasil_id;
