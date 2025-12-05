-- Insert Ticketing System menu and permissions to erp_menu and erp_permission tables

-- 1. Insert main Ticketing System menu
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Ticketing System', 'ticketing_system', NULL, '/tickets', 'fa-solid fa-ticket-alt', NOW(), NOW());

-- 2. Get the menu ID for Ticketing System (assuming it's the last inserted)
SET @ticketing_menu_id = LAST_INSERT_ID();

-- 3. Insert sub-menus for Ticketing System
INSERT IGNORE INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('All Tickets', 'tickets_list', @ticketing_menu_id, '/tickets', 'fa-solid fa-list', NOW(), NOW()),
('Create Ticket', 'tickets_create', @ticketing_menu_id, '/tickets/create', 'fa-solid fa-plus', NOW(), NOW()),
('My Tickets', 'tickets_my', @ticketing_menu_id, '/tickets/my', 'fa-solid fa-user', NOW(), NOW()),
('Assigned to Me', 'tickets_assigned', @ticketing_menu_id, '/tickets/assigned', 'fa-solid fa-user-check', NOW(), NOW()),
('Ticket Reports', 'tickets_reports', @ticketing_menu_id, '/tickets/reports', 'fa-solid fa-chart-bar', NOW(), NOW());

-- 4. Get menu IDs for permissions
SET @tickets_list_id = (SELECT id FROM erp_menu WHERE code = 'tickets_list' LIMIT 1);
SET @tickets_create_id = (SELECT id FROM erp_menu WHERE code = 'tickets_create' LIMIT 1);
SET @tickets_my_id = (SELECT id FROM erp_menu WHERE code = 'tickets_my' LIMIT 1);
SET @tickets_assigned_id = (SELECT id FROM erp_menu WHERE code = 'tickets_assigned' LIMIT 1);
SET @tickets_reports_id = (SELECT id FROM erp_menu WHERE code = 'tickets_reports' LIMIT 1);

-- 5. Insert permissions for All Tickets (tickets_list)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_list_id, 'view', 'tickets_view', NOW(), NOW()),
(@tickets_list_id, 'create', 'tickets_create', NOW(), NOW()),
(@tickets_list_id, 'update', 'tickets_edit', NOW(), NOW()),
(@tickets_list_id, 'delete', 'tickets_delete', NOW(), NOW());

-- 6. Insert permissions for Create Ticket (tickets_create)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_create_id, 'view', 'tickets_create', NOW(), NOW()),
(@tickets_create_id, 'create', 'tickets_create', NOW(), NOW());

-- 7. Insert permissions for My Tickets (tickets_my)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_my_id, 'view', 'tickets_view', NOW(), NOW()),
(@tickets_my_id, 'update', 'tickets_edit', NOW(), NOW());

-- 8. Insert permissions for Assigned to Me (tickets_assigned)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_assigned_id, 'view', 'tickets_view', NOW(), NOW()),
(@tickets_assigned_id, 'update', 'tickets_edit', NOW(), NOW()),
(@tickets_assigned_id, 'update', 'tickets_assign', NOW(), NOW()),
(@tickets_assigned_id, 'update', 'tickets_close', NOW(), NOW());

-- 9. Insert permissions for Ticket Reports (tickets_reports)
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_reports_id, 'view', 'tickets_reports', NOW(), NOW());

-- 10. Additional permissions for ticket management
INSERT IGNORE INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@tickets_list_id, 'update', 'tickets_assign', NOW(), NOW()),
(@tickets_list_id, 'update', 'tickets_close', NOW(), NOW());
