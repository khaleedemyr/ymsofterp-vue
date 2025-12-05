-- Migration untuk membuat kolom po_item_id nullable di tabel food_contra_bon_items
-- Ini diperlukan karena contra bon bisa dibuat dari retail_food atau warehouse_retail_food
-- yang tidak memiliki po_item_id

ALTER TABLE `food_contra_bon_items` 
MODIFY COLUMN `po_item_id` BIGINT(20) UNSIGNED NULL;

