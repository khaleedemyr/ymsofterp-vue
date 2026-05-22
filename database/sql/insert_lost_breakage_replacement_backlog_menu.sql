-- Menu: Asset Replacement (permission lost_breakage atau lost_breakage_replacement_backlog_view)
-- Jika menu sudah ada dengan nama lama:
-- UPDATE erp_menu SET name = 'Asset Replacement' WHERE code = 'lost_breakage_replacement_backlog';
-- parent_id = 251 (Asset Management) — sesuaikan jika perlu

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Asset Replacement',
    'lost_breakage_replacement_backlog',
    251,
    '/lost-breakage/replacement-backlog',
    'fa-solid fa-list-check',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'lost_breakage_replacement_backlog' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'lost_breakage_replacement_backlog_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Salin akses view ke role yang sudah punya Lost & Breakage
INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
SELECT rp.role_id, p_new.id
FROM erp_role_permission rp
INNER JOIN erp_permission p_old ON p_old.id = rp.permission_id AND p_old.code = 'lost_breakage_view'
INNER JOIN erp_permission p_new ON p_new.code = 'lost_breakage_replacement_backlog_view';

COMMIT;

-- Sidebar AppLayout: code menu harus = lost_breakage_replacement_backlog (sama erp_menu.code di atas).
