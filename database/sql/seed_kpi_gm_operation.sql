-- =====================================================
-- KPI Template — GM Operation
-- Jalankan SETELAH:
--   1. create_kpi_master_tables.sql (+ alter_kpi_parameters_kpi_config.sql)
--   2. seed_kpi_template_justus_sample.sql  (D001-D024, KPI01/KPI06/KPI11/KPI14-16 base)
--   3. reset_kpi_master_dev.sql (optional, tahap uji)
-- File ini sudah include Key Strategy KS01/03/04/05 — tidak perlu batch justus untuk strategy.
-- =====================================================
-- Sumber data ERP (semua parameter hybrid — bisa override manual):
--   D001  Revenue MTD        → orders.grand_total
--   D002  Budget MTD         → Revenue Targets (monthly_target / forecast)
--   D048  COGS %             → Manual COGS, Deviation & Catcost
--   D049  Deviation %        → Manual COGS, Deviation & Catcost
--   D050  Category Cost %    → Manual COGS, Deviation & Catcost
--   D051  L&B %              → Asset Manual Monthly Lost & Breakage
--   D008  Petty Cash Usage   → Retail Food + Non Food (non contra bon)
--   D009  Petty Cash Budget  → Petty Cash Lock Budget (Revenue Targets)
--   D052  Labor Cost %       → Manual Monthly Labor Cost
--   D053  Resolution Hours   → CVCC (regional_assigned_at → resolved_at)
--   D054  Service Complaints → CVCC negative + CAPA Service filled
--   D055  Total Reviews      → CVCC total cases
--   D018  Training           → Just Academy (modul wajib selesai)
--   D016  QA Score           → QA2 Audit 1 (C / (C+NC))
--   D021  Visit Actual       → absensi scan IN di outlet (Regional Visit Report)
--   D022  Visit Target       → Regional Management target_outlet_visits/bulan (total)
--   D023  Closed tickets     → Ticketing System (status Closed)
--   D024  Total tickets      → Ticketing System (semua ticket aktif, excl. cancelled)
-- =====================================================

START TRANSACTION;

-- ── Key Strategies (self-contained — tidak wajib justus sample dulu) ──
INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS01', 'F&B Financial Performance & Productivity', 'Revenue, COGS, waste, spoilage, labor cost', 1, 'A', NOW(), NOW()),
('KS03', 'Customer Experience', 'Complaint resolution & service complaint ratio', 3, 'A', NOW(), NOW()),
('KS04', 'Team Development', 'Training, competency, INC program', 4, 'A', NOW(), NOW()),
('KS05', 'Compliance & Team Support', 'QA compliance, outlet visits, improvement actions', 5, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

-- ── Data parameters GM (D048+) ───────────────────────
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('D048', 'Manual COGS Ratio %',              'hybrid', 'outlet', 'percent', 'COGS % from Manual COGS menu',                    NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D049', 'Manual Deviation Ratio %',           'hybrid', 'outlet', 'percent', 'Deviation % from Manual COGS menu',             NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D050', 'Manual Category Cost Ratio %',       'hybrid', 'outlet', 'percent', 'Category cost % from Manual COGS menu',         NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D051', 'Manual Loss & Breakage Ratio %',     'hybrid', 'outlet', 'percent', 'L&B % from Asset Manual Monthly L&B',           NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D052', 'Manual Labor Cost Ratio %',          'hybrid', 'outlet', 'percent', 'Labor cost % from Manual Monthly Labor Cost',   NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D053', 'CVCC Avg Resolution Hours',          'hybrid', 'outlet', 'hours',   'Avg hours regional assign → resolved (CVCC)',     NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D054', 'CVCC Service Negative w/ CAPA',      'hybrid', 'outlet', 'integer', 'Negative CVCC + CAPA Service filled',           NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D055', 'CVCC Total Review Count',            'hybrid', 'outlet', 'integer', 'Total CVCC cases in period',                    NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `source_type` = VALUES(`source_type`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- ── Ubah parameter existing ke hybrid (GM Operation) ─
UPDATE `kpi_parameters` SET `source_type` = 'hybrid', `updated_at` = NOW()
WHERE `code` IN (
    'D001','D002','D003','D008','D009','D010','D011','D012','D013','D014',
    'D015','D016','D017','D018','D021','D022','D023','D024'
);

-- Competency & IMC — 1 parameter KPI saja (bukan D019/D020 terpisah), isi manual
UPDATE `kpi_parameters` SET `status` = 'N', `updated_at` = NOW()
WHERE `code` IN ('D019', 'D020');

UPDATE `kpi_parameters` SET
    `name` = 'Just Academy Training Completion %',
    `description` = 'Required material + quiz completion from Just Academy schedules',
    `scope_type` = 'employee',
    `updated_at` = NOW()
WHERE `code` = 'D018';

UPDATE `kpi_parameters` SET
    `name` = 'QA Audit 1 Passing Score',
    `description` = 'QA2 Audit 1 submitted — compliance C / (C+NC)',
    `updated_at` = NOW()
WHERE `code` = 'D016';

UPDATE `kpi_parameters` SET
    `name` = 'Outlet Visit Count (Attendance)',
    `description` = 'Visit days from att_log scan IN at outlet (Regional Visit)',
    `updated_at` = NOW()
WHERE `code` = 'D021';

UPDATE `kpi_parameters` SET
    `name` = 'Target Outlet Visits / Month',
    `description` = 'Target total kunjungan keseluruhan per bulan (bukan per outlet) — Regional Management',
    `scope_type` = 'employee',
    `updated_at` = NOW()
WHERE `code` = 'D022';

-- ── KPI parameters GM ────────────────────────────────
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('KPI01',  'Monthly F&B Revenue Achievement',              'hybrid', 'outlet',   'percent', 'MTD Actual Revenue vs MTD Budget',                         '>= 100%',         'higher_better', 'monthly',   'D001 / D002 * 100',       1, 'A', NOW(), NOW()),
('KPI_GM02','COGS Ratio',                                  'hybrid', 'outlet',   'percent', 'COGS ratio from Manual COGS menu',                         '<= 42-45%',       'lower_better',  'monthly',   'D048',                    1, 'A', NOW(), NOW()),
('KPI_GM03','Deviation',                                   'hybrid', 'outlet',   'percent', 'Deviation from Manual COGS menu',                          '<= 2%',           'lower_better',  'monthly',   'D049',                    1, 'A', NOW(), NOW()),
('KPI_GM04','Category Cost',                               'hybrid', 'outlet',   'percent', 'Category cost from Manual COGS menu',                      '<= 2-5%',         'lower_better',  'monthly',   'D050',                    1, 'A', NOW(), NOW()),
('KPI_GM05','Monthly Loss & Breakage Control',             'hybrid', 'outlet',   'percent', 'L&B from Asset Manual Monthly L&B',                        '<= 0.2%',         'lower_better',  'monthly',   'D051',                    1, 'A', NOW(), NOW()),
('KPI06',  'Petty Cash Usage Control',                     'hybrid', 'outlet',   'percent', 'Petty cash usage vs lock budget',                          '<= 1%',           'lower_better',  'monthly',   'D008 / D009 * 100',       1, 'A', NOW(), NOW()),
('KPI_GM07','Monthly Labor Cost',                          'hybrid', 'outlet',   'percent', 'Labor cost from Manual Monthly Labor Cost',                '<= 11-13%',       'lower_better',  'monthly',   'D052',                    1, 'A', NOW(), NOW()),
('KPI_GM08','Customer Complaint Resolution',               'hybrid', 'outlet',   'hours',   'Avg complaint resolution from CVCC',                       '<= 24 hours',     'lower_better',  'monthly',   'D053',                    1, 'A', NOW(), NOW()),
('KPI_GM09','Service Complaint Ratio',                     'hybrid', 'outlet',   'percent', 'Negative CVCC w/ Service CAPA / Total Review',             '<= 0.50%',        'lower_better',  'monthly',   'D054 / D055 * 100',       1, 'A', NOW(), NOW()),
('KPI11',  'Training Program & Module Completion',         'hybrid', 'employee', 'percent', 'Training completion',                                        '100%',            'higher_better', 'monthly',   'D018',                    1, 'A', NOW(), NOW()),
('KPI12',  'Employee Competency Assessment (Product Knowledge, Quality Consistency, Hygiene & Sanitation, Restaurant Ops, Cost Awareness, Leadership & Team Work, SOP & Prep. Understanding)', 'manual', 'employee', 'percent', 'Assessment Tool — 1 nilai agregat (input manual)', '>= 90%', 'higher_better', 'monthly', 'KPI12', 1, 'A', NOW(), NOW()),
('KPI13',  'JNG Program & Competency Task List Completion', 'manual', 'employee', 'percent', 'JNG Submission & Mentoring Progress Report (input manual)', '12 Person & 100% on Time', 'higher_better', 'quarterly', 'KPI13', 1, 'A', NOW(), NOW()),
('KPI14',  'QA Compliance & SOP Implementation',           'hybrid', 'outlet',   'percent', 'QA audit & QNS audit score',                               '>= 90%',          'higher_better', 'monthly',   'D016',                    1, 'A', NOW(), NOW()),
('KPI15',  'Outlet Visit Coverage',                        'hybrid', 'outlet',   'percent', 'Visit count vs target',                                    '>= 85%',          'higher_better', 'monthly',   'D021 / D022 * 100',       1, 'A', NOW(), NOW()),
('KPI16',  'Follow-up Improvement Action',                 'hybrid', 'outlet',   'percent', 'Closed vs total improvement actions',                      '>= 95%',          'higher_better', 'monthly',   'D023 / D024 * 100',       1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `source_type` = VALUES(`source_type`),
    `scope_type` = VALUES(`scope_type`),
    `data_type` = VALUES(`data_type`),
    `description` = VALUES(`description`),
    `target_value` = VALUES(`target_value`),
    `target_direction` = VALUES(`target_direction`),
    `frequency` = VALUES(`frequency`),
    `formula` = VALUES(`formula`),
    `updated_at` = NOW();

-- ── ERP mappings ───────────────────────────────────────
INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D001' AS code, 'daily_revenue_forecast' AS resolver_key, 'sum' AS aggregation UNION ALL
    SELECT 'D002', 'daily_revenue_forecast_budget', 'sum' UNION ALL
    SELECT 'D008', 'retail_petty_cash_usage', 'sum' UNION ALL
    SELECT 'D009', 'petty_cash_lock_budget', 'sum' UNION ALL
    SELECT 'D048', 'manual_cogs_percent', 'avg' UNION ALL
    SELECT 'D049', 'manual_deviation_percent', 'avg' UNION ALL
    SELECT 'D050', 'manual_catcost_percent', 'avg' UNION ALL
    SELECT 'D051', 'manual_lost_breakage_percent', 'avg' UNION ALL
    SELECT 'D052', 'manual_labor_cost_percent', 'avg' UNION ALL
    SELECT 'D026', 'manual_google_review_rating_avg', 'avg' UNION ALL
    SELECT 'D053', 'cvcc_avg_resolution_hours', 'avg' UNION ALL
    SELECT 'D054', 'cvcc_service_negative_complaint_count', 'count' UNION ALL
    SELECT 'D055', 'cvcc_total_review_count', 'count' UNION ALL
    SELECT 'D018', 'just_academy_training_completion', 'avg' UNION ALL
    SELECT 'D016', 'qa2_audit1_score', 'avg' UNION ALL
    SELECT 'D021', 'regional_visit_report', 'count' UNION ALL
    SELECT 'D022', 'regional_target_outlet_visits', 'sum' UNION ALL
    SELECT 'D023', 'ticket_improvement_closed', 'count' UNION ALL
    SELECT 'D024', 'ticket_improvement_total', 'count'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE
    `resolver_key` = VALUES(`resolver_key`),
    `aggregation` = VALUES(`aggregation`),
    `updated_at` = NOW();

-- ── Template header ────────────────────────────────────
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `erp_data_scope`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_GM_OPERATION_v1',
    'GM Operation',
    'Template KPI GM Operation — F&B Financial, Customer Experience, Team Development, Compliance.',
    1,
    'draft',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'all_outlets',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_GM_OPERATION_v1');

UPDATE `kpi_templates`
SET
    `name` = 'GM Operation',
    `description` = 'Template KPI GM Operation — F&B Financial, Customer Experience, Team Development, Compliance.',
    `erp_data_scope` = 'all_outlets',
    `updated_at` = NOW()
WHERE `code` = 'KPI_GM_OPERATION_v1';

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_GM_OPERATION_v1' LIMIT 1);

-- ── Jabatan GM Operation ───────────────────────────────
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (
      j.nama_jabatan LIKE '%GM Operation%'
      OR j.nama_jabatan LIKE '%GM%Operation%'
      OR j.nama_jabatan LIKE '%General Manager%Operation%'
  )
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  );

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

-- Strategy bobot = total item weight per blok (33 + 15 + 25 + 27 = 100)
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'KS01' AS code, 33.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS03', 15.00, 1 UNION ALL
    SELECT 'KS04', 25.00, 2 UNION ALL
    SELECT 'KS05', 27.00, 3
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.code;

-- ── KPI items (bobot = weight % portfolio, total = 100) ──
INSERT INTO `kpi_template_items` (
    `kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT ts.id, p.name, v.weight, p.target_value, p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT 'KS01' AS ks_code, 'KPI01'     AS kpi_code,  6.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS01', 'KPI_GM02',  6.00, 1 UNION ALL
    SELECT 'KS01', 'KPI_GM03',  2.00, 2 UNION ALL
    SELECT 'KS01', 'KPI_GM04',  3.00, 3 UNION ALL
    SELECT 'KS01', 'KPI_GM05',  3.00, 4 UNION ALL
    SELECT 'KS01', 'KPI06',     6.00, 5 UNION ALL
    SELECT 'KS01', 'KPI_GM07',  7.00, 6 UNION ALL
    SELECT 'KS03', 'KPI_GM08', 10.00, 0 UNION ALL
    SELECT 'KS03', 'KPI_GM09',  5.00, 1 UNION ALL
    SELECT 'KS04', 'KPI11',     8.00, 0 UNION ALL
    SELECT 'KS04', 'KPI12',    10.00, 1 UNION ALL
    SELECT 'KS04', 'KPI13',     7.00, 2 UNION ALL
    SELECT 'KS05', 'KPI14',    10.00, 0 UNION ALL
    SELECT 'KS05', 'KPI15',    10.00, 1 UNION ALL
    SELECT 'KS05', 'KPI16',     7.00, 2
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
    SELECT 'KS01' AS ks_code, 'KPI01' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT 'KS01', 'KPI_GM02', 1 UNION ALL SELECT 'KS01', 'KPI_GM03', 2 UNION ALL
    SELECT 'KS01', 'KPI_GM04', 3 UNION ALL SELECT 'KS01', 'KPI_GM05', 4 UNION ALL
    SELECT 'KS01', 'KPI06', 5 UNION ALL SELECT 'KS01', 'KPI_GM07', 6 UNION ALL
    SELECT 'KS03', 'KPI_GM08', 0 UNION ALL SELECT 'KS03', 'KPI_GM09', 1 UNION ALL
    SELECT 'KS04', 'KPI11', 0 UNION ALL SELECT 'KS04', 'KPI12', 1 UNION ALL SELECT 'KS04', 'KPI13', 2 UNION ALL
    SELECT 'KS05', 'KPI14', 0 UNION ALL SELECT 'KS05', 'KPI15', 1 UNION ALL SELECT 'KS05', 'KPI16', 2
) v ON ks.code = v.ks_code AND ti.sort_order = v.sort_order
JOIN `kpi_parameters` p ON p.code = v.kpi_code AND p.status = 'A';

COMMIT;

-- Verifikasi:
-- SELECT ks.name, ts.weight_percent, ti.name, ti.weight_percent, p.code, p.formula
-- FROM kpi_templates t
-- JOIN kpi_template_strategies ts ON ts.kpi_template_id = t.id
-- JOIN kpi_key_strategies ks ON ks.id = ts.kpi_key_strategy_id
-- JOIN kpi_template_items ti ON ti.kpi_template_strategy_id = ts.id
-- JOIN kpi_template_item_parameters tip ON tip.kpi_template_item_id = ti.id
-- JOIN kpi_parameters p ON p.id = tip.kpi_parameter_id
-- WHERE t.code = 'KPI_GM_OPERATION_v1'
-- ORDER BY ts.sort_order, ti.sort_order;
