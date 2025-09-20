-- Insert ticketing system menu to erp_menu table

-- Insert main Ticketing System menu
INSERT IGNORE INTO `erp_menu` (`id`, `menu_name`, `menu_url`, `menu_icon`, `menu_parent`, `menu_order`, `menu_status`, `created_at`, `updated_at`) VALUES
('ticketing_system', 'Ticketing System', '#', 'fa-solid fa-ticket-alt', NULL, 15, 'A', NOW(), NOW());

-- Insert sub-menus for Ticketing System
INSERT IGNORE INTO `erp_menu` (`id`, `menu_name`, `menu_url`, `menu_icon`, `menu_parent`, `menu_order`, `menu_status`, `created_at`, `updated_at`) VALUES
('tickets_list', 'All Tickets', '/tickets', 'fa-solid fa-list', 'ticketing_system', 1, 'A', NOW(), NOW()),
('tickets_create', 'Create Ticket', '/tickets/create', 'fa-solid fa-plus', 'ticketing_system', 2, 'A', NOW(), NOW()),
('tickets_my', 'My Tickets', '/tickets/my', 'fa-solid fa-user', 'ticketing_system', 3, 'A', NOW(), NOW()),
('tickets_assigned', 'Assigned to Me', '/tickets/assigned', 'fa-solid fa-user-check', 'ticketing_system', 4, 'A', NOW(), NOW()),
('tickets_reports', 'Reports', '/tickets/reports', 'fa-solid fa-chart-bar', 'ticketing_system', 5, 'A', NOW(), NOW());

-- Insert permissions for ticketing system
INSERT IGNORE INTO `erp_permission` (`id`, `permission_name`, `permission_code`, `permission_description`, `created_at`, `updated_at`) VALUES
('tickets_view', 'View Tickets', 'tickets_view', 'Can view tickets', NOW(), NOW()),
('tickets_create', 'Create Tickets', 'tickets_create', 'Can create new tickets', NOW(), NOW()),
('tickets_edit', 'Edit Tickets', 'tickets_edit', 'Can edit tickets', NOW(), NOW()),
('tickets_delete', 'Delete Tickets', 'tickets_delete', 'Can delete tickets', NOW(), NOW()),
('tickets_assign', 'Assign Tickets', 'tickets_assign', 'Can assign tickets to users', NOW(), NOW()),
('tickets_close', 'Close Tickets', 'tickets_close', 'Can close/resolve tickets', NOW(), NOW()),
('tickets_reports', 'View Ticket Reports', 'tickets_reports', 'Can view ticket reports and analytics', NOW(), NOW());
