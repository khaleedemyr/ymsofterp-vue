-- Query untuk menambahkan field gr_item_id ke tabel food_contra_bon_items
-- Field ini digunakan untuk tracking item dari good receive yang sudah dibuat contra bon

ALTER TABLE `food_contra_bon_items` 
ADD COLUMN `gr_item_id` INT(11) NULL DEFAULT NULL AFTER `po_item_id`,
ADD INDEX `idx_gr_item_id` (`gr_item_id`);

-- Optional: Jika ingin menambahkan foreign key constraint
-- ALTER TABLE `food_contra_bon_items`
-- ADD CONSTRAINT `fk_contra_bon_items_gr_item` 
-- FOREIGN KEY (`gr_item_id`) REFERENCES `food_good_receive_items` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

