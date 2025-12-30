-- Fix kolom source_type untuk mendukung retail_non_food
-- Jalankan query ini di database untuk memperbaiki error "Data truncated for column 'source_type'"

-- 1. Ubah kolom source_type menjadi VARCHAR(50) jika masih ENUM atau terlalu kecil
-- Query ini akan mengubah tipe kolom menjadi VARCHAR(50) yang bisa menerima nilai apapun
ALTER TABLE `food_contra_bons`
MODIFY COLUMN `source_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'purchase_order, retail_food, warehouse_retail_food, retail_non_food';

-- 2. Verifikasi perubahan (optional, untuk memastikan perubahan berhasil)
-- SHOW COLUMNS FROM `food_contra_bons` WHERE Field = 'source_type';

-- CATATAN:
-- - Jika kolom source_type masih ENUM, query di atas akan mengubahnya menjadi VARCHAR(50)
-- - Pastikan tidak ada constraint lain yang membatasi nilai source_type
-- - Setelah query ini dijalankan, error "Data truncated" seharusnya tidak muncul lagi

