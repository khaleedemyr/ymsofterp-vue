-- Regional Management: simpan area (Bar/Kitchen/Service) per user, tanpa outlet_id
-- Jalankan sekali di MySQL (manual SQL, tanpa migration).
-- BACKUP tabel user_regional sebelum menjalankan script ini.

-- 1) Tambah kolom area
ALTER TABLE `user_regional`
  ADD COLUMN `area` VARCHAR(20) NULL DEFAULT NULL
    COMMENT 'Regional area: Bar, Kitchen, Service'
    AFTER `outlet_id`;

-- 2) Backfill area dari nama outlet lama (best effort)
UPDATE `user_regional` AS `ur`
INNER JOIN `tbl_data_outlet` AS `o` ON `o`.`id_outlet` = `ur`.`outlet_id`
SET `ur`.`area` = CASE
  WHEN `o`.`nama_outlet` REGEXP '(^|[[:space:]-])Bar$' THEN 'Bar'
  WHEN `o`.`nama_outlet` REGEXP '(^|[[:space:]-])Kitchen$' THEN 'Kitchen'
  WHEN `o`.`nama_outlet` REGEXP '(^|[[:space:]-])Service$' THEN 'Service'
  ELSE NULL
END
WHERE `ur`.`area` IS NULL;

-- 3) Rapikan duplikat user_id (simpan baris id terkecil per user)
DELETE `ur_del` FROM `user_regional` AS `ur_del`
INNER JOIN `user_regional` AS `ur_keep`
  ON `ur_del`.`user_id` = `ur_keep`.`user_id`
 AND `ur_del`.`id` > `ur_keep`.`id`;

-- 4) Hapus data yang area-nya masih NULL (tidak bisa dipetakan otomatis)
--    Assign ulang lewat menu Regional Management setelah script ini.
DELETE FROM `user_regional` WHERE `area` IS NULL;

-- 5) Hapus kolom outlet_id (hapus FK dulu jika ada di environment Anda)
--    Cek dulu: SHOW CREATE TABLE user_regional;
--    Jika ada constraint FK ke tbl_data_outlet, jalankan DROP FOREIGN KEY ... terlebih dahulu.

ALTER TABLE `user_regional`
  DROP COLUMN `outlet_id`;

-- 6) Wajibkan area + satu user hanya satu assignment
ALTER TABLE `user_regional`
  MODIFY COLUMN `area` VARCHAR(20) NOT NULL
    COMMENT 'Regional area: Bar, Kitchen, Service';

ALTER TABLE `user_regional`
  ADD UNIQUE KEY `uk_user_regional_user_id` (`user_id`);
