-- Query INSERT untuk Web Profile Menu dan Permissions
-- Parent ID = 8 (Sales & Marketing)

-- 1. Insert Menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Web Profile', 'web_profile', 8, '/web-profile', 'fa-solid fa-globe', NOW(), NOW());

-- 2. Insert Permissions (ganti {MENU_ID} dengan ID yang didapat dari query di atas)
-- Atau gunakan query berikut yang lebih aman dengan subquery:

-- Insert Permissions dengan subquery untuk mendapatkan menu_id
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id as menu_id,
    'view' as action,
    'web_profile_view' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu`
WHERE `code` = 'web_profile'
LIMIT 1;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id as menu_id,
    'create' as action,
    'web_profile_create' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu`
WHERE `code` = 'web_profile'
LIMIT 1;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id as menu_id,
    'update' as action,
    'web_profile_update' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu`
WHERE `code` = 'web_profile'
LIMIT 1;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id as menu_id,
    'delete' as action,
    'web_profile_delete' as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu`
WHERE `code` = 'web_profile'
LIMIT 1;

-- ATAU gunakan query sekali insert untuk semua permissions:
-- (Hapus query di atas dan gunakan yang ini jika ingin sekali insert)

-- INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
-- SELECT 
--     id as menu_id,
--     action,
--     code,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM `erp_menu`,
-- (SELECT 'view' as action, 'web_profile_view' as code
--  UNION ALL SELECT 'create', 'web_profile_create'
--  UNION ALL SELECT 'update', 'web_profile_update'
--  UNION ALL SELECT 'delete', 'web_profile_delete') as perms
-- WHERE `erp_menu`.`code` = 'web_profile';

