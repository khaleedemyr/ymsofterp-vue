-- Insert QA Guidance menu and permissions into erp_menu and erp_permission tables

-- Insert QA Guidance menu under Quality Assurance group
-- First, get the parent_id for Quality Assurance group
SET @quality_assurance_id = (SELECT id FROM erp_menu WHERE code = 'quality_assurance' LIMIT 1);

-- Insert QA Guidance menu under Quality Assurance group
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('QA Guidance', 'qa_guidances', @quality_assurance_id, '/qa-guidances', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Get the menu_id for QA Guidance
SET @qa_guidances_menu_id = LAST_INSERT_ID();

-- Insert permissions for QA Guidance menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@qa_guidances_menu_id, 'view', 'qa_guidances_view', NOW(), NOW()),
(@qa_guidances_menu_id, 'create', 'qa_guidances_create', NOW(), NOW()),
(@qa_guidances_menu_id, 'update', 'qa_guidances_update', NOW(), NOW()),
(@qa_guidances_menu_id, 'delete', 'qa_guidances_delete', NOW(), NOW());
