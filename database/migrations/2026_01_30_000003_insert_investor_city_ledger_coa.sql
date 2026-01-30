-- =====================================================
-- INSERT COA untuk Investor dan City Ledger
-- Created: 2026-01-30
-- Description: Insert COA untuk payment types Investor dan Outlet City Ledger
-- =====================================================

-- =====================================================
-- 1. COA PIUTANG INVESTOR
-- =====================================================
-- Investor: potong profit, jadi ini adalah piutang yang akan dipotong dari profit investor
-- Tipe: Asset (karena kita punya hak tagih ke investor)
-- Code: 1.1.2.02 (setelah Piutang Officer Check 1.1.2.01)
INSERT INTO `chart_of_accounts` (
    `code`, 
    `name`, 
    `type`, 
    `parent_id`, 
    `is_active`, 
    `description`,
    `created_at`, 
    `updated_at`
) VALUES (
    '1.1.2.02',
    'Piutang Investor',
    'Asset',
    NULL, -- Atau sesuaikan dengan parent_id untuk kategori Piutang
    1,
    'Piutang ke investor yang akan dipotong dari profit investor',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE 
    `name` = 'Piutang Investor',
    `type` = 'Asset',
    `description` = 'Piutang ke investor yang akan dipotong dari profit investor',
    `updated_at` = NOW();

-- =====================================================
-- 2. COA PIUTANG CITY LEDGER (OUTLET CITY LEDGER)
-- =====================================================
-- City Ledger: dibebankan ke karyawan, sama seperti Officer Check
-- Tipe: Asset (karena kita punya hak tagih ke karyawan)
-- Code: 1.1.2.03 (setelah Piutang Investor 1.1.2.02)
INSERT INTO `chart_of_accounts` (
    `code`, 
    `name`, 
    `type`, 
    `parent_id`, 
    `is_active`, 
    `description`,
    `created_at`, 
    `updated_at`
) VALUES (
    '1.1.2.03',
    'Piutang City Ledger',
    'Asset',
    NULL, -- Atau sesuaikan dengan parent_id untuk kategori Piutang
    1,
    'Piutang ke karyawan melalui city ledger (dibebankan ke karyawan)',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE 
    `name` = 'Piutang City Ledger',
    `type` = 'Asset',
    `description` = 'Piutang ke karyawan melalui city ledger (dibebankan ke karyawan)',
    `updated_at` = NOW();

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Query untuk verifikasi COA sudah dibuat:
-- SELECT * FROM chart_of_accounts WHERE code IN ('1.1.2.02', '1.1.2.03');

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. COA Investor (1.1.2.02):
--    - Tipe: Asset
--    - Digunakan untuk payment type "Investor"
--    - Nantinya akan dipotong dari profit investor
-- 
-- 2. COA City Ledger (1.1.2.03):
--    - Tipe: Asset
--    - Digunakan untuk payment type "City Ledger" atau "Outlet City Ledger"
--    - Perlakuannya sama seperti Officer Check (dibebankan ke karyawan)
-- 
-- 3. Payment Code yang akan di-detect:
--    - Investor: payment_code mengandung "INVESTOR"
--    - City Ledger: payment_code mengandung "CITY", "LEDGER", atau "CITY_LEDGER"
-- 
-- 4. Jika code COA berbeda, sesuaikan di JurnalService.php:
--    - getCoaPiutangInvestor()
--    - getCoaPiutangCityLedger()
