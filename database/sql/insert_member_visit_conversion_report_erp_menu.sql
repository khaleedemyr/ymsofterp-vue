-- =====================================================
-- Member Visit Conversion — erp_menu + erp_permission
-- parent_id = 138 (CRM)
-- Eksekusi sekali di MySQL (boleh dipaste sekaligus).
-- =====================================================

START TRANSACTION;

INSERT INTO `erp_menu` (
    `name`,
    `code`,
    `parent_id`,
    `route`,
    `icon`,
    `created_at`,
    `updated_at`
) VALUES (
    'Member Visit Conversion',
    'member_visit_conversion_report',
    138,
    '/crm/member-visit-conversion-report',
    'fa-solid fa-bullseye',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

UPDATE `erp_menu`
SET `name` = 'Member Visit Conversion',
    `parent_id` = 138,
    `route` = '/crm/member-visit-conversion-report',
    `icon` = 'fa-solid fa-bullseye',
    `updated_at` = NOW()
WHERE `code` = 'member_visit_conversion_report';

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'member_visit_conversion_report' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'view', 'member_visit_conversion_report_view', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_visit_conversion_report_view' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'create', 'member_visit_conversion_report_create', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_visit_conversion_report_create' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'update', 'member_visit_conversion_report_edit', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_visit_conversion_report_edit' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'delete', 'member_visit_conversion_report_delete', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_visit_conversion_report_delete' LIMIT 1
);

COMMIT;

-- Grant ke role yang sudah punya report member visit / transaksi member:
INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT DISTINCT rp.role_id, p_new.id, NOW(), NOW()
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id
    AND p_old.`code` IN (
        'crm_members_view',
        'member_transaction_report_view',
        'member_visit_frequency_report_view'
    )
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'member_visit_conversion_report_view'
WHERE NOT EXISTS (
    SELECT 1
    FROM `erp_role_permission` x
    WHERE x.role_id = rp.role_id
      AND x.permission_id = p_new.id
);
