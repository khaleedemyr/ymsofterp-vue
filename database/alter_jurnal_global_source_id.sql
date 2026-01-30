-- =====================================================
-- ALTER TABLE JURNAL_GLOBAL - Change source_id to VARCHAR
-- Created: 2026-01-30
-- Description: Ubah tipe data source_id dari BIGINT ke VARCHAR
--              untuk support string ID (seperti order_id yang bisa string UUID)
-- =====================================================

-- Drop index yang menggunakan source_id (jika ada)
ALTER TABLE `jurnal_global` DROP INDEX IF EXISTS `idx_source`;

-- Ubah tipe data source_id dari BIGINT UNSIGNED ke VARCHAR(255)
ALTER TABLE `jurnal_global` 
MODIFY COLUMN `source_id` VARCHAR(255) NULL COMMENT 'ID dari source module (bisa string atau integer)';

-- Recreate index untuk source_module dan source_id
ALTER TABLE `jurnal_global` 
ADD INDEX `idx_source` (`source_module`, `source_id`);

-- Verifikasi perubahan
-- DESCRIBE jurnal_global;
