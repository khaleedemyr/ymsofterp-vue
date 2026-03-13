-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK REKAP TRANSAKSI BANK
-- Created: 2026-03-11
-- Description: Insert menu "Rekap Transaksi Bank" ke group Sales & Marketing (parent_id=8)
-- =====================================================

-- =====================================================
-- 1. INSERT MENU KE TABLE erp_menu
-- =====================================================
INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Rekap Transaksi Bank',
    'report_bank_transaction',
    8,
    '/report-bank-transaction',
    'fa-solid fa-university',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Rekap Transaksi Bank',
    `parent_id` = 8,
    `route` = '/report-bank-transaction',
    `icon` = 'fa-solid fa-university',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'report_bank_transaction' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'report_bank_transaction_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- SELESAI
-- =====================================================
-- Catatan:
-- 1. Menu muncul di sidebar group "Sales & Marketing" (parent_id = 8)
-- 2. Permission code: report_bank_transaction_view (action: view)
-- 3. Route: /report-bank-transaction
-- 4. Untuk beri akses ke role: INSERT INTO erp_role_permission (role_id, permission_id)
--    SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'report_bank_transaction_view';
-- =====================================================
