-- =====================================================
-- KPI Template — Asst. Beverage Manager Bandung
-- Jalankan SETELAH:
--   1. seed_kpi_template_justus_sample.sql
--   2. seed_kpi_parameters_extended.sql
--   3. seed_kpi_gm_operation.sql
-- Parameter TIDAK diduplikasi — reuse kode yang sudah ada.
-- =====================================================
-- Struktur (sesuai Excel Asst. Beverage Manager Bandung):
--   KS09 Beverage Operational Excellence  20% → KPI25, KPI27
--   KS03 Customer Experience              15% → KPI_GM08, KPI29
--   KS04 Team Development                 35% → KPI12, KPI13, KPI21, KPI22
--   KS05 Compliance & Team Support        30% → KPI14, KPI15, KPI16
-- =====================================================
-- Jabatan:
--   • Asst. Beverage Manager Bandung
-- =====================================================

START TRANSACTION;

INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS09', 'Beverage Operational Excellence', 'Recipe compliance, serving time, calibration, availability', 9, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `erp_data_scope`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1',
    'Asst. Beverage Manager Bandung',
    'Template KPI Asst. Beverage Manager Bandung — Beverage Ops, Customer Experience, Team Development, Compliance.',
    1,
    'draft',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'all_outlets',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1');

UPDATE `kpi_templates`
SET
    `name` = 'Asst. Beverage Manager Bandung',
    `description` = 'Template KPI Asst. Beverage Manager Bandung — Beverage Ops, Customer Experience, Team Development, Compliance.',
    `erp_data_scope` = 'all_outlets',
    `updated_at` = NOW()
WHERE `code` = 'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1';

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1' LIMIT 1);

INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (
      j.nama_jabatan LIKE '%Asst%Beverage Manager%Bandung%'
      OR j.nama_jabatan LIKE '%Asst. Beverage Manager Bandung%'
      OR j.nama_jabatan LIKE '%Assistant%Beverage Manager%Bandung%'
      OR j.nama_jabatan LIKE '%Assistant Beverage Manager Bandung%'
  )
  AND j.nama_jabatan NOT LIKE '%Regional%'
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  );

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'KS09' AS code, 20.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS03', 15.00, 1 UNION ALL
    SELECT 'KS04', 35.00, 2 UNION ALL
    SELECT 'KS05', 30.00, 3
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.code;

INSERT INTO `kpi_template_items` (
    `kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT ts.id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    -- KS09 Beverage Operational Excellence (20%)
    SELECT 'KS09' AS ks_code, 'KPI25' AS kpi_code, 10.00 AS weight, 0 AS sort_order, '>= 90%' AS tgt UNION ALL
    SELECT 'KS09', 'KPI27', 10.00, 1, '100% Completion' UNION ALL
    -- KS03 Customer Experience (15%)
    SELECT 'KS03', 'KPI_GM08',  8.00, 0, '<= 24 hours' UNION ALL
    SELECT 'KS03', 'KPI29',     7.00, 1, '<= 0.50%' UNION ALL
    -- KS04 Team Development (35%)
    SELECT 'KS04', 'KPI12',    10.00, 0, '>= 90%' UNION ALL
    SELECT 'KS04', 'KPI13',    10.00, 1, '>= 1 Person & 100% on Time' UNION ALL
    SELECT 'KS04', 'KPI21',     8.00, 2, '100% on Time' UNION ALL
    SELECT 'KS04', 'KPI22',     7.00, 3, '>= 2 Person' UNION ALL
    -- KS05 Compliance & Team Support (30%)
    SELECT 'KS05', 'KPI14',    10.00, 0, '>= 90%' UNION ALL
    SELECT 'KS05', 'KPI15',    10.00, 1, '>= 85%' UNION ALL
    SELECT 'KS05', 'KPI16',    10.00, 2, '>= 95%'
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.ks_code
JOIN `kpi_template_strategies` ts ON ts.kpi_template_id = @tpl_id AND ts.kpi_key_strategy_id = ks.id
JOIN `kpi_parameters` p ON p.code = v.kpi_code AND p.status = 'A';

DELETE tip FROM `kpi_template_item_parameters` tip
JOIN `kpi_template_items` ti ON ti.id = tip.kpi_template_item_id
JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id
WHERE ts.kpi_template_id = @tpl_id;

INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id AND ts.kpi_template_id = @tpl_id
JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id
JOIN (
    SELECT 'KS09' AS ks_code, 'KPI25' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT 'KS09', 'KPI27', 1 UNION ALL
    SELECT 'KS03', 'KPI_GM08', 0 UNION ALL SELECT 'KS03', 'KPI29', 1 UNION ALL
    SELECT 'KS04', 'KPI12', 0 UNION ALL SELECT 'KS04', 'KPI13', 1 UNION ALL
    SELECT 'KS04', 'KPI21', 2 UNION ALL SELECT 'KS04', 'KPI22', 3 UNION ALL
    SELECT 'KS05', 'KPI14', 0 UNION ALL SELECT 'KS05', 'KPI15', 1 UNION ALL
    SELECT 'KS05', 'KPI16', 2
) v ON ks.code = v.ks_code AND ti.sort_order = v.sort_order
JOIN `kpi_parameters` p ON p.code = v.kpi_code AND p.status = 'A';

COMMIT;

-- Verifikasi:
-- SELECT ks.name, ts.weight_percent, ti.name, ti.weight_percent, ti.target_value, p.code
-- FROM kpi_templates t
-- JOIN kpi_template_strategies ts ON ts.kpi_template_id = t.id
-- JOIN kpi_key_strategies ks ON ks.id = ts.kpi_key_strategy_id
-- JOIN kpi_template_items ti ON ti.kpi_template_strategy_id = ts.id
-- JOIN kpi_template_item_parameters tip ON tip.kpi_template_item_id = ti.id
-- JOIN kpi_parameters p ON p.id = tip.kpi_parameter_id
-- WHERE t.code = 'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1'
-- ORDER BY ts.sort_order, ti.sort_order;
