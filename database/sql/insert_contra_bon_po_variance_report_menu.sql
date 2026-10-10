-- Menu: Report Selisih Harga/Discount PO vs Contra Bon (parent HO Finance = 5)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`)
VALUES (
    'Report Selisih PO vs CB',
    'contra_bon_po_variance_report',
    5,
    '/contra-bon-po-variance-report',
    'fa-solid fa-scale-unbalanced',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id = (SELECT `id` FROM `erp_menu` WHERE `code` = 'contra_bon_po_variance_report' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
VALUES (@menu_id, 'view', 'contra_bon_po_variance_report_view', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `menu_id` = VALUES(`menu_id`),
    `updated_at` = NOW();

-- Grant view to roles that already have contra_bon view
INSERT IGNORE INTO `erp_role_permission` (`role_id`, `permission_id`)
SELECT DISTINCT rp.role_id, p_new.id
FROM `erp_permission` p_old
JOIN `erp_role_permission` rp ON rp.permission_id = p_old.id
JOIN `erp_permission` p_new ON p_new.code = 'contra_bon_po_variance_report_view'
WHERE p_old.code IN ('contra_bon_view', 'food_payment_view')
   OR p_old.menu_id IN (
        SELECT id FROM erp_menu WHERE code IN ('contra_bon', 'food_payment')
   );
