-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK LAPORAN ARUS KAS
-- Created: 2026-01-30
-- Description: Insert menu "Laporan Arus Kas" ke group HO Finance (parent_id=5)
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
    'Laporan Arus Kas',
    'jurnal_arus_kas',
    5, -- parent_id = 5 (HO Finance)
    '/report-arus-kas',
    'fa-solid fa-water',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Laporan Arus Kas',
    `route` = '/report-arus-kas',
    `icon` = 'fa-solid fa-water',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
-- Dapatkan menu_id dari menu yang baru saja di-insert
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'jurnal_arus_kas' LIMIT 1);

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
    'jurnal_arus_kas_view',
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
    'jurnal_arus_kas_export',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Query untuk verifikasi menu sudah dibuat:
-- SELECT * FROM erp_menu WHERE code = 'jurnal_arus_kas';

-- Query untuk verifikasi permission sudah dibuat:
-- SELECT p.*, m.name as menu_name, m.code as menu_code 
-- FROM erp_permission p
-- INNER JOIN erp_menu m ON p.menu_id = m.id
-- WHERE m.code = 'jurnal_arus_kas';

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Menu akan muncul di sidebar group "HO Finance" (parent_id = 5)
-- 2. Permission code: 'jurnal_arus_kas_view' untuk akses view
-- 3. Permission code: 'jurnal_arus_kas_export' untuk akses export (jika ada)
-- 4. Route: '/report-arus-kas'
-- 5. Icon: 'fa-solid fa-water'
-- 
-- 6. Untuk memberikan akses ke user/role, gunakan permission code:
--    - 'jurnal_arus_kas_view' untuk akses view laporan
--    - 'jurnal_arus_kas_export' untuk akses export (jika ada)
