-- Insert QA Parameters menu and permissions into erp_menu and erp_permission tables

-- Insert QA Parameters menu under Quality Assurance group
-- First, get the parent_id for Quality Assurance group
SET @quality_assurance_id = (SELECT id FROM erp_menu WHERE code = 'quality_assurance' LIMIT 1);

-- Insert QA Parameters menu under Quality Assurance group
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('QA Parameters', 'qa_parameters', @quality_assurance_id, '/qa-parameters', 'fa-solid fa-cogs', NOW(), NOW());

-- Get the menu_id for QA Parameters
SET @qa_parameters_menu_id = LAST_INSERT_ID();

-- Insert permissions for QA Parameters menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@qa_parameters_menu_id, 'view', 'qa_parameters_view', NOW(), NOW()),
(@qa_parameters_menu_id, 'create', 'qa_parameters_create', NOW(), NOW()),
(@qa_parameters_menu_id, 'update', 'qa_parameters_update', NOW(), NOW()),
(@qa_parameters_menu_id, 'delete', 'qa_parameters_delete', NOW(), NOW());
