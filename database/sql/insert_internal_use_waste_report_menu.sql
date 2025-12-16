-- Insert menu untuk Report RnD, BM, WM
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES ('Report RnD, BM, WM', 'internal_use_waste_report', 66, '/internal-use-waste-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- Insert permission untuk menu tersebut
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT 
    LAST_INSERT_ID() as `menu_id`,
    'view' as `action`,
    'internal_use_waste_report.view' as `code`,
    NOW() as `created_at`,
    NOW() as `updated_at`;

