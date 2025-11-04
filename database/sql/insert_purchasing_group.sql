-- Insert group "Purchasing" ke tabel erp_menu
-- Group menu dengan parent_id = NULL (tidak punya parent)

INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Purchasing', 'purchasing', NULL, NULL, 'fa-solid fa-shopping-bag', NOW(), NOW());

