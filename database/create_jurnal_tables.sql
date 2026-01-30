-- =====================================================
-- CREATE TABLE JURNAL DAN JURNAL_GLOBAL
-- Created: 2026-01-30
-- Description: Query untuk membuat table jurnal dan jurnal_global
-- =====================================================

-- =====================================================
-- 1. TABLE JURNAL
-- =====================================================
CREATE TABLE IF NOT EXISTS `jurnal` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `no_jurnal` VARCHAR(50) NOT NULL COMMENT 'Format: JRN-YYYYMM####',
    `tanggal` DATE NOT NULL COMMENT 'Tanggal transaksi jurnal',
    `keterangan` TEXT NULL COMMENT 'Keterangan/deskripsi jurnal',
    `coa_debit_id` BIGINT UNSIGNED NOT NULL COMMENT 'COA untuk debit',
    `coa_kredit_id` BIGINT UNSIGNED NOT NULL COMMENT 'COA untuk kredit',
    `jumlah_debit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Jumlah debit',
    `jumlah_kredit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Jumlah kredit',
    `outlet_id` INT UNSIGNED NULL COMMENT 'ID Outlet (relasi ke tbl_data_outlet.id_outlet)',
    `reference_type` VARCHAR(50) NULL COMMENT 'Tipe referensi (pos_order, outlet_payment, dll)',
    `reference_id` VARCHAR(255) NULL COMMENT 'ID referensi (order_id, payment_id, dll)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'Status: draft, posted, cancelled',
    `created_by` BIGINT UNSIGNED NULL COMMENT 'User yang membuat jurnal',
    `updated_by` BIGINT UNSIGNED NULL COMMENT 'User yang mengupdate jurnal',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_no_jurnal` (`no_jurnal`),
    INDEX `idx_tanggal` (`tanggal`),
    INDEX `idx_coa_debit` (`coa_debit_id`),
    INDEX `idx_coa_kredit` (`coa_kredit_id`),
    INDEX `idx_outlet` (`outlet_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    CONSTRAINT `fk_jurnal_coa_debit` FOREIGN KEY (`coa_debit_id`) 
        REFERENCES `chart_of_accounts` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_coa_kredit` FOREIGN KEY (`coa_kredit_id`) 
        REFERENCES `chart_of_accounts` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_outlet` FOREIGN KEY (`outlet_id`) 
        REFERENCES `tbl_data_outlet` (`id_outlet`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_created_by` FOREIGN KEY (`created_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_updated_by` FOREIGN KEY (`updated_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Table untuk menyimpan jurnal entries (double entry bookkeeping)';

-- =====================================================
-- 2. TABLE JURNAL_GLOBAL
-- =====================================================
CREATE TABLE IF NOT EXISTS `jurnal_global` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `no_jurnal` VARCHAR(50) NOT NULL COMMENT 'Format: JRN-YYYYMM####',
    `tanggal` DATE NOT NULL COMMENT 'Tanggal transaksi jurnal',
    `keterangan` TEXT NULL COMMENT 'Keterangan/deskripsi jurnal',
    `coa_debit_id` BIGINT UNSIGNED NOT NULL COMMENT 'COA untuk debit',
    `coa_kredit_id` BIGINT UNSIGNED NOT NULL COMMENT 'COA untuk kredit',
    `jumlah_debit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Jumlah debit',
    `jumlah_kredit` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Jumlah kredit',
    `outlet_id` INT UNSIGNED NULL COMMENT 'ID Outlet (relasi ke tbl_data_outlet.id_outlet)',
    `source_module` VARCHAR(50) NULL COMMENT 'Module sumber (jurnal, pos_order, outlet_payment, dll)',
    `source_id` BIGINT UNSIGNED NULL COMMENT 'ID dari source module',
    `reference_type` VARCHAR(50) NULL COMMENT 'Tipe referensi (pos_order, outlet_payment, dll)',
    `reference_id` VARCHAR(255) NULL COMMENT 'ID referensi (order_id, payment_id, dll)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'Status: draft, posted, cancelled',
    `posted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu jurnal di-post',
    `posted_by` BIGINT UNSIGNED NULL COMMENT 'User yang mem-post jurnal',
    `created_by` BIGINT UNSIGNED NULL COMMENT 'User yang membuat jurnal',
    `updated_by` BIGINT UNSIGNED NULL COMMENT 'User yang mengupdate jurnal',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_no_jurnal` (`no_jurnal`),
    INDEX `idx_tanggal` (`tanggal`),
    INDEX `idx_coa_debit` (`coa_debit_id`),
    INDEX `idx_coa_kredit` (`coa_kredit_id`),
    INDEX `idx_outlet` (`outlet_id`),
    INDEX `idx_source` (`source_module`, `source_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_posted_at` (`posted_at`),
    INDEX `idx_posted_by` (`posted_by`),
    INDEX `idx_created_by` (`created_by`),
    CONSTRAINT `fk_jurnal_global_coa_debit` FOREIGN KEY (`coa_debit_id`) 
        REFERENCES `chart_of_accounts` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_global_coa_kredit` FOREIGN KEY (`coa_kredit_id`) 
        REFERENCES `chart_of_accounts` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_global_outlet` FOREIGN KEY (`outlet_id`) 
        REFERENCES `tbl_data_outlet` (`id_outlet`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_global_posted_by` FOREIGN KEY (`posted_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_global_created_by` FOREIGN KEY (`created_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jurnal_global_updated_by` FOREIGN KEY (`updated_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Table untuk menyimpan jurnal global entries (mirip jurnal tapi dengan tracking posting)';

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Query untuk verifikasi table sudah dibuat:
-- SHOW TABLES LIKE 'jurnal%';
-- 
-- Query untuk cek struktur table:
-- DESCRIBE jurnal;
-- DESCRIBE jurnal_global;
-- 
-- Query untuk cek indexes:
-- SHOW INDEXES FROM jurnal;
-- SHOW INDEXES FROM jurnal_global;

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Pastikan table berikut sudah ada sebelum create:
--    - chart_of_accounts
--    - tbl_data_outlet
--    - users
-- 
-- 2. Foreign key constraints:
--    - coa_debit_id dan coa_kredit_id → chart_of_accounts.id
--    - outlet_id → tbl_data_outlet.id_outlet
--    - created_by, updated_by, posted_by → users.id
-- 
-- 3. Indexes sudah dibuat untuk performa query:
--    - Index pada no_jurnal, tanggal, coa_debit_id, coa_kredit_id
--    - Index pada reference_type dan reference_id untuk tracking
--    - Index pada status untuk filtering
-- 
-- 4. Reference_id menggunakan VARCHAR(255) karena bisa berisi:
--    - Order ID (bisa string UUID)
--    - Payment ID (bisa string UUID)
--    - dll
-- 
-- 5. Jumlah debit dan kredit menggunakan DECIMAL(15, 2) untuk:
--    - Presisi 2 decimal (Rupiah)
--    - Maksimal 15 digit (sangat besar untuk transaksi)
