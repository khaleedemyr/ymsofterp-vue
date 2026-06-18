-- =====================================================
-- Just Academy — erp_menu + erp_permission
-- Group sidebar sendiri (parent_id NULL), bukan di bawah HR
-- Jalankan setelah create_just_academy_tables.sql
-- Aman diulang (ON DUPLICATE KEY UPDATE)
-- =====================================================

START TRANSACTION;

-- Parent group (folder sidebar, tanpa halaman langsung)
INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Just Academy',
    'just_academy',
    NULL,
    '#',
    'fa-solid fa-graduation-cap',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @ja_parent_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy' LIMIT 1);

-- Child menus
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Dashboard',        'just_academy_dashboard',    @ja_parent_id, '/just-academy/dashboard',     'fa-solid fa-gauge-high',      NOW(), NOW()),
('Categories',       'just_academy_categories',   @ja_parent_id, '/just-academy/categories',    'fa-solid fa-folder-tree',     NOW(), NOW()),
('Programs',         'just_academy_programs',     @ja_parent_id, '/just-academy/programs',      'fa-solid fa-book-open',       NOW(), NOW()),
('Schedules',        'just_academy_schedules',    @ja_parent_id, '/just-academy/schedules',     'fa-solid fa-calendar-days',   NOW(), NOW()),
('My Training',      'just_academy_my_training',  @ja_parent_id, '/just-academy/my-training',   'fa-solid fa-user-graduate',   NOW(), NOW()),
('Reports',          'just_academy_reports',      @ja_parent_id, '/just-academy/reports',       'fa-solid fa-chart-column',    NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- Perbaiki instalasi lama yang parent-nya masih di HR (parent_id = 106)
UPDATE `erp_menu`
SET `parent_id` = @ja_parent_id
WHERE `code` IN (
    'just_academy_dashboard',
    'just_academy_categories',
    'just_academy_programs',
    'just_academy_schedules',
    'just_academy_my_training',
    'just_academy_reports'
);

UPDATE `erp_menu`
SET `parent_id` = NULL, `route` = '#'
WHERE `code` = 'just_academy';

-- Permissions: Categories
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_categories' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view',   'just_academy_categories_view',   NOW(), NOW()),
(@menu_id, 'create', 'just_academy_categories_create', NOW(), NOW()),
(@menu_id, 'update', 'just_academy_categories_edit',   NOW(), NOW()),
(@menu_id, 'delete', 'just_academy_categories_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Permissions: Programs
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_programs' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view',   'just_academy_programs_view',   NOW(), NOW()),
(@menu_id, 'create', 'just_academy_programs_create', NOW(), NOW()),
(@menu_id, 'update', 'just_academy_programs_edit',   NOW(), NOW()),
(@menu_id, 'delete', 'just_academy_programs_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Permissions: Schedules (invite & mark manual → pakai action update)
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_schedules' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view',   'just_academy_schedules_view',   NOW(), NOW()),
(@menu_id, 'create', 'just_academy_schedules_create', NOW(), NOW()),
(@menu_id, 'update', 'just_academy_schedules_edit',   NOW(), NOW()),
(@menu_id, 'delete', 'just_academy_schedules_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Permissions: My Training (semua user aktif — grant via role)
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_my_training' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'just_academy_my_training_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Permissions: Dashboard
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_dashboard' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'just_academy_dashboard_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Permissions: Reports (export pakai action view — cukup akses menu)
SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'just_academy_reports' LIMIT 1);
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'just_academy_reports_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`), `updated_at` = NOW();

-- Bersihkan permission invalid jika script lama sempat ter-insert sebagian
DELETE FROM `erp_permission`
WHERE `code` IN (
    'just_academy_schedules_invite',
    'just_academy_schedules_mark_attendance',
    'just_academy_reports_export'
);

COMMIT;

-- Contoh grant ke role admin (ganti ROLE_ID):
-- INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
-- SELECT 1, p.id, NOW(), NOW()
-- FROM `erp_permission` p
-- WHERE p.`code` LIKE 'just_academy_%'
-- ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Verifikasi:
-- SELECT m.id, m.name, m.code, m.parent_id, m.route, p.action, p.code AS permission_code
-- FROM erp_menu m
-- LEFT JOIN erp_permission p ON p.menu_id = m.id
-- WHERE m.code = 'just_academy' OR m.code LIKE 'just_academy_%'
-- ORDER BY m.parent_id, m.id, p.action;
