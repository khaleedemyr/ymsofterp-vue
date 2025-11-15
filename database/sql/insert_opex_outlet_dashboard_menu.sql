-- Insert Opex Outlet Dashboard Menu
-- Parent ID: 111 (Outlet Report)

-- Insert into erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES (
    'Opex Outlet Dashboard',
    'opex_outlet_dashboard',
    111,
    '/opex-outlet-dashboard',
    'fa-solid fa-chart-pie',
    NOW(),
    NOW()
);

-- Get the menu_id that was just inserted
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Opex Outlet Dashboard
-- View permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (
    @menu_id,
    'view',
    'opex_outlet_dashboard_view',
    NOW(),
    NOW()
);

-- Create permission (if needed)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (
    @menu_id,
    'create',
    'opex_outlet_dashboard_create',
    NOW(),
    NOW()
);

-- Update permission (if needed)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (
    @menu_id,
    'update',
    'opex_outlet_dashboard_update',
    NOW(),
    NOW()
);

-- Delete permission (if needed)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (
    @menu_id,
    'delete',
    'opex_outlet_dashboard_delete',
    NOW(),
    NOW()
);

-- Verify the insert
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    COUNT(p.id) as permission_count
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'opex_outlet_dashboard'
GROUP BY m.id, m.name, m.code, m.parent_id, m.route, m.icon;

