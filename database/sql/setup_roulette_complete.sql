-- Complete setup for Roulette feature
-- ======================================

-- 1. Create roulettes table
CREATE TABLE IF NOT EXISTS `roulettes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insert menu for Data Roulette
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(100, 'Data Roulette', 'data_roulette', 8, '/roulette', 'fa-solid fa-dice', NOW(), NOW());

-- 3. Insert permissions for Data Roulette
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(100, 'view', 'data_roulette_view', NOW(), NOW()),
(100, 'create', 'data_roulette_create', NOW(), NOW()),
(100, 'update', 'data_roulette_update', NOW(), NOW()),
(100, 'delete', 'data_roulette_delete', NOW(), NOW()),
(100, 'import', 'data_roulette_import', NOW(), NOW());

-- 4. Insert sample data for roulettes table (optional)
INSERT INTO `roulettes` (`nama`, `email`, `no_hp`, `created_at`, `updated_at`) VALUES
('John Doe', 'john.doe@example.com', '081234567890', NOW(), NOW()),
('Jane Smith', 'jane.smith@example.com', '081234567891', NOW(), NOW()),
('Bob Johnson', 'bob.johnson@example.com', '081234567892', NOW(), NOW()),
('Alice Brown', 'alice.brown@example.com', '081234567893', NOW(), NOW()),
('Charlie Wilson', 'charlie.wilson@example.com', '081234567894', NOW(), NOW()); 