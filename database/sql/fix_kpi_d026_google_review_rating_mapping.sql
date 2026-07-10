-- D026 / KPI18 — Google Review Rating dari Manual Monthly Google Review (rata-rata outlet scope user)
INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT
    p.id,
    'manual_google_review_rating_avg',
    NULL,
    '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}',
    'avg',
    'A',
    NOW(),
    NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D026'
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();
