-- D033 — SOP Development Completion
-- Sumber: menu SOP Development Completion, record yang dibuat user evaluasi.
-- Jalankan sekali di MySQL production/staging.

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `scope_type` = 'employee',
    `description` = 'Persentase SOP Development yang dibuat user evaluasi dan sudah di-upload/approved',
    `updated_at` = NOW()
WHERE `code` = 'D033';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'sop_development_completion_percent', NULL, '{"user_id":"context.user_id","month":"context.period_month","year":"context.period_year"}', 'avg', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.`code` = 'D033'
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
