-- Single execution query for Push Notification Menu and Permissions
-- Parent ID: 138
-- Execute this query once to insert both menu and permissions

-- Step 1: Insert menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Push Notification', 'push_notification', 138, '/push-notification', 'fa-solid fa-bell', NOW(), NOW());

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
    SELECT 'view' as action, 'push_notification_view' as code
    UNION ALL SELECT 'create', 'push_notification_create'
    UNION ALL SELECT 'update', 'push_notification_update'
    UNION ALL SELECT 'delete', 'push_notification_delete'
) p
WHERE m.code = 'push_notification' 
AND m.parent_id = 138
ORDER BY m.id DESC 
LIMIT 1;

-- Verify the insertions
SELECT 
    m.id as menu_id,
    m.name as menu_name,
    m.code as menu_code,
    m.parent_id,
    m.route,
    m.icon,
    p.action,
    p.code as permission_code
FROM erp_menu m
LEFT JOIN erp_permission p ON m.id = p.menu_id
WHERE m.code = 'push_notification'
AND m.parent_id = 138
ORDER BY p.action;

