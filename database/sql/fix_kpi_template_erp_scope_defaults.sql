-- Perbaiki default scope template KPI: jangan auto "semua outlet"
-- Evaluasi harus pakai outlet karyawan / outlet yang dipilih saat buat evaluasi.
-- Resolver sudah benar — masalahnya template & evaluasi tersimpan dengan erp_data_scope = all_outlets.

START TRANSACTION;

UPDATE `kpi_templates`
SET
    `erp_data_scope` = 'employee_outlet',
    `updated_at` = NOW()
WHERE `erp_data_scope` = 'all_outlets';

-- Draft evaluasi yang sudah terlanjur all_outlets: kembalikan ke outlet karyawan
-- (skip jika memang sengaja all_outlets untuk role corporate — ubah manual di Edit)
UPDATE `kpi_evaluations`
SET
    `erp_data_scope` = 'employee_outlet',
    `erp_scope_outlet_ids` = JSON_ARRAY(`id_outlet`),
    `updated_at` = NOW()
WHERE `erp_data_scope` = 'all_outlets'
  AND `eval_status` = 'draft'
  AND `id_outlet` > 0;

COMMIT;
