-- Insert menu Report Hutang ke erp_menu dan erp_permission
-- parent_id = 5 (HO Finance)
-- Query ini bisa dieksekusi sekali untuk insert menu dan semua permission-nya

-- Step 1: Insert menu Report Hutang
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Report Hutang',
    'debt_report',
    5,
    '/debt-report',
    'fa-solid fa-file-invoice-dollar',
    NOW(),
    NOW()
);

-- Step 2: Insert semua permissions untuk debt_report menu sekaligus
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id,
    action_type,
    CONCAT('debt_report_', action_type),
    NOW(),
    NOW()
FROM `erp_menu`
CROSS JOIN (
    SELECT 'view' as action_type
    UNION ALL SELECT 'create'
    UNION ALL SELECT 'update'
    UNION ALL SELECT 'delete'
) as actions
WHERE `code` = 'debt_report' AND `parent_id` = 5;

