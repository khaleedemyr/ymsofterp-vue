-- Insert Receiving Sheet menu and permission
-- Parent ID = 111 (Outlet Sales Report group)

-- Insert menu for Receiving Sheet
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at) 
VALUES ('Receiving Sheet', 'receiving_sheet', 111, '/report-receiving-sheet', 'fa-solid fa-receipt', NOW(), NOW());

-- Insert permission for Receiving Sheet (view action)
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) 
VALUES (
    (SELECT id FROM erp_menu WHERE code = 'receiving_sheet' LIMIT 1),
    'view',
    'receiving_sheet_view',
    NOW(), NOW()
); 