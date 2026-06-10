-- =====================================================
-- KPI Master — seed JUSTUS GROUP (v2)
-- Jalankan setelah:
--   1. create_kpi_master_tables.sql
--   2. alter_kpi_parameters_kpi_config.sql
-- =====================================================
-- Struktur:
--   D001-D024 = data parameter (sumber ERP/manual, dipakai di formula)
--   KPI01-KPI16 = KPI parameter (lengkap: target, direction, frequency, formula)
--   Template    = pilih KPI parameter + bobot saja
-- =====================================================

START TRANSACTION;

-- ── Key Strategies ───────────────────────────────────
INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS01', 'F&B Financial Performance & Productivity', 'Revenue, COGS, waste, spoilage, labor cost', 1, 'A', NOW(), NOW()),
('KS02', 'Service Operational Excellence', 'SOP compliance, on-time orders', 2, 'A', NOW(), NOW()),
('KS03', 'Customer Experience', 'Complaint resolution & service complaint ratio', 3, 'A', NOW(), NOW()),
('KS04', 'Team Development', 'Training, competency, INC program', 4, 'A', NOW(), NOW()),
('KS05', 'Compliance & Team Support', 'QA compliance, outlet visits, improvement actions', 5, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

-- ── Data Parameters (sumber nilai, tidak dipilih di template) ──
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('D001', 'MTD Actual F&B Revenue',         'erp',    'outlet',   'decimal', 'Actual F&B revenue MTD',         NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D002', 'MTD Budget F&B Revenue',         'erp',    'outlet',   'decimal', 'Budget F&B revenue MTD',         NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D003', 'MTD COGS Amount',                'erp',    'outlet',   'decimal', 'Cost of goods sold MTD',         NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D004', 'MTD COGS Budget',                'erp',    'outlet',   'decimal', 'COGS budget MTD',                NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D005', 'MTD Waste Amount',               'erp',    'outlet',   'decimal', 'Waste amount MTD',               NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D006', 'MTD Spoilage Amount',            'erp',    'outlet',   'decimal', 'Spoilage amount MTD',            NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D007', 'MTD Loss & Breakage Amount',     'erp',    'outlet',   'decimal', 'Loss & breakage MTD',          NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D008', 'MTD Petty Cash Usage',           'erp',    'outlet',   'decimal', 'Petty cash usage MTD',         NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D009', 'MTD Petty Cash Budget',          'erp',    'outlet',   'decimal', 'Petty cash budget MTD',        NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D010', 'MTD Payroll & Related',          'erp',    'outlet',   'decimal', 'Payroll & related MTD',        NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D011', 'Total Orders',                   'hybrid', 'outlet',   'integer', 'Total orders in period',       NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D012', 'On-Time Orders',                 'hybrid', 'outlet',   'integer', 'On-time orders in period',     NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D013', 'Total Customer Reviews',         'erp',    'outlet',   'integer', 'Guest comment + review count', NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D014', 'Number of Complaints',           'erp',    'outlet',   'integer', 'Complaints in period',         NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D015', 'Avg Complaint Resolution Hours', 'hybrid', 'outlet',   'hours',   'Avg hours to close complaint', NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D016', 'QA Audit Passing Score',         'hybrid', 'outlet',   'percent', 'QA audit passing score',       NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D017', 'SOP Compliance Score',           'hybrid', 'outlet',   'percent', 'SOP / mystery guest score',    NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D018', 'Training Completion %',          'erp',    'employee', 'percent', 'Training completion %',        NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D019', 'Competency Assessment Score',    'hybrid', 'employee', 'percent', 'Assessment test avg score',    NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D020', 'INC Program Completion',         'manual', 'employee', 'percent', 'INC program completion',       NULL, 'higher_better', 'monthly', NULL, 0, 'A', NOW(), NOW()),
('D021', 'Outlet Visit Count',             'erp',    'outlet',   'integer', 'Regional/ops visit count',     NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D022', 'Target Outlet Visits',           'manual', 'outlet',   'integer', 'Target visit count',           NULL, 'higher_better', 'monthly', NULL, 0, 'A', NOW(), NOW()),
('D023', 'Improvement Actions Closed',     'erp',    'outlet',   'integer', 'Closed improvement tickets',   NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D024', 'Total Improvement Actions',      'erp',    'outlet',   'integer', 'Total improvement tickets',    NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `source_type` = VALUES(`source_type`),
    `scope_type` = VALUES(`scope_type`),
    `data_type` = VALUES(`data_type`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- ── KPI Parameters (dipilih di template — config lengkap) ──
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('KPI01', 'Monthly F&B Revenue Achievement',        'erp',    'outlet',   'percent', 'Revenue vs budget MTD',              '100%',            'higher_better', 'monthly', 'D001 / D002 * 100',       1, 'A', NOW(), NOW()),
('KPI02', 'COGS Ratio',                               'erp',    'outlet',   'percent', 'COGS vs revenue MTD',                'Based on Budget', 'lower_better',  'monthly', 'D003 / D001 * 100',       1, 'A', NOW(), NOW()),
('KPI03', 'Waste Ratio',                              'erp',    'outlet',   'percent', 'Waste vs revenue MTD',               '<= target %',     'lower_better',  'monthly', 'D005 / D001 * 100',       1, 'A', NOW(), NOW()),
('KPI04', 'Spoilage Ratio',                           'erp',    'outlet',   'percent', 'Spoilage vs revenue MTD',            '<= target %',     'lower_better',  'monthly', 'D006 / D001 * 100',       1, 'A', NOW(), NOW()),
('KPI05', 'Monthly Loss & Breakage Control',          'erp',    'outlet',   'decimal', 'Loss & breakage control',            '<= budget',       'lower_better',  'monthly', 'D007',                    1, 'A', NOW(), NOW()),
('KPI06', 'Petty Cash Usage Control',                 'erp',    'outlet',   'percent', 'Petty cash vs budget',               '<= budget',       'lower_better',  'monthly', 'D008 / D009 * 100',       1, 'A', NOW(), NOW()),
('KPI07', 'SOP Compliance & Standardization',         'hybrid', 'outlet',   'percent', 'SOP compliance score',               '>= target score','higher_better', 'monthly', 'D017',                    1, 'A', NOW(), NOW()),
('KPI08', 'On-Time Order Compliance',                 'hybrid', 'outlet',   'percent', 'On-time order percentage',           '>= 95%',          'higher_better', 'monthly', 'D012 / D011 * 100',       1, 'A', NOW(), NOW()),
('KPI09', 'Customer Complaint Resolution',            'hybrid', 'outlet',   'hours',   'Avg complaint resolution time',      '<= 24 hours',     'lower_better',  'monthly', 'D015',                    1, 'A', NOW(), NOW()),
('KPI10', 'Service Complaint Ratio',                  'erp',    'outlet',   'percent', 'Complaints vs total reviews',        '<= target %',     'lower_better',  'monthly', 'D014 / D013 * 100',       1, 'A', NOW(), NOW()),
('KPI11', 'Training Program & Module Completion',     'erp',    'employee', 'percent', 'Training completion',                '100%',            'higher_better', 'monthly', 'D018',                    1, 'A', NOW(), NOW()),
('KPI12', 'Competency Improvement',                   'hybrid', 'employee', 'percent', 'Competency assessment score',        '>= target score','higher_better', 'monthly', 'D019',                    1, 'A', NOW(), NOW()),
('KPI13', 'INC Program & Competency Test Completion', 'manual', 'employee', 'percent', 'INC program completion',             '100%',            'higher_better', 'monthly', 'D020',                    0, 'A', NOW(), NOW()),
('KPI14', 'QA Compliance (Service & Hospitality)',    'hybrid', 'outlet',   'percent', 'QA audit passing score',             '>= passing score','higher_better', 'monthly', 'D016',                    1, 'A', NOW(), NOW()),
('KPI15', 'Outlet Visit Coverage',                    'erp',    'outlet',   'percent', 'Visit count vs target',              '100%',            'higher_better', 'monthly', 'D021 / D022 * 100',       1, 'A', NOW(), NOW()),
('KPI16', 'Follow-up Improvement Action',             'erp',    'outlet',   'percent', 'Closed vs total improvement actions','100% closed',     'higher_better', 'monthly', 'D023 / D024 * 100',       1, 'A', NOW(), NOW())
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

-- ── ERP mappings (data parameter D* saja) ──────────────
INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'D001' AS code, 'daily_revenue_forecast' AS resolver_key, 'sum' AS aggregation UNION ALL
    SELECT 'D002', 'daily_revenue_forecast_budget', 'sum' UNION ALL
    SELECT 'D011', 'pos_order_count', 'count' UNION ALL
    SELECT 'D003', 'cost_report_cogs', 'sum' UNION ALL
    SELECT 'D005', 'outlet_internal_use_waste', 'sum' UNION ALL
    SELECT 'D006', 'outlet_internal_use_waste', 'sum' UNION ALL
    SELECT 'D007', 'lost_breakage', 'sum' UNION ALL
    SELECT 'D008', 'outlet_analyzer_petty_cash', 'sum' UNION ALL
    SELECT 'D010', 'outlet_analyzer_payroll', 'sum' UNION ALL
    SELECT 'D013', 'guest_comment_gsi', 'count' UNION ALL
    SELECT 'D014', 'ticket_complaint_count', 'count' UNION ALL
    SELECT 'D018', 'training_compliance', 'avg' UNION ALL
    SELECT 'D021', 'regional_visit_report', 'count' UNION ALL
    SELECT 'D023', 'ticket_improvement_closed', 'count' UNION ALL
    SELECT 'D024', 'ticket_improvement_total', 'count'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `updated_at` = NOW();

-- ── Template header ────────────────────────────────────
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_OUTLET_MANAGER_v1',
    'KPI Outlet Manager — JUSTUS GROUP',
    'Template contoh JUSTUS GROUP. Pilih KPI parameter + bobot. Total strategy & KPI = 100%.',
    2,
    'draft',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_OUTLET_MANAGER_v1');

UPDATE `kpi_templates`
SET
    `name` = 'KPI Outlet Manager — JUSTUS GROUP',
    `description` = 'Template contoh JUSTUS GROUP. Pilih KPI parameter + bobot. Total strategy & KPI = 100%.',
    `version` = 2,
    `updated_at` = NOW()
WHERE `code` = 'KPI_OUTLET_MANAGER_v1';

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_OUTLET_MANAGER_v1' LIMIT 1);

-- ── Bridging jabatan ───────────────────────────────────
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (j.nama_jabatan LIKE '%Outlet Manager%' OR j.nama_jabatan LIKE '%Manager Outlet%')
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  )
LIMIT 5;

-- Reset isi template (re-seed aman)
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

-- ── Strategy rows (bobot total = 100%) ─────────────────
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'KS01' AS code, 40.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS02', 15.00, 1 UNION ALL
    SELECT 'KS03', 15.00, 2 UNION ALL
    SELECT 'KS04', 15.00, 3 UNION ALL
    SELECT 'KS05', 15.00, 4
) v
JOIN `kpi_key_strategies` ks ON ks.code = v.code;

SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS01' LIMIT 1);
SET @s02 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS02' LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS03' LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS04' LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS05' LIMIT 1);

-- ── KPI Items: snapshot dari KPI parameter + bobot ─────
INSERT INTO `kpi_template_items` (
    `kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT v.strategy_id, p.name, v.weight, p.target_value, p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s01 AS strategy_id, 'KPI01' AS kpi_code, 15.00 AS weight, 0 AS sort_order UNION ALL
    SELECT @s01, 'KPI02', 10.00, 1 UNION ALL
    SELECT @s01, 'KPI03',  5.00, 2 UNION ALL
    SELECT @s01, 'KPI04',  5.00, 3 UNION ALL
    SELECT @s01, 'KPI05',  3.00, 4 UNION ALL
    SELECT @s01, 'KPI06',  2.00, 5 UNION ALL
    SELECT @s02, 'KPI07',  7.50, 0 UNION ALL
    SELECT @s02, 'KPI08',  7.50, 1 UNION ALL
    SELECT @s03, 'KPI09',  7.50, 0 UNION ALL
    SELECT @s03, 'KPI10',  7.50, 1 UNION ALL
    SELECT @s04, 'KPI11',  5.00, 0 UNION ALL
    SELECT @s04, 'KPI12',  5.00, 1 UNION ALL
    SELECT @s04, 'KPI13',  5.00, 2 UNION ALL
    SELECT @s05, 'KPI14',  5.00, 0 UNION ALL
    SELECT @s05, 'KPI15',  5.00, 1 UNION ALL
    SELECT @s05, 'KPI16',  5.00, 2
) v
JOIN `kpi_parameters` p ON p.code = v.kpi_code;

-- ── Link 1 parameter per KPI item ──────────────────────
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN (
    SELECT @s01 AS strategy_id, 'KPI01' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT @s01, 'KPI02', 1 UNION ALL
    SELECT @s01, 'KPI03', 2 UNION ALL
    SELECT @s01, 'KPI04', 3 UNION ALL
    SELECT @s01, 'KPI05', 4 UNION ALL
    SELECT @s01, 'KPI06', 5 UNION ALL
    SELECT @s02, 'KPI07', 0 UNION ALL
    SELECT @s02, 'KPI08', 1 UNION ALL
    SELECT @s03, 'KPI09', 0 UNION ALL
    SELECT @s03, 'KPI10', 1 UNION ALL
    SELECT @s04, 'KPI11', 0 UNION ALL
    SELECT @s04, 'KPI12', 1 UNION ALL
    SELECT @s04, 'KPI13', 2 UNION ALL
    SELECT @s05, 'KPI14', 0 UNION ALL
    SELECT @s05, 'KPI15', 1 UNION ALL
    SELECT @s05, 'KPI16', 2
) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = v.kpi_code;

COMMIT;

-- ── Verifikasi ─────────────────────────────────────────
-- SELECT code, name, target_value, target_direction, frequency, formula FROM kpi_parameters WHERE code LIKE 'KPI%' ORDER BY code;
-- SELECT ks.name, ts.weight_percent, ti.name, ti.weight_percent, p.code
-- FROM kpi_templates t
-- JOIN kpi_template_strategies ts ON ts.kpi_template_id = t.id
-- JOIN kpi_key_strategies ks ON ks.id = ts.kpi_key_strategy_id
-- JOIN kpi_template_items ti ON ti.kpi_template_strategy_id = ts.id
-- JOIN kpi_template_item_parameters tip ON tip.kpi_template_item_id = ti.id
-- JOIN kpi_parameters p ON p.id = tip.kpi_parameter_id
-- WHERE t.code = 'KPI_OUTLET_MANAGER_v1'
-- ORDER BY ts.sort_order, ti.sort_order;
