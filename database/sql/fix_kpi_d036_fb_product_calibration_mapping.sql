-- D036 — Taste Calibration Completion % (KPI27 Product Taste Calibration)
-- Sumber: menu F&B Product Calibration — schedule bulan data yang conductor-nya
--         karyawan dinilai atau bawahan langsung (id_atasan jabatan)
-- Rumus: completed / total (non-cancelled) × 100 per outlet, rata-rata antar outlet scope

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `description` = 'Completion schedule F&B Product Calibration — conductor user dinilai atau bawahan langsung',
    `updated_at` = NOW()
WHERE `code` = 'D036';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'fb_product_calibration_completion_percent', NULL, '{"outlet_id":"context.outlet_id","start_date":"context.period_start","end_date":"context.period_end","user_id":"context.user_id"}', 'avg', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D036'
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `static_filters` = VALUES(`static_filters`),
    `dynamic_filter_bindings` = VALUES(`dynamic_filter_bindings`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
