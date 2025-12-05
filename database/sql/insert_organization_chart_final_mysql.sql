-- Final MySQL single execution query for Organization Chart Menu and Permissions
-- Parent ID: 106

-- Insert menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Struktur Organisasi', 'organization_chart', 106, '/organization-chart', 'fa-solid fa-sitemap', NOW(), NOW());

-- Insert permissions
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
VALUES 
((SELECT id FROM erp_menu WHERE code = 'organization_chart' AND parent_id = 106 ORDER BY id DESC LIMIT 1), 'view', 'organization_chart_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'organization_chart' AND parent_id = 106 ORDER BY id DESC LIMIT 1), 'create', 'organization_chart_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'organization_chart' AND parent_id = 106 ORDER BY id DESC LIMIT 1), 'update', 'organization_chart_update', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'organization_chart' AND parent_id = 106 ORDER BY id DESC LIMIT 1), 'delete', 'organization_chart_delete', NOW(), NOW());
