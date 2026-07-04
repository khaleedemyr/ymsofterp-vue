-- =====================================================
-- KPI — RESET MASTER DATA (DEV / UJI SAJA)
-- =====================================================
-- Menghapus SEMUA data KPI: evaluasi + template + parameter + key strategy.
-- JANGAN jalankan di production yang sudah punya evaluasi submitted resmi.
--
-- Setelah script ini, jalankan seed berurutan:
--   1. seed_kpi_template_justus_sample.sql   (KS01-KS05 + D001-D024 base)
--   2. seed_kpi_gm_operation.sql             (template GM Operation + resolver mappings)
--   3. alter_kpi_erp_data_scope.sql          (skip jika kolom sudah ada)
-- =====================================================

START TRANSACTION;

-- ── 1. Evaluasi (child: items + parameter_values CASCADE) ──
DELETE FROM `kpi_evaluations`;

-- ── 2. Template (child CASCADE: positions, strategies, items, item_parameters) ──
DELETE FROM `kpi_templates`;

-- ── 3. Parameter catalog (+ erp_mappings CASCADE) ──
DELETE FROM `kpi_parameters`;

-- ── 4. Key Strategy master ──
DELETE FROM `kpi_key_strategies`;

-- ── Reset auto-increment (opsional, biar ID mulai dari 1 lagi) ──
ALTER TABLE `kpi_evaluations` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_evaluation_parameter_values` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_evaluation_items` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_templates` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_template_positions` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_template_strategies` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_template_items` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_template_item_parameters` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_parameters` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_parameter_erp_mappings` AUTO_INCREMENT = 1;
ALTER TABLE `kpi_key_strategies` AUTO_INCREMENT = 1;

COMMIT;

-- Verifikasi (semua harus 0):
-- SELECT 'evaluations' AS tbl, COUNT(*) AS cnt FROM kpi_evaluations
-- UNION ALL SELECT 'templates', COUNT(*) FROM kpi_templates
-- UNION ALL SELECT 'parameters', COUNT(*) FROM kpi_parameters
-- UNION ALL SELECT 'key_strategies', COUNT(*) FROM kpi_key_strategies;
