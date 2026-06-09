-- =====================================================
-- KPI Parameter — tambah kolom KPI config
-- Jalankan setelah create_kpi_master_tables.sql
-- =====================================================

ALTER TABLE `kpi_parameters`
    ADD COLUMN `target_value` VARCHAR(100) NULL COMMENT 'Target KPI, mis. 100%, <=24 hours' AFTER `description`,
    ADD COLUMN `target_direction` ENUM('higher_better', 'lower_better') NOT NULL DEFAULT 'higher_better' AFTER `target_value`,
    ADD COLUMN `frequency` VARCHAR(50) NOT NULL DEFAULT 'monthly' AFTER `target_direction`,
    ADD COLUMN `formula` TEXT NULL COMMENT 'Rumus KPI, mis. P001 / P002 * 100' AFTER `frequency`;
