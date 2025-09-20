-- Insert complete Ops Management group dengan semua menu dan permissions
-- Pastikan ID yang digunakan tidak conflict dengan data yang sudah ada

-- 1. Insert group "Ops Management" ke erp_menu (jika belum ada)
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Ops Management', 'ops_management', NULL, NULL, 'fa-solid fa-cogs', NOW(), NOW());

-- 2. Insert menu "Master Daily Report" ke erp_menu
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(2, 'Master Daily Report', 'master_report', 1, '/master-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- 3. Insert menu "Daily Report" ke erp_menu
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(3, 'Daily Report', 'daily_report', 1, '/daily-report', 'fa-solid fa-clipboard-list', NOW(), NOW());

-- 4. Insert permissions untuk Master Daily Report
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(5, 2, 'view', 'master_report_view', NOW(), NOW()),
(6, 2, 'create', 'master_report_create', NOW(), NOW()),
(7, 2, 'update', 'master_report_update', NOW(), NOW()),
(8, 2, 'delete', 'master_report_delete', NOW(), NOW());

-- 5. Insert permissions untuk Daily Report
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(9, 3, 'view', 'daily_report_view', NOW(), NOW()),
(10, 3, 'create', 'daily_report_create', NOW(), NOW()),
(11, 3, 'update', 'daily_report_update', NOW(), NOW()),
(12, 3, 'delete', 'daily_report_delete', NOW(), NOW());

-- Verifikasi data yang telah diinsert
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    CASE 
        WHEN m.parent_id IS NULL THEN 'Group'
        ELSE 'Menu'
    END as type
FROM `erp_menu` m
WHERE m.code IN ('ops_management', 'master_report', 'daily_report')
ORDER BY m.parent_id, m.id;

-- Tampilkan semua permissions untuk Ops Management
SELECT 
    p.id,
    p.menu_id,
    p.action,
    p.code,
    m.name as menu_name,
    m.code as menu_code
FROM `erp_permission` p
JOIN `erp_menu` m ON p.menu_id = m.id
WHERE m.code IN ('master_report', 'daily_report')
ORDER BY m.code, p.action;
