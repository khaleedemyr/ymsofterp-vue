-- Insert Organization Chart Menu and Permissions
-- Parent ID: 106 (Human Resource section)

-- Insert menu for Organization Chart
INSERT INTO `erp_menu` (
    `name`, 
    `code`, 
    `parent_id`, 
    `route`, 
    `icon`, 
    `created_at`, 
    `updated_at`
) VALUES (
    'Struktur Organisasi',
    'organization_chart',
    106,
    '/organization-chart',
    'fa-solid fa-sitemap',
    NOW(),
    NOW()
);

-- Get the menu_id of the inserted menu
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Organization Chart
INSERT INTO `erp_permission` (
    `menu_id`, 
    `action`, 
    `code`, 
    `created_at`, 
    `updated_at`
) VALUES 
-- View permission
(@menu_id, 'view', 'organization_chart_view', NOW(), NOW()),
-- Create permission (for future use)
(@menu_id, 'create', 'organization_chart_create', NOW(), NOW()),
-- Update permission (for future use)
(@menu_id, 'update', 'organization_chart_update', NOW(), NOW()),
-- Delete permission (for future use)
(@menu_id, 'delete', 'organization_chart_delete', NOW(), NOW());

-- Verify the insert
SELECT 
    m.id as menu_id,
    m.name as menu_name,
    m.code as menu_code,
    m.parent_id,
    m.route,
    m.icon,
    COUNT(p.id) as permission_count
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'organization_chart'
GROUP BY m.id, m.name, m.code, m.parent_id, m.route, m.icon;
