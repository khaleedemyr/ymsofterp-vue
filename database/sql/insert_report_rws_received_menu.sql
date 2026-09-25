-- Menu: Report RWS Sudah Diterima (parent HO Finance = 5)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Report RWS Sudah Diterima',
    'report_rws_received',
    5,
    '/report-rws-received',
    'fa-solid fa-circle-check',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'report_rws_received' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'report_rws_received_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

-- Grant view to roles that already have report_rws_unreceived or retail_warehouse_sale view
INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT DISTINCT rp.role_id, p_new.id
FROM `erp_permission` p_old
JOIN `erp_role_permission` rp ON rp.permission_id = p_old.id
JOIN `erp_permission` p_new ON p_new.code = 'report_rws_received_view'
WHERE p_old.code IN ('report_rws_unreceived_view', 'retail_warehouse_sale', 'contra_bon_view')
   OR p_old.menu_id IN (
        SELECT id FROM erp_menu WHERE code IN ('retail_warehouse_sale', 'report_rws_unreceived', 'contra_bon')
   );
