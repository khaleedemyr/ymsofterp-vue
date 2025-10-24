-- Optimized single execution query for Organization Chart Menu and Permissions
-- Parent ID: 106

-- Insert menu and get the ID
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
VALUES ('Struktur Organisasi', 'organization_chart', 106, '/organization-chart', 'fa-solid fa-sitemap', NOW(), NOW());

-- Insert permissions using the menu ID
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT 
    m.id as menu_id,
    p.action,
    p.code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m
CROSS JOIN (
    SELECT 'view' as action, 'organization_chart_view' as code
    UNION ALL SELECT 'create', 'organization_chart_create'
    UNION ALL SELECT 'update', 'organization_chart_update'
    UNION ALL SELECT 'delete', 'organization_chart_delete'
) p
WHERE m.code = 'organization_chart' 
AND m.parent_id = 106
ORDER BY m.id DESC 
LIMIT 1;
