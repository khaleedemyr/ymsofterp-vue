-- Query untuk membuat tabel erp_menu dan erp_permission
-- serta insert data untuk Member Apps Settings

-- 1. Buat tabel erp_menu
CREATE TABLE IF NOT EXISTS `erp_menu` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL UNIQUE,
  `parent_id` int(11) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `erp_menu_parent_id_foreign` (`parent_id`),
  KEY `erp_menu_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Buat tabel erp_permission
CREATE TABLE IF NOT EXISTS `erp_permission` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `action` enum('view','create','update','delete') NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `erp_permission_menu_id_foreign` (`menu_id`),
  KEY `erp_permission_code_index` (`code`),
  UNIQUE KEY `erp_permission_menu_action_unique` (`menu_id`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insert menu Member Apps Settings (parent_id = 138)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Member Apps Settings', 'member_apps_settings', 138, '/admin/member-apps-settings', 'fa-solid fa-mobile-screen-button', NOW(), NOW());

-- 4. Insert permissions untuk Member Apps Settings
-- Ambil menu_id dari insert sebelumnya
SET @menu_id = LAST_INSERT_ID();

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'member_apps_settings_view', NOW(), NOW()),
(@menu_id, 'create', 'member_apps_settings_create', NOW(), NOW()),
(@menu_id, 'update', 'member_apps_settings_update', NOW(), NOW()),
(@menu_id, 'delete', 'member_apps_settings_delete', NOW(), NOW());

-- 5. Query untuk melihat hasil
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    COUNT(p.id) as permission_count
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'member_apps_settings'
GROUP BY m.id, m.name, m.code, m.parent_id, m.route, m.icon;

-- 6. Query untuk melihat semua permissions
SELECT 
    m.name as menu_name,
    m.code as menu_code,
    p.action,
    p.code as permission_code
FROM erp_menu m
JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'member_apps_settings'
ORDER BY p.action;
