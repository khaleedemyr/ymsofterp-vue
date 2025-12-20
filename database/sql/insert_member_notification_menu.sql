-- Insert menu untuk Kirim Notifikasi Member
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES ('Kirim Notifikasi Member', 'member_notification', 138, '/member-notification', 'fa-solid fa-paper-plane', NOW(), NOW());

-- Get the menu_id yang baru saja di-insert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk menu Kirim Notifikasi Member
-- View permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'member_notification_view', NOW(), NOW());

-- Create permission (untuk kirim notifikasi)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'member_notification_create', NOW(), NOW());

