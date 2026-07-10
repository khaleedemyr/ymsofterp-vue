-- D017 — Recipe Compliance & Standardization (KPI25)
-- Sumber: QA2 Audits, parameter BRA-1.5.3 (Serving) & BRA-1.4.6 (Processing)
-- Rumus: C / (C + NC) × 100 per outlet, rata-rata antar outlet scope

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `description` = 'Recipe/SOP compliance dari QA2 Audits — parameter BRA-1.5.3 & BRA-1.4.6 (C/(C+NC) × 100)',
    `updated_at` = NOW()
WHERE `code` = 'D017';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, 'qa2_recipe_compliance_score', '{"parameter_codes":["BRA-1.5.3","BRA-1.4.6"]}', '{"outlet_id":"context.outlet_id","start_date":"context.period_start","end_date":"context.period_end"}', 'avg', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.code = 'D017'
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `static_filters` = VALUES(`static_filters`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
