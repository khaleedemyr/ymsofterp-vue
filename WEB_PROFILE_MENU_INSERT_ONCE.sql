-- Query INSERT sekali untuk Web Profile Menu dan Permissions
-- Parent ID = 8 (Sales & Marketing)
-- Jalankan query ini sekali untuk insert menu dan semua permissions

-- Insert Menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Web Profile', 'web_profile', 8, '/web-profile', 'fa-solid fa-globe', NOW(), NOW());

-- Insert Permissions (menggunakan LAST_INSERT_ID() untuk mendapatkan menu_id)
-- Catatan: LAST_INSERT_ID() akan mengembalikan nilai yang sama untuk semua baris dalam satu statement
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT LAST_INSERT_ID(), 'view', 'web_profile_view', NOW(), NOW()
UNION ALL SELECT LAST_INSERT_ID(), 'create', 'web_profile_create', NOW(), NOW()
UNION ALL SELECT LAST_INSERT_ID(), 'update', 'web_profile_update', NOW(), NOW()
UNION ALL SELECT LAST_INSERT_ID(), 'delete', 'web_profile_delete', NOW(), NOW();

