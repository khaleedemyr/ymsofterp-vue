-- KPI D022: target kunjungan outlet dari Regional Management (user_regional.target_outlet_visits)
-- Jalankan setelah alter_user_regional_target_outlet_visits.sql

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `scope_type` = 'employee',
    `description` = 'Target visit count (Regional Management)',
    `updated_at` = NOW()
WHERE `code` = 'D022';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'regional_target_outlet_visits', NULL,
    '{"user_id":"context.user_id","month":"context.period_month"}',
    'sum', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D022'
ON DUPLICATE KEY UPDATE
    `resolver_key` = 'regional_target_outlet_visits',
    `aggregation` = 'sum',
    `updated_at` = NOW();

-- Sinkronkan snapshot evaluasi draft yang masih source manual
UPDATE `kpi_evaluation_parameter_values` pv
INNER JOIN `kpi_parameters` p ON p.id = pv.kpi_parameter_id
SET
    pv.`source_type` = p.`source_type`,
    pv.`scope_type` = p.`scope_type`,
    pv.`parameter_name` = p.`name`
WHERE p.`code` IN ('D021', 'D022');
