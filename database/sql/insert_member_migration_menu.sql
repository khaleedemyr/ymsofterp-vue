-- Insert menu untuk Migrasi Data Member
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES ('Migrasi Data Member', 'member_migration', 138, '/member-migration', 'fa-solid fa-database', NOW(), NOW());

-- Get the menu_id yang baru saja di-insert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Migrasi Data Member
-- View permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'member_migration_view', NOW(), NOW());

-- Create permission (untuk migrasi)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'member_migration_create', NOW(), NOW());

