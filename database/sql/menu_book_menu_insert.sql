-- Query untuk insert menu Menu Book ke erp_menu dan erp_permission
-- Jalankan query ini sekali saja

-- Insert ke erp_menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Menu Book',
    'menu_book',
    8,
    '/menu-book',
    'fa-solid fa-book-open',
    NOW(),
    NOW()
);

-- Ambil ID menu yang baru saja diinsert
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Menu Book
-- View permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'menu_book_view', NOW(), NOW());

-- Create permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'create', 'menu_book_create', NOW(), NOW());

-- Update permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'update', 'menu_book_update', NOW(), NOW());

-- Delete permission
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'delete', 'menu_book_delete', NOW(), NOW());

