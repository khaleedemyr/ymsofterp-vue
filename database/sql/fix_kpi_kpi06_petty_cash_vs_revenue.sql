-- Fix KPI06 Petty Cash Usage Control:
-- Formula: usage vs MTD actual F&B revenue (D001), bukan vs lock budget (D009)
-- Jalankan sekali di MySQL (staging/production)

START TRANSACTION;

UPDATE `kpi_parameters`
SET
    `formula` = 'D008 / D001 * 100',
    `description` = 'Petty cash usage vs MTD actual F&B revenue',
    `target_value` = '<= 1%',
    `target_direction` = 'lower_better',
    `updated_at` = NOW()
WHERE `code` = 'KPI06';

-- Snapshot formula di template items yang memakai KPI06
UPDATE `kpi_template_items` ti
JOIN `kpi_template_item_parameters` tip ON tip.kpi_template_item_id = ti.id
JOIN `kpi_parameters` p ON p.id = tip.kpi_parameter_id AND p.code = 'KPI06'
SET
    ti.`formula` = 'D008 / D001 * 100',
    ti.`target_value` = COALESCE(NULLIF(TRIM(ti.`target_value`), ''), '<= 1%'),
    ti.`updated_at` = NOW();

-- Snapshot formula di evaluasi yang masih memakai rumus lama
UPDATE `kpi_evaluation_items`
SET
    `formula` = 'D008 / D001 * 100',
    `updated_at` = NOW()
WHERE `formula` = 'D008 / D009 * 100'
   OR `formula` LIKE '%D008%/%D009%*%100%';

COMMIT;

-- Setelah patch: buka ulang draft evaluasi → Refresh ERP / Recalculate
-- agar achievement KPI06 dihitung ulang dengan D001.
