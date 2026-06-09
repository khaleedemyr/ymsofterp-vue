-- =====================================================
-- Fix: template KPI hanya bisa dipakai bulan publish
-- Penyebab: effective_from ter-set ke bulan publish (mis. 2026-06-01)
-- Solusi: kosongkan effective_from/to → berlaku semua periode
-- Jalankan sekali di production
-- =====================================================

START TRANSACTION;

UPDATE `kpi_template_positions` tp
INNER JOIN `kpi_templates` t ON t.id = tp.kpi_template_id
SET tp.`effective_from` = NULL,
    tp.`effective_to` = NULL,
    tp.`updated_at` = NOW()
WHERE tp.`status` = 'A'
  AND t.`status` = 'A'
  AND t.`template_status` = 'active';

COMMIT;
