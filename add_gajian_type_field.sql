-- Query untuk menambahkan field gajian_type ke tabel custom_payroll_items
-- Field ini digunakan untuk membedakan custom items yang muncul di gajian 1 (akhir bulan) atau gajian 2 (tanggal 8)

ALTER TABLE `custom_payroll_items` 
ADD COLUMN `gajian_type` VARCHAR(20) NULL DEFAULT 'gajian1' 
COMMENT 'gajian1 untuk gaji akhir bulan, gajian2 untuk gaji tanggal 8' 
AFTER `item_description`;

-- Update data existing untuk set default ke gajian1 (untuk backward compatibility)
UPDATE `custom_payroll_items` 
SET `gajian_type` = 'gajian1' 
WHERE `gajian_type` IS NULL;

