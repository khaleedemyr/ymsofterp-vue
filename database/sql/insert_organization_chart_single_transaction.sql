-- Single transaction execution for Organization Chart Menu and Permissions
-- Parent ID: 106

START TRANSACTION;

-- Insert menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Struktur Organisasi', 'organization_chart', 106, '/organization-chart', 'fa-solid fa-sitemap', NOW(), NOW());

-- Get the inserted menu ID
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
VALUES 
(@menu_id, 'view', 'organization_chart_view', NOW(), NOW()),
(@menu_id, 'create', 'organization_chart_create', NOW(), NOW()),
(@menu_id, 'update', 'organization_chart_update', NOW(), NOW()),
(@menu_id, 'delete', 'organization_chart_delete', NOW(), NOW());

COMMIT;
