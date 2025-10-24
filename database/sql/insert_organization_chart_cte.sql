-- Single execution query using CTE for Organization Chart Menu and Permissions
-- Parent ID: 106

WITH menu_insert AS (
    INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
    VALUES ('Struktur Organisasi', 'organization_chart', 106, '/organization-chart', 'fa-solid fa-sitemap', NOW(), NOW())
    RETURNING id
)
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT 
    menu_insert.id,
    permission_data.action,
    permission_data.code,
    NOW(),
    NOW()
FROM menu_insert
CROSS JOIN (
    VALUES 
    ('view', 'organization_chart_view'),
    ('create', 'organization_chart_create'),
    ('update', 'organization_chart_update'),
    ('delete', 'organization_chart_delete')
) AS permission_data(action, code);
