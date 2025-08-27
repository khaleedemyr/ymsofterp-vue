-- Insert menu untuk Outlet Rejection
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(NULL, 'Outlet Rejection', 'outlet_rejection', 6, '/outlet-rejections', 'fas fa-undo', NOW(), NOW());

-- Ambil ID menu yang baru dibuat
SET @menu_id = LAST_INSERT_ID();

-- Insert permissions untuk Outlet Rejection
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(NULL, @menu_id, 'view', 'outlet_rejection_view', NOW(), NOW()),
(NULL, @menu_id, 'create', 'outlet_rejection_create', NOW(), NOW()),
(NULL, @menu_id, 'update', 'outlet_rejection_update', NOW(), NOW()),
(NULL, @menu_id, 'delete', 'outlet_rejection_delete', NOW(), NOW());

-- Atau jika ingin insert langsung dengan ID yang spesifik (ganti X dengan ID yang sesuai)
-- INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
-- (X, 'Outlet Rejection', 'outlet_rejection', 6, '/outlet-rejections', 'fas fa-undo', NOW(), NOW());

-- INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
-- (NULL, X, 'view', 'outlet_rejection_view', NOW(), NOW()),
-- (NULL, X, 'create', 'outlet_rejection_create', NOW(), NOW()),
-- (NULL, X, 'update', 'outlet_rejection_update', NOW(), NOW()),
-- (NULL, X, 'delete', 'outlet_rejection_delete', NOW(), NOW());
