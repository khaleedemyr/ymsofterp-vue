-- =====================================================
-- Diagnosa: kenapa menu Broadcast WA tidak muncul di sidebar
-- Sidebar butuh: allowedMenus berisi 'wa_broadcast'
-- (permission action=view + erp_menu.code = wa_broadcast + role user)
-- =====================================================

-- 1) Menu harus code = wa_broadcast (BUKAN wa-broadcast)
SELECT id, code, name, route, parent_id
FROM erp_menu
WHERE code IN ('wa_broadcast', 'wa-broadcast');

-- 2) Permission view harus ada & menu_id cocok
SELECT m.code AS menu_code, p.id AS perm_id, p.action, p.code AS perm_code, p.menu_id
FROM erp_permission p
LEFT JOIN erp_menu m ON m.id = p.menu_id
WHERE p.code IN ('wa_broadcast_view', 'wa_broadcast_send')
   OR m.code = 'wa_broadcast';

-- 3) Role Anda (ganti USER_ID)
SET @uid = 1;

SELECT r.id AS role_id, r.name AS role_name, p.code AS permission_code, p.action, m.code AS menu_code
FROM users u
JOIN erp_user_role ur ON ur.user_id = u.id
JOIN erp_role r ON r.id = ur.role_id
JOIN erp_role_permission rp ON rp.role_id = r.id
JOIN erp_permission p ON p.id = rp.permission_id
LEFT JOIN erp_menu m ON m.id = p.menu_id
WHERE u.id = @uid
  AND (p.code LIKE 'wa_broadcast%' OR m.code = 'wa_broadcast');

-- 4) Simulasi allowedMenus (harus ada baris wa_broadcast)
SELECT DISTINCT m.code
FROM users u
JOIN erp_user_role ur ON ur.user_id = u.id
JOIN erp_role r ON ur.role_id = r.id
JOIN erp_role_permission rp ON rp.role_id = r.id
JOIN erp_permission p ON p.id = rp.permission_id
JOIN erp_menu m ON m.id = p.menu_id
WHERE u.id = @uid
  AND p.action = 'view'
  AND m.code = 'wa_broadcast';

-- =====================================================
-- Perbaikan cepat (jika permission view belum ke role)
-- =====================================================
/*
INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
SELECT ur.role_id, p.id
FROM erp_user_role ur
CROSS JOIN erp_permission p
WHERE ur.user_id = @uid
  AND p.code = 'wa_broadcast_view';
*/
