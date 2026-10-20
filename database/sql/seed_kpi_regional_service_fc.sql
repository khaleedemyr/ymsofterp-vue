-- =====================================================
-- KPI Template — Reg. Service Manager FC
-- Jalankan SETELAH:
--   1. seed_kpi_template_justus_sample.sql
--   2. seed_kpi_parameters_extended.sql
--   3. seed_kpi_gm_operation.sql
-- Parameter TIDAK diduplikasi — hanya referensi kode yang sudah ada.
-- =====================================================
-- Struktur (sesuai Excel Reg. Service Manager FC):
--   KS03 Customer Experience       22% → KPI_GM08, KPI_GM09, KPI18
--   KS06 Sales & Upselling         14% → KPI19, KPI20
--   KS04 Team Development          34% → KPI12, KPI21, KPI22  (tanpa JNG/KPI13)
--   KS05 Compliance & Team Support 30% → KPI14, KPI15, KPI16
-- Total weight = 100%
-- =====================================================
-- Jabatan:
--   • Regional Service Manager Food Court
-- =====================================================

START TRANSACTION;

INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS06', 'Sales & Upselling', 'Upselling achievement & average check growth', 6, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

-- ── Template header ────────────────────────────────────
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `erp_data_scope`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_REGIONAL_SERVICE_FC_v1',
    'Reg. Service Manager FC',
    'Template KPI Reg. Service Manager FC — Customer Experience, Sales & Upselling, Team Development, Compliance.',
    1,
    'active',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'all_outlets',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_REGIONAL_SERVICE_FC_v1');

UPDATE `kpi_templates`
SET
    `name` = 'Reg. Service Manager FC',
    `description` = 'Template KPI Reg. Service Manager FC — Customer Experience, Sales & Upselling, Team Development, Compliance.',
    `template_status` = 'active',
    `erp_data_scope` = 'all_outlets',
    `scoring_rules` = '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    `updated_at` = NOW()
WHERE `code` = 'KPI_REGIONAL_SERVICE_FC_v1';

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_REGIONAL_SERVICE_FC_v1' LIMIT 1);

-- ── Jabatan ───────────────────────────────────────────
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (
      j.nama_jabatan LIKE '%Regional Service Manager%Food Court%'
      OR j.nama_jabatan LIKE '%Regional Service Manager Food Court%'
      OR j.nama_jabatan LIKE '%Regional Service FC%'
  )
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  );

-- Hapus struktur lama lalu rebuild (idempotent)
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
    SELECT 'KS03' AS code, 22.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS06', 14.00, 1 UNION ALL
    SELECT 'KS04', 34.00, 2 UNION ALL
    SELECT 'KS05', 30.00, 3
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.code;

-- ── KPI items (bobot portfolio, total = 100; JNG/KPI13 dihilangkan, bobot 8% dialihkan ke Team Development)
INSERT INTO `kpi_template_items` (
    `kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT ts.id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, COALESCE(v.freq, p.frequency), p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    -- KS03 Customer Experience (22%)
    SELECT 'KS03' AS ks_code, 'KPI_GM08' AS kpi_code,  8.00 AS weight, 0 AS sort_order, '<= 24 Hours' AS tgt, NULL AS freq UNION ALL
    SELECT 'KS03', 'KPI_GM09',  7.00, 1, '<= 0.50%', NULL UNION ALL
    SELECT 'KS03', 'KPI18',     7.00, 2, '>= 4.5', NULL UNION ALL
    -- KS06 Sales & Upselling (14%)
    SELECT 'KS06', 'KPI19',     8.00, 0, '>= 90%', NULL UNION ALL
    SELECT 'KS06', 'KPI20',     6.00, 1, '>= 10%', NULL UNION ALL
    -- KS04 Team Development (34%) — tanpa JNG; Excel 15+8+5+6 → tanpa JNG jadi 18+8+8
    SELECT 'KS04', 'KPI12',    18.00, 0, '>= 90%', NULL UNION ALL
    SELECT 'KS04', 'KPI21',     8.00, 1, '100% on Time', NULL UNION ALL
    SELECT 'KS04', 'KPI22',     8.00, 2, '>= 2 Person', 'daily' UNION ALL
    -- KS05 Compliance & Team Support (30%)
    SELECT 'KS05', 'KPI14',    10.00, 0, '>= 90%', NULL UNION ALL
    SELECT 'KS05', 'KPI15',    10.00, 1, '>= 85%', NULL UNION ALL
    SELECT 'KS05', 'KPI16',    10.00, 2, '>= 95%', NULL
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
    SELECT 'KS03' AS ks_code, 'KPI_GM08' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT 'KS03', 'KPI_GM09', 1 UNION ALL SELECT 'KS03', 'KPI18', 2 UNION ALL
    SELECT 'KS06', 'KPI19', 0 UNION ALL SELECT 'KS06', 'KPI20', 1 UNION ALL
    SELECT 'KS04', 'KPI12', 0 UNION ALL SELECT 'KS04', 'KPI21', 1 UNION ALL SELECT 'KS04', 'KPI22', 2 UNION ALL
    SELECT 'KS05', 'KPI14', 0 UNION ALL SELECT 'KS05', 'KPI15', 1 UNION ALL SELECT 'KS05', 'KPI16', 2
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
-- WHERE t.code = 'KPI_REGIONAL_SERVICE_FC_v1'
-- ORDER BY ts.sort_order, ti.sort_order;
