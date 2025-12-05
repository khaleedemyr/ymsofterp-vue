-- Query untuk menambahkan kolom yang diperlukan di tabel food_contra_bons
-- Berdasarkan model ContraBon dan controller
-- JALANKAN QUERY INI SATU PER SATU, JIKA ADA ERROR "Duplicate column name" BERARTI KOLOM SUDAH ADA

-- 1. Tambahkan kolom supplier_invoice_number (ERROR: Column not found)
ALTER TABLE `food_contra_bons`
ADD COLUMN `supplier_invoice_number` VARCHAR(100) NULL DEFAULT NULL AFTER `notes`;

-- 2. Tambahkan kolom number (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `number` VARCHAR(50) NULL DEFAULT NULL AFTER `id`;

-- 3. Tambahkan kolom date (jika belum ada, mungkin sudah ada dengan nama issue_date)
ALTER TABLE `food_contra_bons`
ADD COLUMN `date` DATE NULL DEFAULT NULL AFTER `number`;

-- 4. Tambahkan kolom po_id
ALTER TABLE `food_contra_bons`
ADD COLUMN `po_id` BIGINT NULL DEFAULT NULL AFTER `supplier_id`;

-- 5. Tambahkan kolom image_path
ALTER TABLE `food_contra_bons`
ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `notes`;

-- 6. Tambahkan kolom source_type
ALTER TABLE `food_contra_bons`
ADD COLUMN `source_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'purchase_order, retail_food, warehouse_retail_food' AFTER `supplier_invoice_number`;

-- 7. Tambahkan kolom source_id
ALTER TABLE `food_contra_bons`
ADD COLUMN `source_id` BIGINT NULL DEFAULT NULL AFTER `source_type`;

-- 8. Tambahkan kolom created_by (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `created_by` BIGINT NULL DEFAULT NULL AFTER `status`;

-- 9. Tambahkan kolom approved_by (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `approved_by` BIGINT NULL DEFAULT NULL AFTER `created_by`;

-- 10. Tambahkan kolom approved_at (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`;

-- 11. Tambahkan kolom finance_manager_approved_at (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `finance_manager_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_at`;

-- 12. Tambahkan kolom finance_manager_approved_by (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `finance_manager_approved_by` BIGINT NULL DEFAULT NULL AFTER `finance_manager_approved_at`;

-- 13. Tambahkan kolom finance_manager_note (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `finance_manager_note` TEXT NULL DEFAULT NULL AFTER `finance_manager_approved_by`;

-- 14. Tambahkan kolom gm_finance_approved_at (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `gm_finance_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `finance_manager_note`;

-- 15. Tambahkan kolom gm_finance_approved_by (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `gm_finance_approved_by` BIGINT NULL DEFAULT NULL AFTER `gm_finance_approved_at`;

-- 16. Tambahkan kolom gm_finance_note (jika belum ada)
ALTER TABLE `food_contra_bons`
ADD COLUMN `gm_finance_note` TEXT NULL DEFAULT NULL AFTER `gm_finance_approved_by`;

-- CATATAN:
-- Jika kolom date belum ada tapi ada issue_date, bisa copy data:
-- UPDATE `food_contra_bons` SET `date` = `issue_date` WHERE `issue_date` IS NOT NULL AND `date` IS NULL;

-- Jika kolom number belum ada tapi ada contra_bon_number, bisa copy data:
-- UPDATE `food_contra_bons` SET `number` = `contra_bon_number` WHERE `contra_bon_number` IS NOT NULL AND `number` IS NULL;
