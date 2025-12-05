-- Insert Purchase Requisition Menu
-- This script adds the Purchase Requisition Ops menu to the system

-- Insert main menu
INSERT INTO menu_types (name, icon, route, order_number, is_active, created_at, updated_at) VALUES
('Purchase Requisition Ops', 'fas fa-shopping-cart', '/purchase-requisitions', 25, 1, NOW(), NOW());

-- Get the menu_type_id for Purchase Requisition Ops
SET @menu_type_id = LAST_INSERT_ID();

-- Insert submenu items
INSERT INTO menu_types (name, icon, route, parent_id, order_number, is_active, created_at, updated_at) VALUES
('Purchase Requisition List', 'fas fa-list', '/purchase-requisitions', @menu_type_id, 1, 1, NOW(), NOW()),
('Create Purchase Requisition', 'fas fa-plus', '/purchase-requisitions/create', @menu_type_id, 2, 1, NOW(), NOW());

-- Insert permissions for Purchase Requisition Ops
INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES
('purchase_requisitions.view', 'web', NOW(), NOW()),
('purchase_requisitions.create', 'web', NOW(), NOW()),
('purchase_requisitions.edit', 'web', NOW(), NOW()),
('purchase_requisitions.delete', 'web', NOW(), NOW()),
('purchase_requisitions.approve', 'web', NOW(), NOW()),
('purchase_requisitions.reject', 'web', NOW(), NOW()),
('purchase_requisitions.process', 'web', NOW(), NOW()),
('purchase_requisitions.complete', 'web', NOW(), NOW());

-- Get permission IDs
SET @view_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.view' LIMIT 1);
SET @create_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.create' LIMIT 1);
SET @edit_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.edit' LIMIT 1);
SET @delete_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.delete' LIMIT 1);
SET @approve_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.approve' LIMIT 1);
SET @reject_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.reject' LIMIT 1);
SET @process_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.process' LIMIT 1);
SET @complete_permission_id = (SELECT id FROM permissions WHERE name = 'purchase_requisitions.complete' LIMIT 1);

-- Assign permissions to admin role (assuming role ID 1 is admin)
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, 1),
(@create_permission_id, 1),
(@edit_permission_id, 1),
(@delete_permission_id, 1),
(@approve_permission_id, 1),
(@reject_permission_id, 1),
(@process_permission_id, 1),
(@complete_permission_id, 1);

-- Assign permissions to manager role (assuming role ID 2 is manager)
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, 2),
(@create_permission_id, 2),
(@edit_permission_id, 2),
(@approve_permission_id, 2),
(@reject_permission_id, 2),
(@process_permission_id, 2),
(@complete_permission_id, 2);

-- Assign basic permissions to user role (assuming role ID 3 is user)
INSERT INTO role_has_permissions (permission_id, role_id) VALUES
(@view_permission_id, 3),
(@create_permission_id, 3),
(@edit_permission_id, 3);
