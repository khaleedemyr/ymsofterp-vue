-- D027–D030 / KPI19–KPI20 — Upselling & Average Check Growth resolvers
-- Jalankan sekali di MySQL production/staging.

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `description` = 'Target F&B revenue upselling dari menu Upselling Sales Achievement',
    `updated_at` = NOW()
WHERE `code` = 'D028';

UPDATE `kpi_parameters`
SET
    `description` = 'Actual F&B revenue upselling dari menu Upselling Sales Achievement',
    `updated_at` = NOW()
WHERE `code` = 'D027';

UPDATE `kpi_parameters`
SET
    `description` = 'Avg check/pax bulan data KPI (untuk growth vs bulan sebelumnya)',
    `updated_at` = NOW()
WHERE `code` = 'D029';

UPDATE `kpi_parameters`
SET
    `description` = 'Avg check/pax bulan sebelum bulan data KPI (basis growth)',
    `updated_at` = NOW()
WHERE `code` = 'D030';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D027' AS code, 'upselling_actual_fb_revenue' AS resolver_key, 'sum' AS aggregation UNION ALL
    SELECT 'D028', 'upselling_target_fb_revenue', 'sum' UNION ALL
    SELECT 'D029', 'outlet_avg_check_data_month', 'avg' UNION ALL
    SELECT 'D030', 'outlet_avg_check_prev_month', 'avg'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
