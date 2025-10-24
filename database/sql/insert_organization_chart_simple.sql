-- Simple insert for Organization Chart Menu and Permissions
-- Parent ID: 106

INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Struktur Organisasi', 'organization_chart', 106, '/organization-chart', 'fa-solid fa-sitemap', NOW(), NOW());

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
VALUES 
(LAST_INSERT_ID(), 'view', 'organization_chart_view', NOW(), NOW()),
(LAST_INSERT_ID(), 'create', 'organization_chart_create', NOW(), NOW()),
(LAST_INSERT_ID(), 'update', 'organization_chart_update', NOW(), NOW()),
(LAST_INSERT_ID(), 'delete', 'organization_chart_delete', NOW(), NOW());
