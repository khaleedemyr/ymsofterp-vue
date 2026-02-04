-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK MEMBER HISTORY & PREFERENCES
-- Created: 2026-02-04
-- Description: Insert menu "Member History & Preferences" ke group CRM (parent_id=138)
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
    'Member History & Preferences',
    'member_history_preferences',
    138, -- parent_id = 138 (CRM)
    '/member-history',
    'fa-solid fa-history',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Member History & Preferences',
    `route` = '/member-history',
    `icon` = 'fa-solid fa-history',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
-- Dapatkan menu_id dari menu yang baru saja di-insert
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'member_history_preferences' LIMIT 1);

-- Insert permission untuk action 'view' (baca/search member)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'member_history_preferences_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Insert permission untuk action 'read' (melihat detail history)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'read',
    'member_history_preferences_read',
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
    'member_history_preferences_export',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Query untuk verifikasi menu sudah dibuat:
-- SELECT * FROM erp_menu WHERE code = 'member_history_preferences';

-- Query untuk verifikasi permission sudah dibuat:
-- SELECT p.*, m.name as menu_name, m.code as menu_code 
-- FROM erp_permission p
-- INNER JOIN erp_menu m ON p.menu_id = m.id
-- WHERE m.code = 'member_history_preferences';

-- Query untuk melihat semua menu di group CRM (parent_id = 138):
-- SELECT * FROM erp_menu WHERE parent_id = 138 ORDER BY created_at;

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Menu akan muncul di sidebar group "CRM" (parent_id = 138)
-- 2. Permission codes:
--    - 'member_history_preferences_view' untuk akses search member
--    - 'member_history_preferences_read' untuk akses detail history & preferences
--    - 'member_history_preferences_export' untuk akses export (future feature)
-- 3. Route: '/member-history'
-- 4. Icon: 'fa-solid fa-history'
-- 
-- 5. Untuk memberikan akses ke user/role, gunakan permission code:
--    - 'member_history_preferences_view' untuk search member
--    - 'member_history_preferences_read' untuk lihat detail
--    - 'member_history_preferences_export' untuk export data (jika ada)
--
-- 6. API Endpoints yang digunakan:
--    - GET /api/approval-app/member-history/info
--    - GET /api/approval-app/member-history/transactions
--    - GET /api/approval-app/member-history/order/{orderId}
--    - GET /api/approval-app/member-history/preferences
--
-- 7. Fitur yang tersedia:
--    - Search member by ID atau No HP
--    - View member information (personal data, points, tier, etc)
--    - View transaction history dengan pagination
--    - View order detail lengkap dengan items
--    - View member preferences (favorite items & favorite outlet)
