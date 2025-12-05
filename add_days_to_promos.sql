-- Query untuk menambahkan field days (hari) ke tabel promos
ALTER TABLE `promos` 
ADD COLUMN `days` JSON NULL COMMENT 'Array hari promo berlaku (contoh: ["Senin", "Selasa", "Rabu"])' 
AFTER `end_time`;

