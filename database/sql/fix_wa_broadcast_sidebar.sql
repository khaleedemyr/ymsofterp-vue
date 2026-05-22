-- Perbaikan sidebar Broadcast WA (jalankan sekali di server production)
-- Akar masalah yang ditemukan dari diagnose PHP:
-- 1) erp_permission VIEW pakai code 'wa_broadcast' (salah) bukan 'wa_broadcast_view'
-- 2) INSERT role sync pakai created_at/updated_at padahal tabel erp_role_permission hanya (role_id, permission_id)

START TRANSACTION;

SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'wa_broadcast' LIMIT 1);

-- Pastikan menu ada
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at)
SELECT 'Broadcast WhatsApp', 'wa_broadcast', 138, '/crm/wa-broadcast', 'fa-brands fa-whatsapp', NOW(), NOW()
FROM DUAL
WHERE @menu_id IS NULL;

SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'wa_broadcast' LIMIT 1);

-- Permission view + create (update code jika UNIQUE menu_id+action)
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
VALUES (@menu_id, 'view', 'wa_broadcast_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE code = VALUES(code), updated_at = NOW();

INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
VALUES (@menu_id, 'create', 'wa_broadcast_send', NOW(), NOW())
ON DUPLICATE KEY UPDATE code = VALUES(code), updated_at = NOW();

UPDATE erp_permission p
INNER JOIN erp_menu m ON m.id = p.menu_id AND m.code = 'wa_broadcast'
SET p.code = 'wa_broadcast_view', p.updated_at = NOW()
WHERE p.action = 'view' AND p.code = 'wa_broadcast';

-- Hapus duplikat view (sisakan id terkecil)
DELETE p1 FROM erp_permission p1
INNER JOIN erp_permission p2
    ON p1.menu_id = p2.menu_id AND p1.action = 'view' AND p2.action = 'view' AND p1.id > p2.id
INNER JOIN erp_menu m ON m.id = p1.menu_id AND m.code = 'wa_broadcast';

SET @view_perm_id = (
    SELECT id FROM erp_permission
    WHERE menu_id = @menu_id AND action = 'view' AND code = 'wa_broadcast_view'
    LIMIT 1
);

-- Sync role dari inbox / IG comments
INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
SELECT rp.role_id, @view_perm_id
FROM erp_role_permission rp
INNER JOIN erp_permission p_old ON p_old.id = rp.permission_id
    AND p_old.code IN ('omnichannel_inbox_view', 'instagram_comments_view');

COMMIT;

-- Verifikasi
SELECT 'menu' AS cek, id, code, route FROM erp_menu WHERE code = 'wa_broadcast';
SELECT 'permission' AS cek, id, action, code, menu_id FROM erp_permission WHERE menu_id = @menu_id;
