-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK NERACA SALDO
-- Created: 2026-01-30
-- Description: Insert menu "Neraca Saldo" ke group HO Finance (parent_id=5)
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
    'Neraca Saldo',
    'jurnal_neraca_saldo',
    5, -- parent_id = 5 (HO Finance)
    '/report-jurnal-neraca-saldo',
    'fa-solid fa-balance-scale',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Neraca Saldo',
    `route` = '/report-jurnal-neraca-saldo',
    `icon` = 'fa-solid fa-balance-scale',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
-- Dapatkan menu_id dari menu yang baru saja di-insert
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'jurnal_neraca_saldo' LIMIT 1);

-- Insert permission untuk action 'view' (baca)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'jurnal_neraca_saldo_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Insert permission untuk action 'export' (jika nanti ada fitur export)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'export',
    'jurnal_neraca_saldo_export',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Query untuk verifikasi menu sudah dibuat:
-- SELECT * FROM erp_menu WHERE code = 'jurnal_neraca_saldo';

-- Query untuk verifikasi permission sudah dibuat:
-- SELECT p.*, m.name as menu_name, m.code as menu_code 
-- FROM erp_permission p
-- INNER JOIN erp_menu m ON p.menu_id = m.id
-- WHERE m.code = 'jurnal_neraca_saldo';

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Menu akan muncul di sidebar group "HO Finance" (parent_id = 5)
-- 2. Permission code: 'jurnal_neraca_saldo_view' untuk akses view
-- 3. Permission code: 'jurnal_neraca_saldo_export' untuk akses export (jika ada)
-- 4. Route: '/report-jurnal-neraca-saldo'
-- 5. Icon: 'fa-solid fa-balance-scale'
-- 
-- 6. Untuk memberikan akses ke user/role, gunakan permission code:
--    - 'jurnal_neraca_saldo_view' untuk akses view laporan
--    - 'jurnal_neraca_saldo_export' untuk akses export (jika ada)
