-- D009 MTD Petty Cash Budget → sama dengan locking petty cash RF/RNF
-- Rumus per outlet: sum(forecast_revenue Target Pendapatan) × 80% × 0,8%
-- Scope multi-outlet: dijumlahkan per outlet

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'petty_cash_lock_budget', NULL,
    '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}',
    'sum', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D009'
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `updated_at` = NOW();
