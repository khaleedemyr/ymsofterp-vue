-- =====================================================
-- KPI — ERP data scope (1 outlet / beberapa / semua)
-- Jalankan setelah create_kpi_evaluation_tables.sql
-- =====================================================

START TRANSACTION;

ALTER TABLE `kpi_templates`
    ADD COLUMN `erp_data_scope` ENUM('employee_outlet', 'single_outlet', 'multiple_outlets', 'all_outlets')
        NOT NULL DEFAULT 'employee_outlet'
        COMMENT 'Default scope ambil data ERP saat evaluasi'
        AFTER `scoring_rules`,
    ADD COLUMN `erp_scope_outlet_ids` JSON NULL
        COMMENT 'Default id_outlet[] untuk single/multiple'
        AFTER `erp_data_scope`;

ALTER TABLE `kpi_evaluations`
    ADD COLUMN `erp_data_scope` ENUM('employee_outlet', 'single_outlet', 'multiple_outlets', 'all_outlets')
        NOT NULL DEFAULT 'employee_outlet'
        COMMENT 'Scope ambil data ERP untuk evaluasi ini'
        AFTER `division_name`,
    ADD COLUMN `erp_scope_outlet_ids` JSON NULL
        COMMENT 'id_outlet[] untuk single/multiple'
        AFTER `erp_data_scope`;

-- Template JUSTUS sample: default 1 outlet (pilih saat buat evaluasi)
UPDATE `kpi_templates`
SET `erp_data_scope` = 'single_outlet'
WHERE `code` = 'KPI_OUTLET_MANAGER_v1';

COMMIT;
