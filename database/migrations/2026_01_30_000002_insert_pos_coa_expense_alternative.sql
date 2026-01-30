-- =====================================================
-- ALTERNATIVE: INSERT COA untuk POS Jurnal (Jika Perjamuan & Guest Satisfaction = Expense)
-- Created: 2026-01-30
-- Description: Alternative insert jika Perjamuan dan Guest Satisfaction langsung dibebankan sebagai Expense
-- =====================================================

-- CATATAN: File ini adalah ALTERNATIVE jika Perjamuan dan Guest Satisfaction 
-- langsung dibebankan sebagai Expense (bukan prepaid Asset)

-- =====================================================
-- 1. KAS TUNAI (Asset) - SAMA DENGAN FILE UTAMA
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
    '1.1.1.01',
    'Kas Tunai Outlet',
    'Asset',
    NULL,
    'Untuk mencatat pembayaran tunai/cash dari customer di POS',
    1,
    0,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW()
);

-- =====================================================
-- 2. PERJAMUAN (Expense) - ALTERNATIVE
-- =====================================================
-- Jika Perjamuan langsung dibebankan sebagai Expense (bukan prepaid Asset)
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
    '5.1.1.01',  -- Code: Expense (bukan Asset)
    'Perjamuan / Entertainment',  -- Name
    'Expense',  -- Type: Expense karena langsung dibebankan
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat pembayaran via perjamuan/entertainment di POS (langsung dibebankan sebagai expense)',  -- Description
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
-- 3. GUEST SATISFACTION (Expense) - ALTERNATIVE
-- =====================================================
-- Jika Guest Satisfaction langsung dibebankan sebagai Expense (bukan prepaid Asset)
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
    '5.1.1.02',  -- Code: Expense (bukan Asset)
    'Guest Satisfaction / Complimentary',  -- Name
    'Expense',  -- Type: Expense karena langsung dibebankan
    NULL,  -- parent_id: NULL jika root, atau ID parent jika ada hierarki
    'Untuk mencatat pembayaran via guest satisfaction/complimentary di POS (langsung dibebankan sebagai expense)',  -- Description
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
-- 4. PIUTANG OFFICER CHECK (Asset) - SAMA DENGAN FILE UTAMA
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
    '1.1.2.01',
    'Piutang Officer Check - Head Office',
    'Asset',
    NULL,
    'Untuk mencatat hutang dari officer check yang akan dibayar oleh Head Office. Ini adalah piutang karena outlet berhak menerima pembayaran dari HO.',
    1,
    0,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW()
);

-- =====================================================
-- CATATAN:
-- =====================================================
-- Gunakan file ini jika:
-- - Perjamuan langsung dibebankan sebagai Expense (bukan prepaid Asset)
-- - Guest Satisfaction langsung dibebankan sebagai Expense (bukan prepaid Asset)
-- 
-- JANGAN gunakan kedua file sekaligus! Pilih salah satu:
-- - File utama (2026_01_30_000001): Perjamuan & Guest Satisfaction = Asset (prepaid)
-- - File alternative ini: Perjamuan & Guest Satisfaction = Expense (langsung dibebankan)
