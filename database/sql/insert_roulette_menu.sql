-- Insert menu for Data Roulette
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(100, 'Data Roulette', 'data_roulette', 8, '/roulette', 'fa-solid fa-dice', NOW(), NOW());

-- Insert permissions for Data Roulette
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(100, 'view', 'data_roulette_view', NOW(), NOW()),
(100, 'create', 'data_roulette_create', NOW(), NOW()),
(100, 'update', 'data_roulette_update', NOW(), NOW()),
(100, 'delete', 'data_roulette_delete', NOW(), NOW()),
(100, 'import', 'data_roulette_import', NOW(), NOW()); 