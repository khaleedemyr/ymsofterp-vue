-- =====================================================
-- KPI Parameters — extended catalog (reuse D001-D024, KPI01-KPI16)
-- Aman dijalankan ulang: ON DUPLICATE KEY UPDATE, tidak duplikat kode
-- Jalankan SETELAH seed_kpi_template_justus_sample.sql
-- =====================================================

START TRANSACTION;

-- ── Key Strategies tambahan ───────────────────────────
INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS06', 'Sales & Upselling', 'Upselling achievement & average check growth', 6, 'A', NOW(), NOW()),
('KS07', 'Product Development & Innovation', 'New products, innovation success, benchmarking', 7, 'A', NOW(), NOW()),
('KS08', 'Kitchen Operational Excellence', 'Recipe compliance, ticket time, calibration, availability', 8, 'A', NOW(), NOW()),
('KS09', 'Beverage Operational Excellence', 'Recipe compliance, serving time, calibration, availability', 9, 'A', NOW(), NOW()),
('KS10', 'Service Operational System Development', 'SOP development & program implementation', 10, 'A', NOW(), NOW()),
('KS11', 'Service / Beverage Quality Consistency', 'SOP compliance & complaint ratio (service/beverage)', 11, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `updated_at` = NOW();

-- ── Data Parameters BARU (D025+) ──────────────────────
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('D025', 'Customer Satisfaction Index (GSI)',     'hybrid', 'outlet',   'percent', 'GSI guest satisfaction score',           NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D026', 'Google Review Rating',                'hybrid', 'outlet',   'decimal', 'Average Google review rating',           NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D027', 'Actual Upselling Sales',              'hybrid', 'outlet',   'decimal', 'Actual upselling sales MTD',             NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D028', 'Target Upselling Sales',              'hybrid', 'outlet',   'decimal', 'Target F&B revenue upselling (Upselling menu)', NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D029', 'Current Period Average Check',        'erp',    'outlet',   'decimal', 'Average check current period',           NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D030', 'Previous Period Average Check',       'erp',    'outlet',   'decimal', 'Average check previous period',          NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D031', 'Employee Induction Completion %',     'manual', 'employee', 'percent', 'Employee induction on-time completion',  NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D032', 'Coaching Visit Person Count',         'manual', 'employee', 'integer', 'Coaching visits executed (persons)',     NULL, 'higher_better', 'daily',   NULL, 1, 'A', NOW(), NOW()),
('D033', 'SOP Development Completion %',        'manual', 'employee', 'percent', 'SOP development project completion',     NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D034', 'Monthly Dev Program Implementation %','manual', 'employee', 'percent', 'Monthly development program execution',  NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D035', 'Beverage Avg Serving Time (minutes)', 'hybrid', 'outlet',   'decimal', 'Avg beverage serving time per order',    NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D036', 'Taste Calibration Completion %',      'manual', 'outlet',   'percent', 'Product taste calibration completion',   NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D037', 'Menu Items Available Count',          'hybrid', 'outlet',   'integer', 'Menu items available',                   NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D038', 'Total Menu Items Count',              'hybrid', 'outlet',   'integer', 'Total active menu items',                NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D040', 'Beverage Complaint Count',            'erp',    'outlet',   'integer', 'Beverage-related complaints',            NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D042', 'Food / Kitchen Complaint Count',      'erp',    'outlet',   'integer', 'Food/kitchen complaints',                NULL, 'lower_better',  'monthly', NULL, 1, 'A', NOW(), NOW()),
('D043', 'New Products Developed Count',        'manual', 'employee', 'integer', 'Approved trial-ready new products',      NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D044', 'Successful Innovations Count',        'manual', 'employee', 'integer', 'Successful product innovations',         NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D045', 'Total Innovations Launched Count',    'manual', 'employee', 'integer', 'Total innovations launched',             NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D046', 'Benchmark Reports Count',             'manual', 'employee', 'integer', 'Competitor benchmark reports',           NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW()),
('D047', 'Operational Support SLA Score %',     'hybrid', 'outlet',   'percent', 'Operational support ticket SLA',         NULL, 'higher_better', 'monthly', NULL, 1, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `source_type` = VALUES(`source_type`),
    `scope_type` = VALUES(`scope_type`),
    `data_type` = VALUES(`data_type`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- ── KPI Parameters BARU (KPI17+) — reuse D* existing bila bisa ──
INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`,
    `is_shared`, `status`, `created_at`, `updated_at`
) VALUES
('KPI17', 'Customer Satisfaction Index',              'hybrid', 'outlet',   'percent', 'GSI customer satisfaction',                    '>= 90%',              'higher_better', 'monthly',   'D025',                          1, 'A', NOW(), NOW()),
('KPI18', 'Google Review Rating',                     'hybrid', 'outlet',   'decimal', 'Google review rating',                       '>= 4.8',              'higher_better', 'monthly',   'D026',                          1, 'A', NOW(), NOW()),
('KPI19', 'Upselling Sales Achievement',              'hybrid', 'outlet',   'percent', 'Actual vs target upselling sales',           '>= 90%',              'higher_better', 'monthly',   'D027 / D028 * 100',             1, 'A', NOW(), NOW()),
('KPI20', 'Average Check Growth',                     'erp',    'outlet',   'percent', 'Average check growth vs previous period',    '> 10%',               'higher_better', 'monthly',   '(D029 - D030) / D030 * 100',    1, 'A', NOW(), NOW()),
('KPI21', 'Employee Induction Completion',            'manual', 'employee', 'percent', 'Employee induction on time',                 '100% on Time',        'higher_better', 'monthly',   'D031',                          1, 'A', NOW(), NOW()),
('KPI22', 'Coaching Visit Execution',                 'manual', 'employee', 'integer', 'Coaching visit persons executed',            '>= 2 Person',         'higher_better', 'daily',     'D032',                          1, 'A', NOW(), NOW()),
('KPI23', 'SOP Development Completion',               'manual', 'employee', 'percent', 'SOP development completion',               '100%',                'higher_better', 'monthly',   'D033',                          1, 'A', NOW(), NOW()),
('KPI24', 'Monthly Development Program Implementation','manual', 'employee', 'percent', 'Monthly dev program implementation',         '100%',                'higher_better', 'monthly',   'D034',                          1, 'A', NOW(), NOW()),
('KPI25', 'Recipe Compliance & Standardization',      'hybrid', 'outlet',   'percent', 'Recipe/SOP compliance score',                '>= 95%',              'higher_better', 'monthly',   'D017',                          1, 'A', NOW(), NOW()),
('KPI26', 'Beverage Serving Time',                    'hybrid', 'outlet',   'decimal', 'Average beverage serving time',              '<= 5 Minutes',        'lower_better',  'monthly',   'D035',                          1, 'A', NOW(), NOW()),
('KPI27', 'Product Taste Calibration',                'manual', 'outlet',   'percent', 'Taste calibration completion',               '100% Completion',     'higher_better', 'monthly',   'D036',                          1, 'A', NOW(), NOW()),
('KPI28', 'Product Availability',                     'hybrid', 'outlet',   'percent', 'Menu product availability',                  '>= 98%',              'higher_better', 'monthly',   'D037 / D038 * 100',             1, 'A', NOW(), NOW()),
('KPI29', 'Beverage Complaint Ratio',                 'erp',    'outlet',   'percent', 'Beverage complaints vs orders',              '<= 0.50%',            'lower_better',  'monthly',   'D040 / D011 * 100',             1, 'A', NOW(), NOW()),
('KPI30', 'Kitchen / Food Complaint Ratio',           'erp',    'outlet',   'percent', 'Food complaints vs orders',                  '<= 0.50%',            'lower_better',  'monthly',   'D042 / D011 * 100',             1, 'A', NOW(), NOW()),
('KPI31', 'Monthly Labor Cost Ratio',                 'erp',    'outlet',   'percent', 'Payroll vs F&B revenue',                     '<= 11%',              'lower_better',  'monthly',   'D010 / D001 * 100',             1, 'A', NOW(), NOW()),
('KPI32', 'New Product Development',                  'manual', 'employee', 'integer', 'New products developed',                     'Min. 3 Products',       'higher_better', 'quarterly', 'D043',                          1, 'A', NOW(), NOW()),
('KPI33', 'Innovation Success Rate',                  'manual', 'employee', 'percent', 'Successful innovations launched',            '>= 85%',              'higher_better', 'monthly',   'D044 / D045 * 100',             1, 'A', NOW(), NOW()),
('KPI34', 'Product Benchmarking Execution',           'manual', 'employee', 'integer', 'Competitor benchmark reports',               'Min. 2 / Month',      'higher_better', 'monthly',   'D046',                          1, 'A', NOW(), NOW()),
('KPI35', 'Operational Support Responsiveness',       'hybrid', 'outlet',   'percent', 'Support ticket SLA compliance',              '>= SLA',              'higher_better', 'monthly',   'D047',                          1, 'A', NOW(), NOW()),
('KPI36', 'Monthly F&B Expense Ratio',                'erp',    'outlet',   'percent', 'F&B expenses vs revenue',                    '<= 0.2%',             'lower_better',  'monthly',   'D007 / D001 * 100',             1, 'A', NOW(), NOW())
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

-- ── ERP mappings parameter data baru (manual skip) ──
INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, v.resolver_key, NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', v.aggregation, 'A', NOW(), NOW()
FROM `kpi_parameters` p
JOIN (
    -- D013 tetap pakai mapping sample (guest_comment_gsi count) untuk KPI10 — jangan diubah
    SELECT 'D040' AS code, 'ticket_complaint_count' AS resolver_key, 'count' AS aggregation UNION ALL
    SELECT 'D042', 'ticket_complaint_count', 'count' UNION ALL
    SELECT 'D027', 'upselling_actual_fb_revenue', 'sum' UNION ALL
    SELECT 'D028', 'upselling_target_fb_revenue', 'sum' UNION ALL
    SELECT 'D029', 'outlet_avg_check_data_month', 'avg' UNION ALL
    SELECT 'D030', 'outlet_avg_check_prev_month', 'avg'
) v ON v.code = p.code
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `aggregation` = VALUES(`aggregation`), `updated_at` = NOW();

COMMIT;

-- REUSE (tidak di-insert ulang):
-- D001-D024, KPI01-KPI16
-- D011 = Total Orders (on-time, beverage/food complaint ratio)
-- D012 = On-Time Orders → KPI08
-- D014,D013 → KPI10 | D015 → KPI09 | D016 → KPI14 | D017 → KPI07/KPI25
-- D018 → KPI11 | D019 → KPI12 | D020 → KPI13 (JMA/JMT/JNG/JND/INC)
-- D021,D022 → KPI15 | D023,D024 → KPI16
-- D003,D001 → KPI02 | D005 → KPI03 | D006 → KPI04 | D007 → KPI05/KPI36
