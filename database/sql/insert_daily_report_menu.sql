-- Insert menu Daily Report ke erp_menu
-- Pastikan parent_id sudah ada untuk group "Ops Management"
-- Asumsi parent_id untuk "Ops Management" adalah 1 (sesuaikan dengan data yang ada)

INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(2, 'Daily Report', 'daily_report', 1, '/daily-report', 'fa-solid fa-clipboard-list', NOW(), NOW());

-- Insert permissions untuk Daily Report
-- Menu ID 2 adalah Daily Report (sesuaikan dengan ID yang diinsert di atas)

INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(5, 2, 'view', 'daily_report_view', NOW(), NOW()),
(6, 2, 'create', 'daily_report_create', NOW(), NOW()),
(7, 2, 'update', 'daily_report_update', NOW(), NOW()),
(8, 2, 'delete', 'daily_report_delete', NOW(), NOW());

-- Verifikasi data yang telah diinsert
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    COUNT(p.id) as permission_count
FROM `erp_menu` m
LEFT JOIN `erp_permission` p ON m.id = p.menu_id
WHERE m.code = 'daily_report'
GROUP BY m.id, m.name, m.code, m.parent_id, m.route, m.icon;

-- Tampilkan semua permissions untuk Daily Report
SELECT 
    p.id,
    p.menu_id,
    p.action,
    p.code,
    m.name as menu_name
FROM `erp_permission` p
JOIN `erp_menu` m ON p.menu_id = m.id
WHERE m.code = 'daily_report'
ORDER BY p.action;
