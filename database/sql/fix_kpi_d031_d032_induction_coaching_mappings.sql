-- D031 / D032 — Employee Induction Completion & Coaching Visit Execution
-- Jalankan sekali di MySQL production/staging.

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `description` = 'Persentase minggu induction onboarding yang approved tepat waktu',
    `updated_at` = NOW()
WHERE `code` = 'D031';

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `frequency` = 'monthly',
    `description` = 'Jumlah karyawan unik yang di-coaching oleh user evaluasi (bulan data KPI)',
    `updated_at` = NOW()
WHERE `code` = 'D032';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year","user_id":"context.user_id"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D031' AS code, 'employee_induction_on_time_percent' AS resolver_key, 'avg' AS aggregation UNION ALL
    SELECT 'D032', 'employee_coaching_person_count', 'count'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
