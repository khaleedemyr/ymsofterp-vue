-- Insert menu untuk Inject Point Manual
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES ('Inject Point Manual', 'manual_point', 138, '/manual-point', 'fa-solid fa-syringe', NOW(), NOW());

-- Get the menu_id yang baru saja di-insert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Inject Point Manual
-- View permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'manual_point_view', NOW(), NOW());

-- Create permission (untuk inject point)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'manual_point_create', NOW(), NOW());

