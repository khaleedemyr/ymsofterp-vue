-- Update kolom source_type untuk mendukung retail_non_food
-- Pastikan kolom source_type bisa menerima nilai 'retail_non_food'

-- 1. Ubah kolom source_type menjadi VARCHAR(50) jika masih ENUM atau terlalu kecil
ALTER TABLE `food_contra_bons`
MODIFY COLUMN `source_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'purchase_order, retail_food, warehouse_retail_food, retail_non_food';

-- 2. Update comment di kolom source_type untuk mencerminkan semua nilai yang didukung
-- (Query di atas sudah include comment)

-- CATATAN:
-- Jika kolom source_type masih ENUM, query di atas akan mengubahnya menjadi VARCHAR(50)
-- Pastikan tidak ada constraint lain yang membatasi nilai source_type

