-- Tambah kolom termin kasbon (tanpa migration Laravel)
ALTER TABLE `purchase_requisitions`
ADD COLUMN `kasbon_termin` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `notes`;

-- Opsional: isi default untuk data kasbon lama agar termin terbaca 1x
UPDATE `purchase_requisitions`
SET `kasbon_termin` = 1
WHERE `mode` = 'kasbon' AND (`kasbon_termin` IS NULL OR `kasbon_termin` = 0);
