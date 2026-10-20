-- =====================================================
-- KPI Template — Reg. Executive Chef
-- Jalankan SETELAH:
--   1. seed_kpi_template_justus_sample.sql
--   2. seed_kpi_parameters_extended.sql      (KS08, KPI25/KPI27/KPI30, KPI21/KPI22)
--   3. seed_kpi_gm_operation.sql             (KPI_GM02-05/07/08, KPI11-KPI16)
-- Parameter TIDAK diduplikasi — reuse kode yang sudah ada.
-- =====================================================
-- Struktur (sesuai Excel Reg. Executive Chef):
--   KS01 Cost & Productivity              20% → KPI_GM02, KPI_GM03, KPI_GM04, KPI_GM05, KPI_GM07
--   KS08 Kitchen Operational Excellence   15% → KPI25, KPI27
--   KS03 Customer Experience              10% → KPI_GM08, KPI30
--   KS04 Team Development                 25% → KPI11, KPI12, KPI21, KPI22  (tanpa JNG/KPI13)
--   KS05 Compliance & Team Support        30% → KPI14, KPI15, KPI16
-- Total weight = 100%
-- =====================================================
-- Jabatan:
--   • Regional Executive Chef
-- =====================================================

START TRANSACTION;

INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS08', 'Kitchen Operational Excellence', 'Recipe compliance, ticket time, calibration, availability', 8, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `erp_data_scope`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_REG_EXECUTIVE_CHEF_v1',
    'Reg. Executive Chef',
    'Template KPI Reg. Executive Chef — Cost & Productivity, Kitchen Ops, Customer Experience, Team Development, Compliance.',
    1,
    'active',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'all_outlets',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_REG_EXECUTIVE_CHEF_v1');

UPDATE `kpi_templates`
SET
    `name` = 'Reg. Executive Chef',
    `description` = 'Template KPI Reg. Executive Chef — Cost & Productivity, Kitchen Ops, Customer Experience, Team Development, Compliance.',
    `template_status` = 'active',
    `erp_data_scope` = 'all_outlets',
    `scoring_rules` = '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    `updated_at` = NOW()
WHERE `code` = 'KPI_REG_EXECUTIVE_CHEF_v1';

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_REG_EXECUTIVE_CHEF_v1' LIMIT 1);

INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (
      j.nama_jabatan = 'Regional Executive Chef'
      OR j.nama_jabatan LIKE 'Regional Executive Chef %'
  )
  AND j.nama_jabatan NOT LIKE '%Sous%'
  AND j.nama_jabatan NOT LIKE '%Asst%'
  AND j.nama_jabatan NOT LIKE '%Assistant%'
  AND j.nama_jabatan NOT LIKE '%Corporate%'
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  );

DELETE tip FROM `kpi_template_item_parameters` tip
JOIN `kpi_template_items` ti ON ti.id = tip.kpi_template_item_id
JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id
WHERE ts.kpi_template_id = @tpl_id;

DELETE ti FROM `kpi_template_items` ti
JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id
WHERE ts.kpi_template_id = @tpl_id;

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'KS01' AS code, 20.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS08', 15.00, 1 UNION ALL
    SELECT 'KS03', 10.00, 2 UNION ALL
    SELECT 'KS04', 25.00, 3 UNION ALL
    SELECT 'KS05', 30.00, 4
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.code;

INSERT INTO `kpi_template_items` (
    `kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT ts.id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, COALESCE(v.freq, p.frequency), p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    -- KS01 Cost & Productivity (20%)
    SELECT 'KS01' AS ks_code, 'KPI_GM02' AS kpi_code,  5.00 AS weight, 0 AS sort_order, '29% - 31%' AS tgt, NULL AS freq UNION ALL
    SELECT 'KS01', 'KPI_GM03',  5.00, 1, '< 2%', NULL UNION ALL
    SELECT 'KS01', 'KPI_GM04',  5.00, 2, '< 0.5%', NULL UNION ALL
    SELECT 'KS01', 'KPI_GM05',  2.00, 3, '<= 0.2%', NULL UNION ALL
    SELECT 'KS01', 'KPI_GM07',  3.00, 4, '11% - 13%', NULL UNION ALL
    -- KS08 Kitchen Operational Excellence (15%)
    SELECT 'KS08', 'KPI25',    10.00, 0, '>= 90%', NULL UNION ALL
    SELECT 'KS08', 'KPI27',     5.00, 1, '100% Completion', NULL UNION ALL
    -- KS03 Customer Experience (10%)
    SELECT 'KS03', 'KPI_GM08',  5.00, 0, '<= 24 Hours', NULL UNION ALL
    SELECT 'KS03', 'KPI30',     5.00, 1, '<= 0.50%', NULL UNION ALL
    -- KS04 Team Development (25%) — tanpa JNG; Excel 5x5 incl JNG → 7/6/6/6
    SELECT 'KS04', 'KPI11',     7.00, 0, '100%', NULL UNION ALL
    SELECT 'KS04', 'KPI12',     6.00, 1, '>= 80%', NULL UNION ALL
    SELECT 'KS04', 'KPI21',     6.00, 2, '100% on Time', NULL UNION ALL
    SELECT 'KS04', 'KPI22',     6.00, 3, '>= 2 Person', 'daily' UNION ALL
    -- KS05 Compliance & Team Support (30%)
    SELECT 'KS05', 'KPI14',    10.00, 0, '> 90%', NULL UNION ALL
    SELECT 'KS05', 'KPI15',    15.00, 1, '>= 80%', NULL UNION ALL
    SELECT 'KS05', 'KPI16',     5.00, 2, '>= 95%', NULL
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.ks_code
JOIN `kpi_template_strategies` ts ON ts.kpi_template_id = @tpl_id AND ts.kpi_key_strategy_id = ks.id
JOIN `kpi_parameters` p ON p.code = v.kpi_code AND p.status = 'A';

INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id AND ts.kpi_template_id = @tpl_id
JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id
JOIN (
    SELECT 'KS01' AS ks_code, 'KPI_GM02' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT 'KS01', 'KPI_GM03', 1 UNION ALL SELECT 'KS01', 'KPI_GM04', 2 UNION ALL
    SELECT 'KS01', 'KPI_GM05', 3 UNION ALL SELECT 'KS01', 'KPI_GM07', 4 UNION ALL
    SELECT 'KS08', 'KPI25', 0 UNION ALL SELECT 'KS08', 'KPI27', 1 UNION ALL
    SELECT 'KS03', 'KPI_GM08', 0 UNION ALL SELECT 'KS03', 'KPI30', 1 UNION ALL
    SELECT 'KS04', 'KPI11', 0 UNION ALL SELECT 'KS04', 'KPI12', 1 UNION ALL
    SELECT 'KS04', 'KPI21', 2 UNION ALL SELECT 'KS04', 'KPI22', 3 UNION ALL
    SELECT 'KS05', 'KPI14', 0 UNION ALL SELECT 'KS05', 'KPI15', 1 UNION ALL SELECT 'KS05', 'KPI16', 2
) v ON ks.code = v.ks_code AND ti.sort_order = v.sort_order
JOIN `kpi_parameters` p ON p.code = v.kpi_code AND p.status = 'A';

COMMIT;
