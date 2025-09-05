-- Insert menu untuk Outlet Food Return
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Outlet Food Return', 'outlet_food_return', 4, '/outlet-food-return', 'fa-solid fa-undo', NOW(), NOW());

-- Get the menu_id yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Outlet Food Return
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
(@menu_id, 'view', 'outlet_food_return_view', NOW(), NOW()),
(@menu_id, 'create', 'outlet_food_return_create', NOW(), NOW()),
(@menu_id, 'update', 'outlet_food_return_update', NOW(), NOW()),
(@menu_id, 'delete', 'outlet_food_return_delete', NOW(), NOW());

-- Verifikasi data yang diinsert
SELECT 
    m.id as menu_id,
    m.name as menu_name,
    m.code as menu_code,
    m.parent_id,
    m.route,
    m.icon,
    p.id as permission_id,
    p.action,
    p.code as permission_code
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'outlet_food_return'
ORDER BY p.action;
