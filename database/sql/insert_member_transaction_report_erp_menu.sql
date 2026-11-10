-- =====================================================
-- Report Transaksi Member — erp_menu + erp_permission
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
    'Report Transaksi Member',
    'member_transaction_report',
    138,
    '/crm/member-transaction-report',
    'fa-solid fa-receipt',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- Jika instalasi lama tanpa unique di erp_menu.code, pastikan parent/route benar:
UPDATE `erp_menu`
SET `name` = 'Report Transaksi Member',
    `parent_id` = 138,
    `route` = '/crm/member-transaction-report',
    `icon` = 'fa-solid fa-receipt',
    `updated_at` = NOW()
WHERE `code` = 'member_transaction_report';

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'member_transaction_report' LIMIT 1);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'view', 'member_transaction_report_view', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_transaction_report_view' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'create', 'member_transaction_report_create', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_transaction_report_create' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'update', 'member_transaction_report_edit', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_transaction_report_edit' LIMIT 1
);

INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`)
SELECT @menu_id, 'delete', 'member_transaction_report_delete', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM `erp_permission` WHERE `code` = 'member_transaction_report_delete' LIMIT 1
);

COMMIT;

-- Grant ke role yang sudah punya Data Member (crm_members_view):
INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT rp.role_id, p_new.id, NOW(), NOW()
FROM `erp_role_permission` rp
INNER JOIN `erp_permission` p_old ON p_old.id = rp.permission_id AND p_old.`code` = 'crm_members_view'
INNER JOIN `erp_permission` p_new ON p_new.`code` = 'member_transaction_report_view'
WHERE NOT EXISTS (
    SELECT 1
    FROM `erp_role_permission` x
    WHERE x.role_id = rp.role_id
      AND x.permission_id = p_new.id
);
