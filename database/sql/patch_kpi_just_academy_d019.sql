-- KPI Just Academy: D018 (training plan completion) + D019 (competency assessment)
-- Jalankan sekali setelah deploy resolver just_academy_competency_assessment_score.

-- Aktifkan D019 untuk KPI12 (GM Operation)
UPDATE `kpi_parameters`
SET
    `name` = 'Just Academy Competency Assessment Score',
    `description` = 'Avg quiz score — training method Competency Assessment (plan by user + bawahan)',
    `source_type` = 'hybrid',
    `scope_type` = 'employee',
    `data_type` = 'percent',
    `status` = 'A',
    `updated_at` = NOW()
WHERE `code` = 'D019';

INSERT INTO `kpi_parameters` (
    `code`, `name`, `source_type`, `scope_type`, `data_type`, `description`,
    `target_value`, `target_direction`, `frequency`, `formula`, `is_shared`, `status`, `created_at`, `updated_at`
)
SELECT
    'D019',
    'Just Academy Competency Assessment Score',
    'hybrid',
    'employee',
    'percent',
    'Avg quiz score — training method Competency Assessment (plan by user + bawahan)',
    '>= 90%',
    'higher_better',
    'monthly',
    NULL,
    1,
    'A',
    NOW(),
    NOW()
WHERE NOT EXISTS (SELECT 1 FROM `kpi_parameters` WHERE `code` = 'D019');

UPDATE `kpi_parameters`
SET
    `source_type` = 'hybrid',
    `formula` = 'D019',
    `description` = 'Competency Assessment dari Just Academy (method Competency Assessment)',
    `updated_at` = NOW()
WHERE `code` = 'KPI12';

UPDATE `kpi_parameters`
SET
    `name` = 'Just Academy Training Plan Module Completion %',
    `description` = 'Rata-rata % modul wajib selesai — training plan dibuat/ditrainer user + bawahan',
    `scope_type` = 'employee',
    `updated_at` = NOW()
WHERE `code` = 'D018';

INSERT INTO `kpi_parameter_erp_mappings` (`kpi_parameter_id`, `resolver_key`, `static_filters`, `dynamic_filter_bindings`, `aggregation`, `status`, `created_at`, `updated_at`)
SELECT p.id, 'just_academy_competency_assessment_score', NULL, '{"user_id":"context.user_id","month":"context.period_month"}', 'avg', 'A', NOW(), NOW()
FROM `kpi_parameters` p
WHERE p.`code` = 'D019'
  AND NOT EXISTS (
      SELECT 1 FROM `kpi_parameter_erp_mappings` m
      WHERE m.`kpi_parameter_id` = p.id AND m.`resolver_key` = 'just_academy_competency_assessment_score'
  );
