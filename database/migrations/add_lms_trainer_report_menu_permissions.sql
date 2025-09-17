-- =====================================================
-- SQL Script: Add LMS Trainer Report Menu & Permissions
-- =====================================================
-- Description: Menambahkan menu Trainer Report ke dalam grup LMS
--              beserta permission yang diperlukan
-- 
-- Tables affected:
-- - erp_menu: Menambahkan menu Trainer Report
-- - erp_permission: Menambahkan permission untuk menu tersebut
--
-- Parent Menu ID: 127 (LMS group)
-- New Menu ID: 128 (Trainer Report)
-- Permission IDs: 129-132
-- =====================================================

-- Insert Trainer Report menu into erp_menu table
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(128, 'Trainer Report', 'lms-trainer-report', 127, '/lms/trainer-report-page', 'fa-solid fa-chart-line', NOW(), NOW());

-- Insert permissions for Trainer Report menu into erp_permission table
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(129, 128, 'view', 'lms-trainer-report-view', NOW(), NOW()),
(130, 128, 'create', 'lms-trainer-report-create', NOW(), NOW()),
(131, 128, 'update', 'lms-trainer-report-update', NOW(), NOW()),
(132, 128, 'delete', 'lms-trainer-report-delete', NOW(), NOW());

-- =====================================================
-- Verification Queries (Optional - untuk testing)
-- =====================================================

-- Check if menu was inserted correctly
-- SELECT * FROM erp_menu WHERE id = 128;

-- Check if permissions were inserted correctly
-- SELECT * FROM erp_permission WHERE menu_id = 128;

-- Check the menu hierarchy
-- SELECT 
--     m.id,
--     m.name,
--     m.code,
--     m.parent_id,
--     p.name as parent_name
-- FROM erp_menu m
-- LEFT JOIN erp_menu p ON m.parent_id = p.id
-- WHERE m.id = 128;

-- =====================================================
-- Rollback Queries (Jika perlu menghapus)
-- =====================================================

-- DELETE FROM erp_permission WHERE menu_id = 128;
-- DELETE FROM erp_menu WHERE id = 128;
