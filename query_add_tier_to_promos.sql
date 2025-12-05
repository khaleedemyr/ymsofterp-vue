-- Query untuk menambahkan field tier ke table promos
-- Field all_tiers: boolean untuk menandai apakah promo berlaku untuk semua tier
-- Field tiers: JSON untuk menyimpan array tier yang dipilih (jika all_tiers = false)

ALTER TABLE `promos` 
ADD COLUMN `all_tiers` TINYINT(1) DEFAULT 0 AFTER `need_member`,
ADD COLUMN `tiers` JSON NULL AFTER `all_tiers`;

-- Update index jika perlu
-- ALTER TABLE `promos` ADD INDEX `idx_all_tiers` (`all_tiers`);

