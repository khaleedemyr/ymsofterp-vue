-- =====================================================
-- INSERT COA untuk POS Jurnal
-- Created: 2026-01-30
-- Description: Insert Chart of Accounts untuk POS payment types
-- =====================================================

-- Catatan:
-- 1. Pastikan parent COA sudah ada sebelum insert (jika menggunakan parent_id)
-- 2. Sesuaikan code dengan struktur COA yang sudah ada di sistem
-- 3. Pastikan code unik dan tidak duplicate

-- =====================================================
-- 1. KAS TUNAI (Asset)
-- =====================================================
INSERT INTO `chart_of_accounts` (
    `code`,
    `name`,
    `type`,
    `parent_id`,
    `description`,
    `is_active`,
    `show_in_menu_payment`,
    `static_or_dynamic`,
    `menu_id`,
    `mode_payment`,
    `budget_limit`,
    `default_counter_account_id`,
    `created_at`,
    `updated_at`
) VALUES (
    '1.1.1.01',  -- Code: Sesuaikan dengan struktur COA Anda
    'Kas Tunai Outlet',  -- Name
    'Asset',  -- Type
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat pembayaran tunai/cash dari customer di POS',  -- Description
    1,  -- is_active: 1 = aktif
    0,  -- show_in_menu_payment: 0 = tidak tampil di menu payment
    NULL,  -- static_or_dynamic: NULL jika tidak digunakan
    NULL,  -- menu_id: NULL jika tidak digunakan
    NULL,  -- mode_payment: NULL jika tidak digunakan
    NULL,  -- budget_limit: NULL jika tidak ada limit
    NULL,  -- default_counter_account_id: NULL jika tidak ada
    NOW(),  -- created_at
    NOW()   -- updated_at
);

-- =====================================================
-- 2. PERJAMUAN (Asset)
-- =====================================================
-- Catatan: Tentukan apakah Asset atau Expense
-- Jika prepaid/perjamuan yang sudah dibayar sebelumnya = Asset
-- Jika langsung dibebankan = Expense
-- Contoh ini menggunakan Asset (prepaid)
INSERT INTO `chart_of_accounts` (
    `code`,
    `name`,
    `type`,
    `parent_id`,
    `description`,
    `is_active`,
    `show_in_menu_payment`,
    `static_or_dynamic`,
    `menu_id`,
    `mode_payment`,
    `budget_limit`,
    `default_counter_account_id`,
    `created_at`,
    `updated_at`
) VALUES (
    '1.1.1.02',  -- Code: Sesuaikan dengan struktur COA Anda
    'Perjamuan / Entertainment',  -- Name
    'Asset',  -- Type: Asset jika prepaid, Expense jika langsung dibebankan
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat pembayaran via perjamuan/entertainment di POS',  -- Description
    1,  -- is_active: 1 = aktif
    0,  -- show_in_menu_payment: 0 = tidak tampil di menu payment
    NULL,  -- static_or_dynamic: NULL jika tidak digunakan
    NULL,  -- menu_id: NULL jika tidak digunakan
    NULL,  -- mode_payment: NULL jika tidak digunakan
    NULL,  -- budget_limit: NULL jika tidak ada limit
    NULL,  -- default_counter_account_id: NULL jika tidak ada
    NOW(),  -- created_at
    NOW()   -- updated_at
);

-- =====================================================
-- 3. GUEST SATISFACTION (Asset)
-- =====================================================
-- Catatan: Tentukan apakah Asset atau Expense
-- Jika prepaid/complimentary yang sudah dibayar sebelumnya = Asset
-- Jika langsung dibebankan = Expense
-- Contoh ini menggunakan Asset (prepaid)
INSERT INTO `chart_of_accounts` (
    `code`,
    `name`,
    `type`,
    `parent_id`,
    `description`,
    `is_active`,
    `show_in_menu_payment`,
    `static_or_dynamic`,
    `menu_id`,
    `mode_payment`,
    `budget_limit`,
    `default_counter_account_id`,
    `created_at`,
    `updated_at`
) VALUES (
    '1.1.1.03',  -- Code: Sesuaikan dengan struktur COA Anda
    'Guest Satisfaction / Complimentary',  -- Name
    'Asset',  -- Type: Asset jika prepaid, Expense jika langsung dibebankan
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat pembayaran via guest satisfaction/complimentary di POS',  -- Description
    1,  -- is_active: 1 = aktif
    0,  -- show_in_menu_payment: 0 = tidak tampil di menu payment
    NULL,  -- static_or_dynamic: NULL jika tidak digunakan
    NULL,  -- menu_id: NULL jika tidak digunakan
    NULL,  -- mode_payment: NULL jika tidak digunakan
    NULL,  -- budget_limit: NULL jika tidak ada limit
    NULL,  -- default_counter_account_id: NULL jika tidak ada
    NOW(),  -- created_at
    NOW()   -- updated_at
);

-- =====================================================
-- 4. PIUTANG OFFICER CHECK (Asset)
-- =====================================================
INSERT INTO `chart_of_accounts` (
    `code`,
    `name`,
    `type`,
    `parent_id`,
    `description`,
    `is_active`,
    `show_in_menu_payment`,
    `static_or_dynamic`,
    `menu_id`,
    `mode_payment`,
    `budget_limit`,
    `default_counter_account_id`,
    `created_at`,
    `updated_at`
) VALUES (
    '1.1.2.01',  -- Code: Sesuaikan dengan struktur COA Anda
    'Piutang Officer Check - Head Office',  -- Name
    'Asset',  -- Type: Asset karena ini piutang (hak menerima pembayaran)
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat hutang dari officer check yang akan dibayar oleh Head Office. Ini adalah piutang karena outlet berhak menerima pembayaran dari HO.',  -- Description
    1,  -- is_active: 1 = aktif
    0,  -- show_in_menu_payment: 0 = tidak tampil di menu payment
    NULL,  -- static_or_dynamic: NULL jika tidak digunakan
    NULL,  -- menu_id: NULL jika tidak digunakan
    NULL,  -- mode_payment: NULL jika tidak digunakan
    NULL,  -- budget_limit: NULL jika tidak ada limit
    NULL,  -- default_counter_account_id: NULL jika tidak ada
    NOW(),  -- created_at
    NOW()   -- updated_at
);

-- =====================================================
-- VERIFIKASI INSERT
-- =====================================================
-- Query untuk verifikasi bahwa COA sudah ter-insert dengan benar:
-- SELECT id, code, name, type, is_active FROM chart_of_accounts 
-- WHERE code IN ('1.1.1.01', '1.1.1.02', '1.1.1.03', '1.1.2.01')
-- ORDER BY code;

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. PASTIKAN CODE UNIK: Sesuaikan code dengan struktur COA yang sudah ada
-- 2. PARENT_ID: Jika ada hierarki COA, set parent_id sesuai dengan parent COA
-- 3. TYPE: 
--    - Asset: untuk Kas, Perjamuan (prepaid), Guest Satisfaction (prepaid), Piutang
--    - Expense: untuk Perjamuan/Guest Satisfaction jika langsung dibebankan
-- 4. Jika Perjamuan/Guest Satisfaction adalah Expense, ubah:
--    - Code menjadi '5.1.1.01' dan '5.1.1.02' (untuk Expense)
--    - Type menjadi 'Expense'
-- 5. Setelah insert, pastikan is_active = 1 agar bisa digunakan
