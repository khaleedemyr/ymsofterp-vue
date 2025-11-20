-- Migration untuk membuat kolom po_id nullable di tabel food_contra_bons
-- Ini diperlukan karena contra bon bisa dibuat dari retail_food atau warehouse_retail_food
-- yang tidak memiliki po_id

ALTER TABLE `food_contra_bons` 
MODIFY COLUMN `po_id` BIGINT(20) UNSIGNED NULL;

