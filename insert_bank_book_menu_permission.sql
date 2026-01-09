-- Query untuk insert menu dan permission Buku Bank
-- Jalankan query ini langsung di database
-- parent_id = 5 (HO Finance group)

-- Insert menu Buku Bank
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Buku Bank',
    'bank_books',
    5,
    '/bank-books',
    'fa-solid fa-book',
    NOW(),
    NOW()
);

-- Get the menu_id that was just inserted
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions for Buku Bank
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES
    (@menu_id, 'view', 'bank_books_view', NOW(), NOW()),
    (@menu_id, 'create', 'bank_books_create', NOW(), NOW()),
    (@menu_id, 'update', 'bank_books_update', NOW(), NOW()),
    (@menu_id, 'delete', 'bank_books_delete', NOW(), NOW());
