-- Insert menu untuk CRM Dashboard
-- Jalankan query ini sekali saja
-- parent_id = 1 (Main Menu)

-- Menu: Dashboard CRM
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
SELECT 
    'Dashboard CRM',
    'crm_dashboard',
    1,
    '/crm/dashboard',
    'fa-solid fa-chart-line',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_menu WHERE code = 'crm_dashboard'
);

-- Permissions untuk Dashboard CRM
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'crm_dashboard' LIMIT 1),
    action_name,
    CONCAT('crm_dashboard_', action_name),
    NOW(),
    NOW()
FROM (
    SELECT 'view' as action_name
    UNION ALL SELECT 'create'
    UNION ALL SELECT 'update'
    UNION ALL SELECT 'delete'
) AS actions
WHERE NOT EXISTS (
    SELECT 1 FROM erp_permission 
    WHERE menu_id = (SELECT id FROM erp_menu WHERE code = 'crm_dashboard' LIMIT 1)
    AND action = actions.action_name
);

