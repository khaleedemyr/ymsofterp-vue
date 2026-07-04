-- Menu: Employee Onboarding — parent Human Resources (parent_id = 106)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Onboarding Template',
    'onboarding_template',
    106,
    '/onboarding-templates',
    'fa-solid fa-list-check',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @template_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'onboarding_template' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
    (@template_menu_id, 'view',   'onboarding_template_view',   NOW(), NOW()),
    (@template_menu_id, 'create', 'onboarding_template_create', NOW(), NOW()),
    (@template_menu_id, 'update', 'onboarding_template_edit',   NOW(), NOW()),
    (@template_menu_id, 'delete', 'onboarding_template_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `erp_menu` (
    `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`
) VALUES (
    'Employee Onboarding',
    'employee_onboarding',
    106,
    '/employee-onboarding',
    'fa-solid fa-user-plus',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @onboarding_menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'employee_onboarding' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
    (@onboarding_menu_id, 'view',   'employee_onboarding_view',   NOW(), NOW()),
    (@onboarding_menu_id, 'create', 'employee_onboarding_create', NOW(), NOW()),
    (@onboarding_menu_id, 'update', 'employee_onboarding_edit',   NOW(), NOW()),
    (@onboarding_menu_id, 'delete', 'employee_onboarding_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

COMMIT;
