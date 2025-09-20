-- Insert Ticketing System menu and permissions (Simple version - 1 menu only)

-- 1. Insert main Ticketing System menu
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Ticketing System', 'tickets', NULL, '/tickets', 'fa-solid fa-ticket-alt', NOW(), NOW());

-- 2. Get the menu ID for Ticketing System
SET @tickets_menu_id = (SELECT id FROM erp_menu WHERE code = 'tickets' LIMIT 1);

-- 3. Insert permissions for Ticketing System
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_menu_id, 'view', 'tickets_view', NOW(), NOW()),
(@tickets_menu_id, 'create', 'tickets_create', NOW(), NOW()),
(@tickets_menu_id, 'update', 'tickets_edit', NOW(), NOW()),
(@tickets_menu_id, 'delete', 'tickets_delete', NOW(), NOW());
