-- Perbaiki target KPI Coaching Visit: "22 Person" → ">= 2 Person" (typo di beberapa template)
-- Pastikan D032 tetap integer (jumlah orang), bukan persen.

START TRANSACTION;

UPDATE `kpi_template_items` ti
INNER JOIN `kpi_template_item_parameters` tip ON tip.kpi_template_item_id = ti.id
INNER JOIN `kpi_parameters` p ON p.id = tip.kpi_parameter_id AND p.code = 'KPI22'
SET ti.`target_value` = '>= 2 Person',
    ti.`updated_at` = NOW()
WHERE ti.`target_value` IN ('22 Person', '2 Person');

-- Fallback bila link parameter belum ada: cocokkan nama / formula
UPDATE `kpi_template_items`
SET `target_value` = '>= 2 Person',
    `updated_at` = NOW()
WHERE `target_value` IN ('22 Person', '2 Person')
  AND (
      `name` LIKE '%Coaching Visit%'
      OR TRIM(`formula`) = 'D032'
  );

UPDATE `kpi_evaluation_items` ei
INNER JOIN `kpi_evaluations` e ON e.id = ei.kpi_evaluation_id
SET ei.`target_value` = '>= 2 Person',
    ei.`updated_at` = NOW()
WHERE ei.`item_name` LIKE '%Coaching Visit%'
  AND ei.`target_value` IN ('22 Person', '2 Person')
  AND e.`eval_status` = 'draft';

UPDATE `kpi_parameters`
SET
    `data_type` = 'integer',
    `description` = 'Jumlah karyawan unik yang di-coaching oleh user evaluasi (bulan data KPI)',
    `updated_at` = NOW()
WHERE `code` = 'D032'
  AND `data_type` <> 'integer';

COMMIT;
