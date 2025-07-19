-- ========================================
-- INSERT DATA MENU CRM
-- ========================================

-- Insert parent menu CRM
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('CRM', 'crm', NULL, '#', 'fa-solid fa-handshake', NOW(), NOW());

-- Get the parent CRM menu ID
SET @crm_parent_id = LAST_INSERT_ID();

-- Insert sub-menu CRM
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Data Member', 'crm_members', @crm_parent_id, '/members', 'fa-solid fa-users', NOW(), NOW()),
('Dashboard CRM', 'crm_dashboard', @crm_parent_id, '/crm/dashboard', 'fa-solid fa-chart-line', NOW(), NOW()),
('Customer Analytics', 'crm_analytics', @crm_parent_id, '/crm/analytics', 'fa-solid fa-chart-pie', NOW(), NOW()),
('Member Reports', 'crm_reports', @crm_parent_id, '/crm/reports', 'fa-solid fa-file-lines', NOW(), NOW());

-- Get menu IDs for permissions
SET @crm_members_id = (SELECT id FROM erp_menu WHERE code = 'crm_members' LIMIT 1);
SET @crm_dashboard_id = (SELECT id FROM erp_menu WHERE code = 'crm_dashboard' LIMIT 1);
SET @crm_analytics_id = (SELECT id FROM erp_menu WHERE code = 'crm_analytics' LIMIT 1);
SET @crm_reports_id = (SELECT id FROM erp_menu WHERE code = 'crm_reports' LIMIT 1);

-- ========================================
-- INSERT PERMISSIONS FOR CRM MEMBERS
-- ========================================

-- Permissions for Data Member
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@crm_members_id, 'view', 'crm_members_view', NOW(), NOW()),
(@crm_members_id, 'create', 'crm_members_create', NOW(), NOW()),
(@crm_members_id, 'update', 'crm_members_update', NOW(), NOW()),
(@crm_members_id, 'delete', 'crm_members_delete', NOW(), NOW());

-- Permissions for Dashboard CRM
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@crm_dashboard_id, 'view', 'crm_dashboard_view', NOW(), NOW());

-- Permissions for Customer Analytics
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@crm_analytics_id, 'view', 'crm_analytics_view', NOW(), NOW());

-- Permissions for Member Reports
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@crm_reports_id, 'view', 'crm_reports_view', NOW(), NOW()),
(@crm_reports_id, 'create', 'crm_reports_create', NOW(), NOW());

-- ========================================
-- VERIFICATION QUERIES
-- ========================================

-- Check inserted menus
SELECT 'Inserted Menus:' as info;
SELECT id, name, code, parent_id, route, icon FROM erp_menu WHERE code LIKE 'crm%' ORDER BY parent_id, id;

-- Check inserted permissions
SELECT 'Inserted Permissions:' as info;
SELECT p.id, m.name as menu_name, p.action, p.code 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE m.code LIKE 'crm%' 
ORDER BY m.name, p.action;

-- ========================================
-- ALTERNATIVE: INSERT WITH SPECIFIC IDs
-- ========================================

/*
-- If you need to specify specific IDs, use this version:

-- Insert parent menu CRM with specific ID
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(100, 'CRM', 'crm', NULL, '#', 'fa-solid fa-handshake', NOW(), NOW());

-- Insert sub-menu CRM with specific IDs
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(101, 'Data Member', 'crm_members', 100, '/members', 'fa-solid fa-users', NOW(), NOW()),
(102, 'Dashboard CRM', 'crm_dashboard', 100, '/crm/dashboard', 'fa-solid fa-chart-line', NOW(), NOW()),
(103, 'Customer Analytics', 'crm_analytics', 100, '/crm/analytics', 'fa-solid fa-chart-pie', NOW(), NOW()),
(104, 'Member Reports', 'crm_reports', 100, '/crm/reports', 'fa-solid fa-file-lines', NOW(), NOW());

-- Insert permissions with specific IDs
INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
-- Data Member permissions
(1001, 101, 'view', 'crm_members_view', NOW(), NOW()),
(1002, 101, 'create', 'crm_members_create', NOW(), NOW()),
(1003, 101, 'update', 'crm_members_update', NOW(), NOW()),
(1004, 101, 'delete', 'crm_members_delete', NOW(), NOW()),

-- Dashboard CRM permissions
(1005, 102, 'view', 'crm_dashboard_view', NOW(), NOW()),

-- Customer Analytics permissions
(1006, 103, 'view', 'crm_analytics_view', NOW(), NOW()),

-- Member Reports permissions
(1007, 104, 'view', 'crm_reports_view', NOW(), NOW()),
(1008, 104, 'create', 'crm_reports_create', NOW(), NOW());
*/

-- ========================================
-- CLEANUP (if needed)
-- ========================================

/*
-- To remove CRM menu and permissions (uncomment if needed):
-- DELETE FROM erp_permission WHERE menu_id IN (SELECT id FROM erp_menu WHERE code LIKE 'crm%');
-- DELETE FROM erp_menu WHERE code LIKE 'crm%';
*/ 