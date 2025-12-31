-- Query INSERT sekali untuk Web Profile Menu dan Permissions
-- Parent ID = 8 (Sales & Marketing)

-- Insert Menu dan dapatkan ID-nya
SET @menu_id = NULL;

INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Web Profile', 'web_profile', 8, '/web-profile', 'fa-solid fa-globe', NOW(), NOW());

SET @menu_id = LAST_INSERT_ID();

-- Insert semua permissions sekaligus
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'web_profile_view', NOW(), NOW()),
(@menu_id, 'create', 'web_profile_create', NOW(), NOW()),
(@menu_id, 'update', 'web_profile_update', NOW(), NOW()),
(@menu_id, 'delete', 'web_profile_delete', NOW(), NOW());

