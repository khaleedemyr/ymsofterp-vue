-- Insert Ticketing System menu and permissions (Final version)

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

-- 3. Update parent_id for sub-menus (set parent to ticketing_system)
UPDATE `erp_menu` SET `parent_id` = (SELECT id FROM (SELECT id FROM erp_menu WHERE code = 'ticketing_system') AS temp) WHERE code IN ('tickets_list', 'tickets_create', 'tickets_my', 'tickets_assigned', 'tickets_reports');

-- 4. Insert permissions for All Tickets (tickets_list)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'create', 'tickets_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'update', 'tickets_edit', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'delete', 'tickets_delete', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'update', 'tickets_assign', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_list'), 'update', 'tickets_close', NOW(), NOW());

-- 5. Insert permissions for Create Ticket (tickets_create)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_create'), 'view', 'tickets_create', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_create'), 'create', 'tickets_create', NOW(), NOW());

-- 6. Insert permissions for My Tickets (tickets_my)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_my'), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_my'), 'update', 'tickets_edit', NOW(), NOW());

-- 7. Insert permissions for Assigned to Me (tickets_assigned)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned'), 'view', 'tickets_view', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned'), 'update', 'tickets_edit', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned'), 'update', 'tickets_assign', NOW(), NOW()),
((SELECT id FROM erp_menu WHERE code = 'tickets_assigned'), 'update', 'tickets_close', NOW(), NOW());

-- 8. Insert permissions for Ticket Reports (tickets_reports)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
((SELECT id FROM erp_menu WHERE code = 'tickets_reports'), 'view', 'tickets_reports', NOW(), NOW());
