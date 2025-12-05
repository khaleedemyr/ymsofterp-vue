-- Insert menu for OPEX By Category Report
-- Parent ID = 5 (HO Finance - same parent as OPEX Report)
-- Query ini bisa dieksekusi sekali untuk insert menu dan semua permission-nya

-- Step 1: Insert menu OPEX By Category
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'OPEX By Category',
    'opex-by-category',
    5,
    '/opex-by-category',
    'fa-solid fa-chart-pie',
    NOW(),
    NOW()
);

-- Step 2: Insert semua permissions untuk opex-by-category menu sekaligus
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    id,
    action_type,
    CONCAT('opex-by-category_', action_type),
    NOW(),
    NOW()
FROM `erp_menu`
CROSS JOIN (
    SELECT 'view' as action_type
    UNION ALL SELECT 'create'
    UNION ALL SELECT 'update'
    UNION ALL SELECT 'delete'
) as actions
WHERE `code` = 'opex-by-category' AND `parent_id` = 5;

