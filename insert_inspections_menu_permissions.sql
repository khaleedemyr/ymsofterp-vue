-- Insert Inspections menu and permissions into erp_menu and erp_permission tables

-- Insert Inspections menu under Quality Assurance group
-- First, get the parent_id for Quality Assurance group
SET @quality_assurance_id = (SELECT id FROM erp_menu WHERE code = 'quality_assurance' LIMIT 1);

-- Insert Inspections menu under Quality Assurance group
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Inspections', 'inspections', @quality_assurance_id, '/inspections', 'fa-solid fa-camera', NOW(), NOW());

-- Get the menu_id for Inspections
SET @inspections_menu_id = LAST_INSERT_ID();

-- Insert permissions for Inspections menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@inspections_menu_id, 'view', 'inspections_view', NOW(), NOW()),
(@inspections_menu_id, 'create', 'inspections_create', NOW(), NOW()),
(@inspections_menu_id, 'update', 'inspections_update', NOW(), NOW()),
(@inspections_menu_id, 'delete', 'inspections_delete', NOW(), NOW());
