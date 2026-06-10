-- Fix D011 Total Orders: pakai count order POS, bukan sum revenue
-- Jalankan sekali di production jika D011 menampilkan angka revenue (mis. 5.083.848.001)

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'pos_order_count', NULL,
    '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}',
    'count', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D011'
ON DUPLICATE KEY UPDATE
    `resolver_key` = 'pos_order_count',
    `aggregation` = 'count',
    `updated_at` = NOW();

-- Alternatif jika tetap pakai daily_revenue_forecast, pastikan aggregation = count:
-- UPDATE kpi_parameter_erp_mappings m
-- JOIN kpi_parameters p ON p.id = m.kpi_parameter_id
-- SET m.aggregation = 'count'
-- WHERE p.code = 'D011' AND m.resolver_key = 'daily_revenue_forecast';
