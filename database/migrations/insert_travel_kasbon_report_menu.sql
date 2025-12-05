-- Insert menu untuk Report Travel Application & Kasbon
-- Masukkan ke group Human Resources (parent_id = 106)

-- Insert menu
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Report Travel & Kasbon',
    'travel_kasbon_report',
    106, -- parent_id untuk 'Human Resource'
    '/travel-kasbon-report',
    'fa-solid fa-plane',
    NOW(),
    NOW()
);

-- Insert all permissions simultaneously
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT
    id,
    action_type,
    CONCAT('travel_kasbon_report_', action_type),
    NOW(),
    NOW()
FROM `erp_menu`
CROSS JOIN (
    SELECT 'view' as action_type
    UNION ALL SELECT 'create'
    UNION ALL SELECT 'update'
    UNION ALL SELECT 'delete'
) as actions
WHERE `code` = 'travel_kasbon_report' AND `parent_id` = 106;

