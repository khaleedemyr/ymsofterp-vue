-- =====================================================
-- Insert Cashflow Outlet Dashboard Menu and Permissions
-- =====================================================
-- Menu ini berada di menu utama (Main Menu) dengan parent_id = NULL
-- Eksekusi sekali untuk insert menu dan permissions

-- Insert menu untuk Cashflow Outlet Dashboard
INSERT INTO `erp_menu` (
    `name`, 
    `code`, 
    `parent_id`, 
    `route`, 
    `icon`, 
    `created_at`, 
    `updated_at`
) VALUES (
    'Cashflow Outlet Dashboard',
    'cashflow_outlet_dashboard',
    NULL,
    '/cashflow-outlet-dashboard',
    'fa-solid fa-chart-pie',
    NOW(),
    NOW()
);

-- Get the menu_id of the inserted menu
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Cashflow Outlet Dashboard
INSERT INTO `erp_permission` (
    `menu_id`, 
    `action`, 
    `code`, 
    `created_at`, 
    `updated_at`
) VALUES 
-- View permission
(@menu_id, 'view', 'cashflow_outlet_dashboard_view', NOW(), NOW()),
-- Create permission (for future use)
(@menu_id, 'create', 'cashflow_outlet_dashboard_create', NOW(), NOW()),
-- Update permission (for future use)
(@menu_id, 'update', 'cashflow_outlet_dashboard_update', NOW(), NOW()),
-- Delete permission (for future use)
(@menu_id, 'delete', 'cashflow_outlet_dashboard_delete', NOW(), NOW());

-- Verify the insert
SELECT 
    m.id as menu_id,
    m.name as menu_name,
    m.code as menu_code,
    m.parent_id,
    m.route,
    m.icon,
    COUNT(p.id) as permission_count,
    GROUP_CONCAT(p.action ORDER BY p.action SEPARATOR ', ') as permissions
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'cashflow_outlet_dashboard'
GROUP BY m.id, m.name, m.code, m.parent_id, m.route, m.icon;

