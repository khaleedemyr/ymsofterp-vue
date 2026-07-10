-- Perbaiki target KPI32 (New Product Development) per template jabatan.
-- Master parameter KPI32 default = Min. 3 Products; Executive Chef = Min. 6 Products.
-- Jalankan sekali, lalu buka ulang evaluasi KPI (draft) atau Refresh ERP.

START TRANSACTION;

-- Template yang targetnya Min. 6 Products (Executive Chef / Culinary)
UPDATE `kpi_template_items` ti
INNER JOIN `kpi_template_item_parameters` tip ON tip.kpi_template_item_id = ti.id
INNER JOIN `kpi_parameters` p ON p.id = tip.kpi_parameter_id AND p.code = 'KPI32'
INNER JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id
INNER JOIN `kpi_templates` t ON t.id = ts.kpi_template_id
SET ti.target_value = 'Min. 6 Products',
    ti.updated_at = NOW()
WHERE t.code IN (
    'KPI_CORP_EXECUTIVE_CHEF_CULINARY_v1',
    'KPI_CORP_EXC_CHEF_v1'
);

-- Template yang targetnya Min. 3 Products (Beverage / R&D Beverage)
UPDATE `kpi_template_items` ti
INNER JOIN `kpi_template_item_parameters` tip ON tip.kpi_template_item_id = ti.id
INNER JOIN `kpi_parameters` p ON p.id = tip.kpi_parameter_id AND p.code = 'KPI32'
INNER JOIN `kpi_template_strategies` ts ON ts.id = ti.kpi_template_strategy_id
INNER JOIN `kpi_templates` t ON t.id = ts.kpi_template_id
SET ti.target_value = 'Min. 3 Products',
    ti.updated_at = NOW()
WHERE t.code IN (
    'KPI_RD_BEVERAGE_MANAGER_v1',
    'KPI_CORP_RD_BEVERAGE_MANAGER_v1',
    'KPI_REG_BEVERAGE_MANAGER_v1',
    'KPI_BEVERAGE_MANAGER_v1',
    'KPI_ASST_BEVERAGE_MANAGER_BANDUNG_v1',
    'KPI_REG_ASST_BEVERAGE_MGR_v1'
);

-- Samakan evaluasi draft/submitted yang sudah ada dengan target template terbaru
UPDATE `kpi_evaluation_items` ei
INNER JOIN `kpi_template_items` ti ON ti.id = ei.kpi_template_item_id
INNER JOIN `kpi_template_item_parameters` tip ON tip.kpi_template_item_id = ti.id
INNER JOIN `kpi_parameters` p ON p.id = tip.kpi_parameter_id AND p.code = 'KPI32'
SET ei.target_value = ti.target_value,
    ei.updated_at = NOW()
WHERE ei.target_value IS NULL
   OR ei.target_value <> ti.target_value;

COMMIT;

-- Verifikasi:
-- SELECT t.code, t.name, ti.target_value, p.code
-- FROM kpi_templates t
-- JOIN kpi_template_strategies ts ON ts.kpi_template_id = t.id
-- JOIN kpi_template_items ti ON ti.kpi_template_strategy_id = ts.id
-- JOIN kpi_template_item_parameters tip ON tip.kpi_template_item_id = ti.id
-- JOIN kpi_parameters p ON p.id = tip.kpi_parameter_id
-- WHERE p.code = 'KPI32'
-- ORDER BY t.code;
