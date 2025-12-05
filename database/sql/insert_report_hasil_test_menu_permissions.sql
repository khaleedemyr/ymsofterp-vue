-- Insert menu "Report Hasil Test" dan permission-nya
-- parent_id = 106 (Human Resources)

-- 1. Insert menu "Report Hasil Test"
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES (
    'Report Hasil Test',
    'enroll_test_report',
    106,
    '/enroll-test-report',
    'fa-solid fa-chart-line',
    NOW(),
    NOW()
);

-- 2. Insert permissions untuk menu "Report Hasil Test"
-- Ambil menu_id dari menu yang baru saja diinsert
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'view' as action,
    CONCAT(m.code, '_view') as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m 
WHERE m.code = 'enroll_test_report' AND m.parent_id = 106;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'create' as action,
    CONCAT(m.code, '_create') as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m 
WHERE m.code = 'enroll_test_report' AND m.parent_id = 106;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'update' as action,
    CONCAT(m.code, '_update') as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m 
WHERE m.code = 'enroll_test_report' AND m.parent_id = 106;

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    m.id as menu_id,
    'delete' as action,
    CONCAT(m.code, '_delete') as code,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_menu` m 
WHERE m.code = 'enroll_test_report' AND m.parent_id = 106;
