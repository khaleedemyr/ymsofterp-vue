-- Insert menu untuk Brands Management
-- Jalankan query ini sekali saja

-- Menu: Brands
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) 
SELECT 
    'Brands',
    'web_profile_brands',
    (SELECT id FROM erp_menu WHERE name = 'Web Profile' LIMIT 1),
    '/web-profile/brands',
    'fa-solid fa-store',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM erp_menu WHERE code = 'web_profile_brands'
);

-- Permissions untuk Brands
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) 
SELECT 
    (SELECT id FROM erp_menu WHERE code = 'web_profile_brands' LIMIT 1),
    action_name,
    CONCAT('web_profile_brands_', action_name),
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
    WHERE menu_id = (SELECT id FROM erp_menu WHERE code = 'web_profile_brands' LIMIT 1)
    AND action = actions.action_name
);

