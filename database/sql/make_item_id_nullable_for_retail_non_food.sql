-- Update kolom item_id dan unit_id menjadi nullable untuk mendukung retail_non_food (free text items)
-- Jalankan query ini di database untuk memperbaiki error "Column 'item_id' cannot be null"

-- 1. Ubah kolom item_id menjadi nullable
ALTER TABLE `food_contra_bon_items`
MODIFY COLUMN `item_id` BIGINT NULL DEFAULT NULL COMMENT 'NULL untuk retail_non_food (free text items)';

-- 2. Ubah kolom unit_id menjadi nullable
ALTER TABLE `food_contra_bon_items`
MODIFY COLUMN `unit_id` BIGINT NULL DEFAULT NULL COMMENT 'NULL untuk retail_non_food (free text items)';

-- 3. Verifikasi perubahan (optional, untuk memastikan perubahan berhasil)
-- SHOW COLUMNS FROM `food_contra_bon_items` WHERE Field IN ('item_id', 'unit_id');

-- CATATAN:
-- - Setelah query ini dijalankan, item_id dan unit_id bisa NULL untuk retail_non_food
-- - Untuk purchase_order, retail_food, dan warehouse_retail_food, item_id dan unit_id tetap harus terisi
-- - Validasi di controller sudah memastikan bahwa item_id dan unit_id hanya boleh null untuk retail_non_food

