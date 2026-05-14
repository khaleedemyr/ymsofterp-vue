-- =====================================================
-- INSERT MENU DAN PERMISSION: Cost Report HO
-- Parent: Cost Control (parent_id = 66)
-- Route: /cost-report-ho
-- =====================================================

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Cost Report HO',
    'cost_report_ho',
    66,
    '/cost-report-ho',
    'fa-solid fa-building-columns',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = 'Cost Report HO',
    `parent_id` = 66,
    `route` = '/cost-report-ho',
    `icon` = 'fa-solid fa-building-columns',
    `updated_at` = NOW();

SET @menu_id = (SELECT id FROM erp_menu WHERE code = 'cost_report_ho' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES (
    @menu_id,
    'view',
    'cost_report_ho_view',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

-- Beri akses role (contoh):
-- INSERT INTO erp_role_permission (role_id, permission_id)
-- SELECT YOUR_ROLE_ID, id FROM erp_permission WHERE code = 'cost_report_ho_view';
