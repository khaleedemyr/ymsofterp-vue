-- Insert Purchase Requisition Ops Menu to ERP Menu System
-- This script adds the Purchase Requisition Ops menu to the existing ERP menu structure

-- Insert main menu Purchase Requisition Ops
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) VALUES
('Purchase Requisition Ops', 'purchase_requisition_ops', 184, '/purchase-requisitions', 'fas fa-shopping-cart', NOW(), NOW());

-- Get the menu_id for Purchase Requisition Ops
SET @purchase_requisition_menu_id = LAST_INSERT_ID();

-- Insert permissions for main menu (CRUD operations)
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@purchase_requisition_menu_id, 'view', 'purchase_requisition_ops_view', NOW(), NOW()),
(@purchase_requisition_menu_id, 'create', 'purchase_requisition_ops_create', NOW(), NOW()),
(@purchase_requisition_menu_id, 'update', 'purchase_requisition_ops_update', NOW(), NOW()),
(@purchase_requisition_menu_id, 'delete', 'purchase_requisition_ops_delete', NOW(), NOW());
