-- Optimized single execution query for Monitoring Active Users Menu and Permissions
-- Parent ID: 217 (Support Group)
-- Execute this query once to insert both menu and permissions

-- Step 1: Insert menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Monitoring User Aktif', 'monitoring_active_users', 217, '/monitoring/active-users', 'fa-solid fa-users-line', NOW(), NOW());

-- Step 2: Insert permissions using the menu ID (optimized with CROSS JOIN)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT 
    m.id as menu_id,
    p.action,
    p.code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m
CROSS JOIN (
    SELECT 'view' as action, 'monitoring_active_users_view' as code
    UNION ALL SELECT 'create', 'monitoring_active_users_create'
    UNION ALL SELECT 'update', 'monitoring_active_users_update'
    UNION ALL SELECT 'delete', 'monitoring_active_users_delete'
) p
WHERE m.code = 'monitoring_active_users' 
AND m.parent_id = 217
ORDER BY m.id DESC 
LIMIT 1;

