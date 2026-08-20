-- D040 / D041 / D042 — Beverage, Service, Food complaint count
-- Sumber: CVCC (negative + CAPA per divisi bar / service / kitchen)
-- 0 komplain = 0 (memenuhi target lower-is-better), sama seperti D054
-- Jalankan sekali di MySQL production/staging.

START TRANSACTION;

INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('D041', 'Service Complaint Count', 'erp', 'outlet', 'integer', 'CVCC service negative + CAPA filled', NULL, 'lower_better', 'monthly', NULL, 1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `source_type` = VALUES(`source_type`),
    `description` = VALUES(`description`),
    `target_direction` = VALUES(`target_direction`),
    `updated_at` = NOW();

UPDATE `kpi_parameters`
SET
    `source_type` = 'erp',
    `description` = 'CVCC beverage/bar negative + CAPA filled',
    `target_direction` = 'lower_better',
    `updated_at` = NOW()
WHERE `code` = 'D040';

UPDATE `kpi_parameters`
SET
    `source_type` = 'erp',
    `description` = 'CVCC kitchen negative + CAPA filled',
    `target_direction` = 'lower_better',
    `updated_at` = NOW()
WHERE `code` = 'D042';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, v.resolver_key, NULL, '{"user_id":"context.user_id","outlet_id":"context.outlet_id","month":"context.period_month"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D040' AS code, 'cvcc_beverage_complaint_count' AS resolver_key, 'count' AS aggregation UNION ALL
    SELECT 'D041', 'cvcc_service_complaint_count', 'count' UNION ALL
    SELECT 'D042', 'cvcc_food_complaint_count', 'count'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `dynamic_filter_bindings` = VALUES(`dynamic_filter_bindings`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
