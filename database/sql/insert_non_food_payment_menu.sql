-- Insert Non Food Payment Menu and Permissions
-- This script inserts the Non Food Payment menu and its permissions into erp_menu and erp_permission tables

-- Insert menu into erp_menu table
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES (
    'Non Food Payment',
    'non_food_payment',
    5,
    '/non-food-payments',
    'fa-solid fa-credit-card',
    NOW(),
    NOW()
);

-- Get the menu_id for the inserted menu
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions into erp_permission table
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'non_food_payment_view', NOW(), NOW()),
(@menu_id, 'create', 'non_food_payment_create', NOW(), NOW()),
(@menu_id, 'update', 'non_food_payment_update', NOW(), NOW()),
(@menu_id, 'delete', 'non_food_payment_delete', NOW(), NOW());
