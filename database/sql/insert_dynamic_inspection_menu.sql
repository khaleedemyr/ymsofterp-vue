-- Insert Dynamic Inspection Menu and Permissions
-- Created: 2025-10-01

-- Insert menu untuk Dynamic Inspection
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(107, 'Dynamic Inspection', 'dynamic_inspection', 106, '/dynamic-inspections', 'fa-solid fa-clipboard-check', NOW(), NOW());

-- Insert permissions untuk Dynamic Inspection
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(427, 107, 'view', 'dynamic_inspection_view', NOW(), NOW()),
(428, 107, 'create', 'dynamic_inspection_create', NOW(), NOW()),
(429, 107, 'update', 'dynamic_inspection_update', NOW(), NOW()),
(430, 107, 'delete', 'dynamic_inspection_delete', NOW(), NOW());
