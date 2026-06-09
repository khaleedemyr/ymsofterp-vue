-- =====================================================
-- KPI Master — sample seed data (opsional)
-- Jalankan setelah create_kpi_master_tables.sql
-- =====================================================

START TRANSACTION;

INSERT INTO `kpi_key_strategies` (`code`, `name`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('KS01', 'F&B Financial Performance & Productivity', 'Revenue, COGS, waste, labor cost', 1, 'A', NOW(), NOW()),
('KS02', 'Service Operational Excellence', 'SOP compliance, on-time orders', 2, 'A', NOW(), NOW()),
('KS03', 'Customer Experience', 'Complaint resolution & ratio', 3, 'A', NOW(), NOW()),
('KS04', 'Team Development', 'Training, competency, INC program', 4, 'A', NOW(), NOW()),
('KS05', 'Compliance & Team Support', 'QA compliance, visits, improvement actions', 5, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

INSERT INTO `kpi_parameters` (`code`, `name`, `source_type`, `scope_type`, `data_type`, `description`, `is_shared`, `status`, `created_at`, `updated_at`) VALUES
('P001', 'MTD Actual F&B Revenue', 'erp', 'outlet', 'decimal', 'Actual F&B revenue MTD', 1, 'A', NOW(), NOW()),
('P002', 'MTD Budget F&B Revenue', 'erp', 'outlet', 'decimal', 'Budget F&B revenue MTD', 1, 'A', NOW(), NOW()),
('P003', 'MTD COGS Amount', 'erp', 'outlet', 'decimal', 'Cost of goods sold MTD', 1, 'A', NOW(), NOW()),
('P005', 'MTD Waste Amount', 'erp', 'outlet', 'decimal', 'Waste amount MTD', 1, 'A', NOW(), NOW()),
('P010', 'MTD Payroll & Related', 'erp', 'outlet', 'decimal', 'Payroll & related costs MTD', 1, 'A', NOW(), NOW()),
('P014', 'Number of Complaints', 'erp', 'outlet', 'integer', 'Total complaints in period', 1, 'A', NOW(), NOW()),
('P018', 'Training Completion %', 'erp', 'employee', 'percent', 'Training completion percentage', 1, 'A', NOW(), NOW()),
('P020', 'INC Program Completion', 'manual', 'employee', 'percent', 'INC program completion - manual input', 0, 'A', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, 'daily_revenue_forecast', NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', 'sum', 'A', NOW(), NOW()
FROM `kpi_parameters` p WHERE p.code = 'P001'
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `updated_at` = NOW();

INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, 'daily_revenue_forecast_budget', NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', 'sum', 'A', NOW(), NOW()
FROM `kpi_parameters` p WHERE p.code = 'P002'
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `updated_at` = NOW();

INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, 'outlet_analyzer_payroll', NULL, '{"outlet_id":"context.outlet_id","month":"context.period_month","year":"context.period_year"}', 'sum', 'A', NOW(), NOW()
FROM `kpi_parameters` p WHERE p.code = 'P010'
ON DUPLICATE KEY UPDATE `resolver_key` = VALUES(`resolver_key`), `updated_at` = NOW();

COMMIT;
