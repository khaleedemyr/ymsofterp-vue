-- Transfer Kepemilikan Aset — erp_menu + erp_permission (parent_id = 251, Asset Management)
-- Jalankan sekali di MySQL setelah asset_ownership_phase3.sql
-- Lalu assign permission ke role (erp_role_permission), contoh di bawah.

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Transfer Kepemilikan Aset',
    'asset_owner_transfer',
    251,
    '/asset-owner-transfers',
    'fa-solid fa-people-arrows',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @aot_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'asset_owner_transfer' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@aot_menu_id, 'view',    'asset_owner_transfer_view',    NOW(), NOW()),
(@aot_menu_id, 'create',  'asset_owner_transfer_create',  NOW(), NOW()),
(@aot_menu_id, 'update',  'asset_owner_transfer_edit',    NOW(), NOW()),
(@aot_menu_id, 'delete',  'asset_owner_transfer_delete',  NOW(), NOW()),
(@aot_menu_id, 'approve', 'asset_owner_transfer_approve', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;

-- Assign ke role (ganti :role_id):
-- INSERT IGNORE INTO erp_role_permission (role_id, permission_id)
-- SELECT :role_id, id FROM erp_permission
-- WHERE code IN (
--   'asset_owner_transfer_view',
--   'asset_owner_transfer_create',
--   'asset_owner_transfer_edit',
--   'asset_owner_transfer_delete',
--   'asset_owner_transfer_approve'
-- );
