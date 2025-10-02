-- Insert Coaching menu into erp_menu table
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Coaching', 'coaching', 106, '/coaching', 'fa-solid fa-user-graduate', NOW(), NOW());

-- Get the menu_id for the inserted Coaching menu
SET @coaching_menu_id = LAST_INSERT_ID();

-- Insert permissions for Coaching menu into erp_permission table
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @coaching_menu_id, 'view', 'coaching_view', NOW(), NOW()),
(NULL, @coaching_menu_id, 'create', 'coaching_create', NOW(), NOW()),
(NULL, @coaching_menu_id, 'update', 'coaching_update', NOW(), NOW()),
(NULL, @coaching_menu_id, 'delete', 'coaching_delete', NOW(), NOW());
