-- Insert Purchase Order Ops Menu to ERP Menu System
-- This script adds the Purchase Order Ops menu to the existing ERP menu structure

-- Insert main menu Purchase Order Ops
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Purchase Order Ops', 'purchase_order_ops', 6, '/po-ops', 'fas fa-file-invoice', NOW(), NOW());

-- Get the menu_id for Purchase Order Ops
SET @purchase_order_ops_menu_id = LAST_INSERT_ID();

-- Insert permissions for main menu (CRUD operations)
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@purchase_order_ops_menu_id, 'view', 'purchase_order_ops_view', NOW(), NOW()),
(@purchase_order_ops_menu_id, 'create', 'purchase_order_ops_create', NOW(), NOW()),
(@purchase_order_ops_menu_id, 'update', 'purchase_order_ops_update', NOW(), NOW()),
(@purchase_order_ops_menu_id, 'delete', 'purchase_order_ops_delete', NOW(), NOW());

-- Insert submenu items
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Purchase Order Ops List', 'purchase_order_ops_list', @purchase_order_ops_menu_id, '/po-ops', 'fas fa-list', NOW(), NOW()),
('Create Purchase Order Ops', 'purchase_order_ops_create_form', @purchase_order_ops_menu_id, '/po-ops/create', 'fas fa-plus', NOW(), NOW());

-- Get submenu IDs
SET @po_ops_list_menu_id = LAST_INSERT_ID() - 1;
SET @po_ops_create_menu_id = LAST_INSERT_ID();

-- Insert permissions for submenu items
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@po_ops_list_menu_id, 'view', 'purchase_order_ops_list_view', NOW(), NOW()),
(@po_ops_create_menu_id, 'create', 'purchase_order_ops_create_form_create', NOW(), NOW());

-- Insert additional action permissions for Purchase Order Ops
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@purchase_order_ops_menu_id, 'view', 'purchase_order_ops_approve', NOW(), NOW()),
(@purchase_order_ops_menu_id, 'view', 'purchase_order_ops_reject', NOW(), NOW()),
(@purchase_order_ops_menu_id, 'view', 'purchase_order_ops_print', NOW(), NOW());
