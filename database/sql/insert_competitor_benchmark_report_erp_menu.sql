-- Menu: Competitor Benchmark Report — parent Ops Management (parent_id = 184)
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
    'Competitor Benchmark Report',
    'competitor_benchmark_report',
    184,
    '/competitor-benchmark-report',
    'fa-solid fa-chart-line',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

SET @menu_id := (SELECT `id` FROM `erp_menu` WHERE `code` = 'competitor_benchmark_report' LIMIT 1);

INSERT INTO `erp_permission` (
    `menu_id`,
    `action`,
    `code`,
    `created_at`,
    `updated_at`
) VALUES
    (@menu_id, 'view',   'competitor_benchmark_report_view',   NOW(), NOW()),
    (@menu_id, 'create', 'competitor_benchmark_report_create', NOW(), NOW()),
    (@menu_id, 'update', 'competitor_benchmark_report_edit',   NOW(), NOW()),
    (@menu_id, 'delete', 'competitor_benchmark_report_delete', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `updated_at` = NOW();

COMMIT;
