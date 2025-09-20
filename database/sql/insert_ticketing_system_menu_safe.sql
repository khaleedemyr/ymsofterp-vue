-- Insert Ticketing System menu and permissions (Safe version)

-- 1. Insert main Ticketing System menu
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Ticketing System', 'ticketing_system', NULL, '/tickets', 'fa-solid fa-ticket-alt', NOW(), NOW());

-- 2. Insert sub-menus for Ticketing System
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('All Tickets', 'tickets_list', NULL, '/tickets', 'fa-solid fa-list', NOW(), NOW()),
('Create Ticket', 'tickets_create', NULL, '/tickets/create', 'fa-solid fa-plus', NOW(), NOW()),
('My Tickets', 'tickets_my', NULL, '/tickets/my', 'fa-solid fa-user', NOW(), NOW()),
('Assigned to Me', 'tickets_assigned', NULL, '/tickets/assigned', 'fa-solid fa-user-check', NOW(), NOW()),
('Ticket Reports', 'tickets_reports', NULL, '/tickets/reports', 'fa-solid fa-chart-bar', NOW(), NOW());

-- 3. Update parent_id for sub-menus
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM erp_menu WHERE code = 'ticketing_system' LIMIT 1) WHERE code = 'tickets_list';
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM erp_menu WHERE code = 'ticketing_system' LIMIT 1) WHERE code = 'tickets_create';
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM erp_menu WHERE code = 'ticketing_system' LIMIT 1) WHERE code = 'tickets_my';
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM erp_menu WHERE code = 'ticketing_system' LIMIT 1) WHERE code = 'tickets_assigned';
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM erp_menu WHERE code = 'ticketing_system' LIMIT 1) WHERE code = 'tickets_reports';

-- 4. Insert permissions for All Tickets
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'create', 'tickets_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'update', 'tickets_edit', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'delete', 'tickets_delete', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'update', 'tickets_assign', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1), 'update', 'tickets_close', NOW(), NOW());

-- 5. Insert permissions for Create Ticket
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_create' LIMIT 1), 'view', 'tickets_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_create' LIMIT 1), 'create', 'tickets_create', NOW(), NOW());

-- 6. Insert permissions for My Tickets
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_my' LIMIT 1), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_my' LIMIT 1), 'update', 'tickets_edit', NOW(), NOW());

-- 7. Insert permissions for Assigned to Me
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned' LIMIT 1), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned' LIMIT 1), 'update', 'tickets_edit', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned' LIMIT 1), 'update', 'tickets_assign', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned' LIMIT 1), 'update', 'tickets_close', NOW(), NOW());

-- 8. Insert permissions for Ticket Reports
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_reports' LIMIT 1), 'view', 'tickets_reports', NOW(), NOW());
