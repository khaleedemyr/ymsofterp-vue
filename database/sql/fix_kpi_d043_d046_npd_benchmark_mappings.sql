-- D043 / D046 — New Product Development & Product Benchmarking Execution
-- Data: NPD Plan & Report, Competitor Benchmark Report
-- Jalankan sekali di MySQL production/staging.

-- Kolom manual_input_hint (jika belum ada dari alter_kpi_parameters_manual_input_hint.sql)
SET @has_manual_input_hint := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kpi_parameters'
      AND COLUMN_NAME = 'manual_input_hint'
);

SET @sql_add_manual_input_hint := IF(
    @has_manual_input_hint = 0,
    'ALTER TABLE `kpi_parameters` ADD COLUMN `manual_input_hint` TEXT NULL DEFAULT NULL AFTER `description`',
    'SELECT 1'
);

PREPARE stmt_add_manual_input_hint FROM @sql_add_manual_input_hint;
EXECUTE stmt_add_manual_input_hint;
DEALLOCATE PREPARE stmt_add_manual_input_hint;

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `frequency` = 'quarterly',
    `description` = 'Jumlah produk NPD approved (status approved) dari menu NPD Plan & Report — dihitung per PIC/creator',
    `manual_input_hint` = 'Otomatis dari NPD Plan & Report (produk approved). Override manual = total produk quarter bila quarterly.',
    `updated_at` = NOW()
WHERE `code` = 'D043';

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `description` = 'Jumlah benchmark visit dari menu Competitor Benchmark Report — dihitung per PIC/creator',
    `manual_input_hint` = 'Otomatis dari Competitor Benchmark Report. Override manual = jumlah benchmark bulan/quarter.',
    `updated_at` = NOW()
WHERE `code` = 'D046';

INSERT INTO `kpi_parameter_erp_mappings` (
    `kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`
)
SELECT p.id, v.resolver_key, NULL, '{"user_id":"context.user_id","month":"context.period_month"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D043' AS code, 'npd_approved_product_count' AS resolver_key, 'sum' AS aggregation UNION ALL
    SELECT 'D046', 'competitor_benchmark_execution_count', 'sum'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `status` = 'A',
    `updated_at` = NOW();

COMMIT;
