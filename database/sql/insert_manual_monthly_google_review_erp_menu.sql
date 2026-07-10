-- Menu: Manual Monthly Google Review Rating — parent CRM (parent_id = 138)
-- Eksekusi sekali di MySQL (paste semua query sekaligus).

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
    'Manual Monthly Google Review',
    'manual_monthly_google_review',
    138,
    '/manual-monthly-google-review',
    'fa-brands fa-google',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'manual_monthly_google_review' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'manual_monthly_google_review_view',   NOW(), NOW()),
    (@menu_id, 'create', 'manual_monthly_google_review_create', NOW(), NOW()),
    (@menu_id, 'update', 'manual_monthly_google_review_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'manual_monthly_google_review_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
