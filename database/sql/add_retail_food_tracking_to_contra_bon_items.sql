-- ============================================
-- QUERY OPSIONAL: Menambahkan kolom untuk tracking Retail Food dan Warehouse Retail Food
-- ============================================
-- Query ini OPSIONAL dan hanya diperlukan jika ingin tracking yang lebih akurat
-- untuk item dari Retail Food dan Warehouse Retail Food
-- 
-- CATATAN PENTING:
-- 1. Query ini TIDAK WAJIB untuk fitur multiple sources
-- 2. Data lama tetap aman tanpa query ini
-- 3. Tanpa kolom ini, tracking retail food masih bisa dilakukan via source_id di contra_bon + item_name
-- 4. Dengan kolom ini, tracking lebih akurat dan bisa mencegah duplikasi item
--
-- ============================================
-- OPSI 1: Tambahkan kolom untuk Retail Food Item ID
-- ============================================
ALTER TABLE `food_contra_bon_items` 
ADD COLUMN `retail_food_item_id` INT(11) NULL DEFAULT NULL AFTER `gr_item_id`,
ADD INDEX `idx_retail_food_item_id` (`retail_food_item_id`);

-- ============================================
-- OPSI 2: Tambahkan kolom untuk Warehouse Retail Food Item ID
-- ============================================
ALTER TABLE `food_contra_bon_items` 
ADD COLUMN `warehouse_retail_food_item_id` INT(11) NULL DEFAULT NULL AFTER `retail_food_item_id`,
ADD INDEX `idx_warehouse_retail_food_item_id` (`warehouse_retail_food_item_id`);

-- ============================================
-- OPSIONAL: Foreign Key Constraints (jika diperlukan)
-- ============================================
-- Uncomment jika ingin menambahkan foreign key constraints

-- ALTER TABLE `food_contra_bon_items`
-- ADD CONSTRAINT `fk_contra_bon_items_retail_food_item` 
-- FOREIGN KEY (`retail_food_item_id`) REFERENCES `retail_food_items` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `food_contra_bon_items`
-- ADD CONSTRAINT `fk_contra_bon_items_warehouse_retail_food_item` 
-- FOREIGN KEY (`warehouse_retail_food_item_id`) REFERENCES `retail_warehouse_food_items` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

