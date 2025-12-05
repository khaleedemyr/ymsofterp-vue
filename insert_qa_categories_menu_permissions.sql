-- Insert QA Categories menu and permissions into erp_menu and erp_permission tables

-- Insert Quality Assurance group menu (parent)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Quality Assurance', 'quality_assurance', NULL, NULL, 'fa-solid fa-shield-halved', NOW(), NOW());

-- Get the parent_id for Quality Assurance group
SET @quality_assurance_id = LAST_INSERT_ID();

-- Insert QA Categories menu under Quality Assurance group
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('QA Categories', 'qa_categories', @quality_assurance_id, '/qa-categories', 'fa-solid fa-clipboard-list', NOW(), NOW());

-- Get the menu_id for QA Categories
SET @qa_categories_menu_id = LAST_INSERT_ID();

-- Insert permissions for QA Categories menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@qa_categories_menu_id, 'view', 'qa_categories_view', NOW(), NOW()),
(@qa_categories_menu_id, 'create', 'qa_categories_create', NOW(), NOW()),
(@qa_categories_menu_id, 'update', 'qa_categories_update', NOW(), NOW()),
(@qa_categories_menu_id, 'delete', 'qa_categories_delete', NOW(), NOW());
