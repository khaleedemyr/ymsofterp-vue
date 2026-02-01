-- =====================================================
-- INSERT MENU DAN PERMISSION UNTUK RETAIL NON FOOD PAYMENT
-- Created: 2026-02-01
-- Description: Insert menu "Retail Non Food Payment" ke group HO Finance (parent_id=5)
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
    'Retail Non Food Payment',
    'retail_non_food_payment',
    5, -- parent_id = 5 (HO Finance)
    '/retail-non-food-payment',
    'fa-solid fa-money-bill-wave',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Retail Non Food Payment',
    `route` = '/retail-non-food-payment',
    `icon` = 'fa-solid fa-money-bill-wave',
    `updated_at` = NOW();

-- =====================================================
-- 2. INSERT PERMISSION KE TABLE erp_permission
-- =====================================================
-- Dapatkan menu_id dari menu yang baru saja di-insert
SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'retail_non_food_payment' LIMIT 1);

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
    'retail_non_food_payment_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Insert permission untuk action 'create' (tambah/buat jurnal)
INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'create',
    'retail_non_food_payment_create',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- =====================================================
-- SELESAI
-- =====================================================
-- Catatan:
-- 1. Menu akan muncul di sidebar group "HO Finance" (parent_id = 5)
-- 2. Permission 'view' untuk akses halaman index (list transaksi)
-- 3. Permission 'create' untuk membuat jurnal dari transaksi retail non food
-- 4. Route: /retail-non-food-payment
-- 5. Icon: fa-solid fa-money-bill-wave
-- 
-- Untuk memberikan akses ke role tertentu, tambahkan record di table erp_role_permission:
-- INSERT INTO erp_role_permission (role_id, permission_id) 
-- SELECT 'YOUR_ROLE_ID', id FROM erp_permission 
-- WHERE code IN ('retail_non_food_payment_view', 'retail_non_food_payment_create');
-- =====================================================
