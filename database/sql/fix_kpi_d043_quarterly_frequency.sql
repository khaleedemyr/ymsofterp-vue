-- Selaraskan frequency D043 dengan KPI32 (New Product Development) yang quarterly.
-- Setelah dijalankan, Refresh ERP / Hitung Ulang Skor akan agregasi 3 bulan untuk parameter ini.

UPDATE `kpi_parameters`
SET `frequency` = 'quarterly',
    `updated_at` = NOW()
WHERE `code` = 'D043'
  AND `frequency` <> 'quarterly';

UPDATE `kpi_evaluation_items` ei
INNER JOIN `kpi_parameters` p ON p.id = ei.kpi_parameter_id
SET ei.frequency = 'quarterly',
    ei.updated_at = NOW()
WHERE p.code = 'D043'
  AND ei.frequency <> 'quarterly';
