-- =====================================================
-- KPI Master — seed lengkap (parameter + template JUSTUS)
-- Jalankan setelah: create_kpi_master_tables.sql
-- Urutan: seed_kpi_master_sample.sql (key strategy) ATAU file ini (all-in-one)
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

-- ── Parameters ─────────────────────────────────────────
INSERT INTO `kpi_parameters` (`code`, `name`, `source_type`, `scope_type`, `data_type`, `description`, `is_shared`, `status`, `created_at`, `updated_at`) VALUES
('P001', 'MTD Actual F&B Revenue',           'erp',    'outlet',   'decimal', 'Actual F&B revenue MTD', 1, 'A', NOW(), NOW()),
('P002', 'MTD Budget F&B Revenue',           'erp',    'outlet',   'decimal', 'Budget F&B revenue MTD', 1, 'A', NOW(), NOW()),
('P003', 'MTD COGS Amount',                  'erp',    'outlet',   'decimal', 'Cost of goods sold MTD', 1, 'A', NOW(), NOW()),
('P004', 'MTD COGS Budget',                  'erp',    'outlet',   'decimal', 'COGS budget MTD', 1, 'A', NOW(), NOW()),
('P005', 'MTD Waste Amount',                 'erp',    'outlet',   'decimal', 'Waste amount MTD', 1, 'A', NOW(), NOW()),
('P006', 'MTD Spoilage Amount',              'erp',    'outlet',   'decimal', 'Spoilage amount MTD', 1, 'A', NOW(), NOW()),
('P007', 'MTD Loss & Breakage Amount',       'erp',    'outlet',   'decimal', 'Loss & breakage MTD', 1, 'A', NOW(), NOW()),
('P008', 'MTD Petty Cash Usage',             'erp',    'outlet',   'decimal', 'Petty cash usage MTD', 1, 'A', NOW(), NOW()),
('P009', 'MTD Petty Cash Budget',            'erp',    'outlet',   'decimal', 'Petty cash budget MTD', 1, 'A', NOW(), NOW()),
('P010', 'MTD Payroll & Related',            'erp',    'outlet',   'decimal', 'Payroll & related MTD', 1, 'A', NOW(), NOW()),
('P011', 'Total Orders',                     'hybrid', 'outlet',   'integer', 'Total orders in period', 1, 'A', NOW(), NOW()),
('P012', 'On-Time Orders',                   'hybrid', 'outlet',   'integer', 'On-time orders in period', 1, 'A', NOW(), NOW()),
('P013', 'Total Customer Reviews',           'erp',    'outlet',   'integer', 'Guest comment + review count', 1, 'A', NOW(), NOW()),
('P014', 'Number of Complaints',             'erp',    'outlet',   'integer', 'Complaints in period', 1, 'A', NOW(), NOW()),
('P015', 'Avg Complaint Resolution Hours',   'hybrid', 'outlet',   'hours',   'Average hours to close complaint', 1, 'A', NOW(), NOW()),
('P016', 'QA Audit Passing Score',           'hybrid', 'outlet',   'percent', 'QA audit passing score', 1, 'A', NOW(), NOW()),
('P017', 'SOP Compliance Score',             'hybrid', 'outlet',   'percent', 'SOP / mystery guest score', 1, 'A', NOW(), NOW()),
('P018', 'Training Completion %',            'erp',    'employee', 'percent', 'Training completion percentage', 1, 'A', NOW(), NOW()),
('P019', 'Competency Assessment Score',      'hybrid', 'employee', 'percent', 'Assessment test average score', 1, 'A', NOW(), NOW()),
('P020', 'INC Program Completion',           'manual', 'employee', 'percent', 'INC program completion', 0, 'A', NOW(), NOW()),
('P021', 'Outlet Visit Count',               'erp',    'outlet',   'integer', 'Regional/ops visit count', 1, 'A', NOW(), NOW()),
('P022', 'Target Outlet Visits',             'manual', 'outlet',   'integer', 'Target visit count per period', 0, 'A', NOW(), NOW()),
('P023', 'Improvement Actions Closed',       'erp',    'outlet',   'integer', 'Closed improvement/CAPA tickets', 1, 'A', NOW(), NOW()),
('P024', 'Total Improvement Actions',        'erp',    'outlet',   'integer', 'Total improvement/CAPA tickets', 1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `source_type` = VALUES(`source_type`), `updated_at` = NOW();

-- ── ERP mappings (parameter ERP/hybrid) ────────────────
INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    SELECT 'P001' AS code, 'daily_revenue_forecast' AS resolver_key, 'sum' AS aggregation UNION ALL
    SELECT 'P002', 'daily_revenue_forecast_budget', 'sum' UNION ALL
    SELECT 'P003', 'cost_report_cogs', 'sum' UNION ALL
    SELECT 'P005', 'outlet_internal_use_waste', 'sum' UNION ALL
    SELECT 'P006', 'outlet_internal_use_waste', 'sum' UNION ALL
    SELECT 'P007', 'lost_breakage', 'sum' UNION ALL
    SELECT 'P008', 'outlet_analyzer_petty_cash', 'sum' UNION ALL
    SELECT 'P010', 'outlet_analyzer_payroll', 'sum' UNION ALL
    SELECT 'P013', 'guest_comment_gsi', 'count' UNION ALL
    SELECT 'P014', 'ticket_complaint_count', 'count' UNION ALL
    SELECT 'P018', 'training_compliance', 'avg' UNION ALL
    SELECT 'P021', 'regional_visit_report', 'count' UNION ALL
    SELECT 'P023', 'ticket_improvement_closed', 'count' UNION ALL
    SELECT 'P024', 'ticket_improvement_total', 'count'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `updated_at` = NOW();

-- ── Template header ────────────────────────────────────
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT
    'KPI_OUTLET_MANAGER_v1',
    'KPI Outlet Manager — JUSTUS GROUP',
    'Template contoh dari form KPI JUSTUS GROUP. Bobot strategy & KPI total 100%.',
    1,
    'draft',
    '{"exceeding_min":100,"meeting_min":85,"below_max":85}',
    'A',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = 'KPI_OUTLET_MANAGER_v1');

SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = 'KPI_OUTLET_MANAGER_v1' LIMIT 1);

-- ── Bridging jabatan (sesuaikan LIKE jika nama jabatan beda) ──
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, CURDATE(), 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A'
  AND (j.nama_jabatan LIKE '%Outlet Manager%' OR j.nama_jabatan LIKE '%Manager Outlet%')
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_template_positions` tp
      WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan
  )
LIMIT 5;

-- Hapus strategy lama template ini (re-seed aman)
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

-- ── Strategy rows (total bobot = 100%) ─────────────────
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

-- Helper: strategy id by code
SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS01' LIMIT 1);
SET @s02 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS02' LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS03' LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS04' LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = 'KS05' LIMIT 1);

-- ── KPI Items (total bobot semua item = 100%) ──────────
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
-- KS01: F&B Financial (40%)
(@s01, 'Monthly F&B Revenue Achievement',       15.00, '100%',              'higher_better', 'monthly', 'P001 / P002 * 100', 0, 'A', NOW(), NOW()),
(@s01, 'COGS Ratio',                             10.00, 'Based on Budget',   'lower_better',  'monthly', 'P003 / P001 * 100', 1, 'A', NOW(), NOW()),
(@s01, 'Waste Ratio',                             5.00, '<= target %',       'lower_better',  'monthly', 'P005 / P001 * 100', 2, 'A', NOW(), NOW()),
(@s01, 'Spoilage Ratio',                          5.00, '<= target %',       'lower_better',  'monthly', 'P006 / P001 * 100', 3, 'A', NOW(), NOW()),
(@s01, 'Monthly Loss & Breakage Control',           3.00, '<= budget',         'lower_better',  'monthly', 'P007',              4, 'A', NOW(), NOW()),
(@s01, 'Petty Cash Usage Control',                2.00, '<= budget',         'lower_better',  'monthly', 'P008 / P009 * 100', 5, 'A', NOW(), NOW()),
-- KS02: Service Ops (15%)
(@s02, 'SOP Compliance & Standardization',        7.50, '>= target score',   'higher_better', 'monthly', 'P017',              0, 'A', NOW(), NOW()),
(@s02, 'On-Time Order Compliance',                7.50, '>= 95%',            'higher_better', 'monthly', 'P012 / P011 * 100', 1, 'A', NOW(), NOW()),
-- KS03: Customer Experience (15%)
(@s03, 'Customer Complaint Resolution',           7.50, '<= 24 hours',       'lower_better',  'monthly', 'P015',              0, 'A', NOW(), NOW()),
(@s03, 'Service Complaint Ratio',                 7.50, '<= target %',       'lower_better',  'monthly', 'P014 / P013 * 100', 1, 'A', NOW(), NOW()),
-- KS04: Team Development (15%)
(@s04, 'Training Program & Module Completion',    5.00, '100%',              'higher_better', 'monthly', 'P018',              0, 'A', NOW(), NOW()),
(@s04, 'Competency Improvement',                  5.00, '>= target score',   'higher_better', 'monthly', 'P019',              1, 'A', NOW(), NOW()),
(@s04, 'INC Program & Competency Test Completion', 5.00, '100%',           'higher_better', 'monthly', 'P020',              2, 'A', NOW(), NOW()),
-- KS05: Compliance & Support (15%)
(@s05, 'QA Compliance (Service & Hospitality)', 5.00, '>= passing score',  'higher_better', 'monthly', 'P016',              0, 'A', NOW(), NOW()),
(@s05, 'Outlet Visit Coverage',                   5.00, '100%',              'higher_better', 'monthly', 'P021 / P022 * 100', 1, 'A', NOW(), NOW()),
(@s05, 'Follow-up Improvement Action',            5.00, '100% closed',       'higher_better', 'monthly', 'P023 / P024 * 100', 2, 'A', NOW(), NOW());

-- ── Link parameter ke KPI items ───────────────────────
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'Monthly F&B Revenue Achievement' AS item_name, 'P001' AS param_code, 0 AS sort_order UNION ALL
    SELECT 'Monthly F&B Revenue Achievement', 'P002', 1 UNION ALL
    SELECT 'COGS Ratio', 'P003', 0 UNION ALL
    SELECT 'COGS Ratio', 'P001', 1 UNION ALL
    SELECT 'Waste Ratio', 'P005', 0 UNION ALL
    SELECT 'Waste Ratio', 'P001', 1 UNION ALL
    SELECT 'Spoilage Ratio', 'P006', 0 UNION ALL
    SELECT 'Spoilage Ratio', 'P001', 1 UNION ALL
    SELECT 'Monthly Loss & Breakage Control', 'P007', 0 UNION ALL
    SELECT 'Petty Cash Usage Control', 'P008', 0 UNION ALL
    SELECT 'Petty Cash Usage Control', 'P009', 1 UNION ALL
    SELECT 'SOP Compliance & Standardization', 'P017', 0 UNION ALL
    SELECT 'On-Time Order Compliance', 'P012', 0 UNION ALL
    SELECT 'On-Time Order Compliance', 'P011', 1 UNION ALL
    SELECT 'Customer Complaint Resolution', 'P015', 0 UNION ALL
    SELECT 'Service Complaint Ratio', 'P014', 0 UNION ALL
    SELECT 'Service Complaint Ratio', 'P013', 1 UNION ALL
    SELECT 'Training Program & Module Completion', 'P018', 0 UNION ALL
    SELECT 'Competency Improvement', 'P019', 0 UNION ALL
    SELECT 'INC Program & Competency Test Completion', 'P020', 0 UNION ALL
    SELECT 'QA Compliance (Service & Hospitality)', 'P016', 0 UNION ALL
    SELECT 'Outlet Visit Coverage', 'P021', 0 UNION ALL
    SELECT 'Outlet Visit Coverage', 'P022', 1 UNION ALL
    SELECT 'Follow-up Improvement Action', 'P023', 0 UNION ALL
    SELECT 'Follow-up Improvement Action', 'P024', 1
) v
JOIN `kpi_template_items` ti ON ti.name = v.item_name AND ti.kpi_template_strategy_id IN (@s01, @s02, @s03, @s04, @s05)
JOIN `kpi_parameters` p ON p.code = v.param_code
ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`), `updated_at` = NOW();

COMMIT;

-- Cek hasil:
-- SELECT * FROM kpi_templates WHERE code = 'KPI_OUTLET_MANAGER_v1';
-- SELECT ks.name, ts.weight_percent FROM kpi_template_strategies ts JOIN kpi_key_strategies ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id;
