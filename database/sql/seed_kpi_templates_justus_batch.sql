-- =====================================================
-- KPI Templates — JUSTUS GROUP batch (10 templates)
-- Jalankan SETELAH:
--   1. seed_kpi_template_justus_sample.sql
--   2. seed_kpi_parameters_extended.sql
-- Re-run aman: template di-reset per kode (strategies di-delete & di-seed ulang)
-- Parameter TIDAK diduplikasi — semua referensi ke kode D*/KPI* yang sudah ada
-- =====================================================

START TRANSACTION;

-- Samakan collation koneksi dengan tabel KPI (utf8mb4_unicode_ci).
-- Tanpa ini, perbandingan @tpl_code / literal UNION vs kolom code memicu error 1267.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

-- ── Helper: seed satu template dari definisi KPI ──────
-- (dipanggil berulang via copy pattern per template)

-- =====================================================================
-- 1. REG. SERVICE MANAGER JSH
-- =====================================================================
SET @tpl_code := 'KPI_REG_SERVICE_MANAGER_JSH_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. SERVICE MANAGER JSH', 'Regional Service Manager JSH — JUSTUS GROUP', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
UPDATE `kpi_templates` SET `name` = 'REG. SERVICE MANAGER JSH', `updated_at` = NOW() WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci);
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);

INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Service Manager%JSH%' OR j.nama_jabatan LIKE '%JSH%Service%Manager%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;

INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (
    SELECT 'KS02' AS code, 20.00 AS weight, 0 AS sort_order UNION ALL
    SELECT 'KS03', 20.00, 1 UNION ALL SELECT 'KS06', 20.00, 2 UNION ALL
    SELECT 'KS04', 20.00, 3 UNION ALL SELECT 'KS05', 20.00, 4
) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);

SET @s02 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS02' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s06 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS06' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);

INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s02 AS strategy_id, 'KPI07' AS kpi_code, 10.00 AS weight, 0 AS sort_order, 'Deviation <= 2% & 100% Documented' AS tgt UNION ALL
    SELECT @s02, 'KPI08', 10.00, 1, '>= 95%' UNION ALL
    SELECT @s03, 'KPI09',  5.00, 0, NULL UNION ALL
    SELECT @s03, 'KPI10',  5.00, 1, '< 0.50%' UNION ALL
    SELECT @s03, 'KPI17',  5.00, 2, NULL UNION ALL
    SELECT @s03, 'KPI18',  5.00, 3, NULL UNION ALL
    SELECT @s06, 'KPI19', 10.00, 0, NULL UNION ALL
    SELECT @s06, 'KPI20', 10.00, 1, NULL UNION ALL
    SELECT @s04, 'KPI12',  5.00, 0, '100%' UNION ALL
    SELECT @s04, 'KPI13',  5.00, 1, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21',  5.00, 2, NULL UNION ALL
    SELECT @s04, 'KPI22',  5.00, 3, NULL UNION ALL
    SELECT @s05, 'KPI14',  6.67, 0, '>= 80%' UNION ALL
    SELECT @s05, 'KPI15',  6.67, 1, '>= 85%' UNION ALL
    SELECT @s05, 'KPI16',  6.66, 2, '>= 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN (
    SELECT @s02 AS strategy_id, 'KPI07' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT @s02, 'KPI08', 1 UNION ALL SELECT @s03, 'KPI09', 0 UNION ALL SELECT @s03, 'KPI10', 1 UNION ALL
    SELECT @s03, 'KPI17', 2 UNION ALL SELECT @s03, 'KPI18', 3 UNION ALL SELECT @s06, 'KPI19', 0 UNION ALL
    SELECT @s06, 'KPI20', 1 UNION ALL SELECT @s04, 'KPI12', 0 UNION ALL SELECT @s04, 'KPI13', 1 UNION ALL
    SELECT @s04, 'KPI21', 2 UNION ALL SELECT @s04, 'KPI22', 3 UNION ALL SELECT @s05, 'KPI14', 0 UNION ALL
    SELECT @s05, 'KPI15', 1 UNION ALL SELECT @s05, 'KPI16', 2
) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 2. REG. SERVICE MANAGER FC
-- =====================================================================
SET @tpl_code := 'KPI_REG_SERVICE_MANAGER_FC_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. SERVICE MANAGER FC', 'Regional Service Manager FC — JUSTUS GROUP', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);

INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j
WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Service Manager%FC%' OR j.nama_jabatan LIKE '%FC%Service%Manager%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 20.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS02' AS code, 0 AS sort_order UNION ALL SELECT 'KS03',1 UNION ALL SELECT 'KS06',2 UNION ALL SELECT 'KS04',3 UNION ALL SELECT 'KS05',4) v
JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);

SET @s02 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS02' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s06 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS06' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);

INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s02 AS strategy_id, 'KPI07' AS kpi_code, 10.00 AS weight, 0 AS sort_order, 'Deviation <= 2% & 100% Documented' AS tgt UNION ALL
    SELECT @s02, 'KPI08', 10.00, 1, '>= 90%' UNION ALL
    SELECT @s03, 'KPI09',  6.67, 0, NULL UNION ALL
    SELECT @s03, 'KPI10',  6.67, 1, '< 0.50%' UNION ALL
    SELECT @s03, 'KPI18',  6.66, 2, NULL UNION ALL
    SELECT @s06, 'KPI19', 10.00, 0, NULL UNION ALL
    SELECT @s06, 'KPI20', 10.00, 1, NULL UNION ALL
    SELECT @s04, 'KPI12',  5.00, 0, '>= 90%' UNION ALL
    SELECT @s04, 'KPI13',  5.00, 1, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21',  5.00, 2, NULL UNION ALL
    SELECT @s04, 'KPI22',  5.00, 3, NULL UNION ALL
    SELECT @s05, 'KPI14',  6.67, 0, '>= 85%' UNION ALL
    SELECT @s05, 'KPI15',  6.67, 1, '>= 85%' UNION ALL
    SELECT @s05, 'KPI16',  6.66, 2, '>= 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN (
    SELECT @s02 AS strategy_id, 'KPI07' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT @s02, 'KPI08', 1 UNION ALL SELECT @s03, 'KPI09', 0 UNION ALL SELECT @s03, 'KPI10', 1 UNION ALL
    SELECT @s03, 'KPI18', 2 UNION ALL SELECT @s06, 'KPI19', 0 UNION ALL SELECT @s06, 'KPI20', 1 UNION ALL
    SELECT @s04, 'KPI12', 0 UNION ALL SELECT @s04, 'KPI13', 1 UNION ALL SELECT @s04, 'KPI21', 2 UNION ALL
    SELECT @s04, 'KPI22', 3 UNION ALL SELECT @s05, 'KPI14', 0 UNION ALL SELECT @s05, 'KPI15', 1 UNION ALL
    SELECT @s05, 'KPI16', 2
) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 3. CORP. F&B SERVICE DEV MANAGER
-- =====================================================================
SET @tpl_code := 'KPI_CORP_FB_SERVICE_DEV_MGR_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'CORP. F&B SERVICE DEV MANAGER', 'Corporate F&B Service Development Manager', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);

INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Service%Dev%Manager%' OR j.nama_jabatan LIKE '%F%B%Service%Dev%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);

DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 25.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS10' AS code, 0 AS sort_order UNION ALL SELECT 'KS11',1 UNION ALL SELECT 'KS04',2 UNION ALL SELECT 'KS05',3) v
JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);

SET @s10 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS10' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s11 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS11' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);

INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s10 AS strategy_id, 'KPI23' AS kpi_code, 12.50 AS weight, 0 AS sort_order, NULL AS tgt UNION ALL
    SELECT @s10, 'KPI24', 12.50, 1, NULL UNION ALL
    SELECT @s11, 'KPI07', 12.50, 0, 'Deviation <2% & 100% Documented' UNION ALL
    SELECT @s11, 'KPI10', 12.50, 1, '<= 0.50%' UNION ALL
    SELECT @s04, 'KPI11',  5.00, 0, NULL UNION ALL
    SELECT @s04, 'KPI12',  5.00, 1, '>= 90%' UNION ALL
    SELECT @s04, 'KPI13',  5.00, 2, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21',  5.00, 3, NULL UNION ALL
    SELECT @s04, 'KPI22',  5.00, 4, NULL UNION ALL
    SELECT @s05, 'KPI14',  6.25, 0, '>= 85%' UNION ALL
    SELECT @s05, 'KPI15',  6.25, 1, '>= 85%' UNION ALL
    SELECT @s05, 'KPI35',  6.25, 2, NULL UNION ALL
    SELECT @s05, 'KPI16',  6.25, 3, '>= 85%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW()
FROM `kpi_template_items` ti
JOIN (
    SELECT @s10 AS strategy_id, 'KPI23' AS kpi_code, 0 AS sort_order UNION ALL
    SELECT @s10, 'KPI24', 1 UNION ALL SELECT @s11, 'KPI07', 0 UNION ALL SELECT @s11, 'KPI10', 1 UNION ALL
    SELECT @s04, 'KPI11', 0 UNION ALL SELECT @s04, 'KPI12', 1 UNION ALL SELECT @s04, 'KPI13', 2 UNION ALL
    SELECT @s04, 'KPI21', 3 UNION ALL SELECT @s04, 'KPI22', 4 UNION ALL SELECT @s05, 'KPI14', 0 UNION ALL
    SELECT @s05, 'KPI15', 1 UNION ALL SELECT @s05, 'KPI35', 2 UNION ALL SELECT @s05, 'KPI16', 3
) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 4. REG. BEVERAGE MANAGER
-- =====================================================================
SET @tpl_code := 'KPI_REG_BEVERAGE_MANAGER_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. BEVERAGE MANAGER', 'Regional Beverage Manager — JUSTUS GROUP', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND j.nama_jabatan LIKE '%Beverage Manager%' AND j.nama_jabatan NOT LIKE '%Asst%' AND j.nama_jabatan NOT LIKE '%Assistant%'
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 25.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS09' AS code, 0 AS sort_order UNION ALL SELECT 'KS03',1 UNION ALL SELECT 'KS04',2 UNION ALL SELECT 'KS05',3) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s09 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS09' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s09 AS strategy_id, 'KPI25' AS kpi_code, 6.25 AS weight, 0 AS sort_order, '>= 95%' AS tgt UNION ALL
    SELECT @s09, 'KPI26', 6.25, 1, '<= 5 Minutes' UNION ALL SELECT @s09, 'KPI27', 6.25, 2, NULL UNION ALL SELECT @s09, 'KPI28', 6.25, 3, NULL UNION ALL
    SELECT @s03, 'KPI09', 12.50, 0, NULL UNION ALL SELECT @s03, 'KPI29', 12.50, 1, '<= 0.04%' UNION ALL
    SELECT @s04, 'KPI12', 5.00, 0, '>= 90%' UNION ALL SELECT @s04, 'KPI13', 5.00, 1, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 5.00, 2, NULL UNION ALL SELECT @s04, 'KPI22', 5.00, 3, NULL UNION ALL
    SELECT @s05, 'KPI14', 6.67, 0, '>= 80%' UNION ALL SELECT @s05, 'KPI15', 6.67, 1, '>= 85%' UNION ALL SELECT @s05, 'KPI16', 6.66, 2, '>= 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s09 AS strategy_id, 'KPI25' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s09,'KPI26',1 UNION ALL SELECT @s09,'KPI27',2 UNION ALL SELECT @s09,'KPI28',3 UNION ALL
    SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI29',1 UNION ALL SELECT @s04,'KPI12',0 UNION ALL SELECT @s04,'KPI13',1 UNION ALL SELECT @s04,'KPI21',2 UNION ALL SELECT @s04,'KPI22',3 UNION ALL
    SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI16',2) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 5. REG. ASST. BEVERAGE MANAGER
-- =====================================================================
SET @tpl_code := 'KPI_REG_ASST_BEVERAGE_MGR_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. ASST. BEVERAGE MANAGER', 'Regional Assistant Beverage Manager', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Asst%Beverage%' OR j.nama_jabatan LIKE '%Assistant%Beverage%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 25.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS09' AS code, 0 AS sort_order UNION ALL SELECT 'KS03',1 UNION ALL SELECT 'KS04',2 UNION ALL SELECT 'KS05',3) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s09 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS09' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s09 AS strategy_id, 'KPI25' AS kpi_code, 6.25 AS weight, 0 AS sort_order, '>= 95%' AS tgt UNION ALL
    SELECT @s09, 'KPI26', 6.25, 1, '<= 15 Minutes' UNION ALL SELECT @s09, 'KPI27', 6.25, 2, NULL UNION ALL SELECT @s09, 'KPI28', 6.25, 3, NULL UNION ALL
    SELECT @s03, 'KPI09', 12.50, 0, NULL UNION ALL SELECT @s03, 'KPI29', 12.50, 1, '<= 0.50%' UNION ALL
    SELECT @s04, 'KPI12', 5.00, 0, '>= 80%' UNION ALL SELECT @s04, 'KPI13', 5.00, 1, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 5.00, 2, NULL UNION ALL SELECT @s04, 'KPI22', 5.00, 3, NULL UNION ALL
    SELECT @s05, 'KPI14', 6.67, 0, '>= 85%' UNION ALL SELECT @s05, 'KPI15', 6.67, 1, '>= 85%' UNION ALL SELECT @s05, 'KPI16', 6.66, 2, '100%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s09 AS strategy_id, 'KPI25' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s09,'KPI26',1 UNION ALL SELECT @s09,'KPI27',2 UNION ALL SELECT @s09,'KPI28',3 UNION ALL
    SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI29',1 UNION ALL SELECT @s04,'KPI12',0 UNION ALL SELECT @s04,'KPI13',1 UNION ALL SELECT @s04,'KPI21',2 UNION ALL SELECT @s04,'KPI22',3 UNION ALL
    SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI16',2) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order
JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 6. CORP. R&D BEVERAGE MANAGER
-- =====================================================================
SET @tpl_code := 'KPI_CORP_RD_BEVERAGE_MANAGER_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'CORP. R&D BEVERAGE MANAGER', 'Corporate R&D Beverage Manager', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%R&D%Beverage%' OR j.nama_jabatan LIKE '%R%26%D%Beverage%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 25.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS07' AS code, 0 AS sort_order UNION ALL SELECT 'KS11',1 UNION ALL SELECT 'KS04',2 UNION ALL SELECT 'KS05',3) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s07 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS07' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s11 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS11' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s07 AS strategy_id, 'KPI32' AS kpi_code, 8.33 AS weight, 0 AS sort_order, 'Min. 3 Products' AS tgt UNION ALL
    SELECT @s07, 'KPI33', 8.33, 1, NULL UNION ALL SELECT @s07, 'KPI34', 8.34, 2, NULL UNION ALL
    SELECT @s11, 'KPI25', 12.50, 0, 'Deviation <2% & 100% Documented' UNION ALL SELECT @s11, 'KPI29', 12.50, 1, '< 0.50%' UNION ALL
    SELECT @s04, 'KPI11', 5.00, 0, NULL UNION ALL SELECT @s04, 'KPI12', 5.00, 1, '>= 90%' UNION ALL SELECT @s04, 'KPI13', 5.00, 2, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 5.00, 3, NULL UNION ALL SELECT @s04, 'KPI22', 5.00, 4, NULL UNION ALL
    SELECT @s05, 'KPI14', 6.25, 0, '>= 85%' UNION ALL SELECT @s05, 'KPI15', 6.25, 1, '>= 85%' UNION ALL
    SELECT @s05, 'KPI35', 6.25, 2, NULL UNION ALL SELECT @s05, 'KPI16', 6.25, 3, '> 90%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s07 AS strategy_id, 'KPI32' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s07,'KPI33',1 UNION ALL SELECT @s07,'KPI34',2 UNION ALL
    SELECT @s11,'KPI25',0 UNION ALL SELECT @s11,'KPI29',1 UNION ALL SELECT @s04,'KPI11',0 UNION ALL SELECT @s04,'KPI12',1 UNION ALL SELECT @s04,'KPI13',2 UNION ALL
    SELECT @s04,'KPI21',3 UNION ALL SELECT @s04,'KPI22',4 UNION ALL SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI35',2 UNION ALL SELECT @s05,'KPI16',3) v
    ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 7. CORP. EXECUTIVE CHEF
-- =====================================================================
SET @tpl_code := 'KPI_CORP_EXC_CHEF_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'CORP. EXECUTIVE CHEF', 'Corporate Executive Chef — JUSTUS GROUP', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Executive Chef%' OR j.nama_jabatan LIKE '%Exc%Chef%') AND j.nama_jabatan NOT LIKE '%Regional%' AND j.nama_jabatan NOT LIKE '%Sous%'
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, v.weight, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS01' AS code, 16.67 AS weight, 0 AS sort_order UNION ALL SELECT 'KS07',16.67,1 UNION ALL SELECT 'KS08',16.67,2 UNION ALL SELECT 'KS03',16.67,3 UNION ALL SELECT 'KS04',16.66,4 UNION ALL SELECT 'KS05',16.66,5) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS01' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s07 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS07' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s08 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS08' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 3.33 AS weight, 0 AS sort_order, '<= 43%' AS tgt UNION ALL
    SELECT @s01, 'KPI03', 3.33, 1, '<= 1%' UNION ALL SELECT @s01, 'KPI04', 3.33, 2, '<= 0.5%' UNION ALL SELECT @s01, 'KPI36', 3.34, 3, '<= 0.1%' UNION ALL SELECT @s01, 'KPI31', 3.34, 4, '<= 11%' UNION ALL
    SELECT @s07, 'KPI32', 5.56, 0, 'Min. 6 Products' UNION ALL SELECT @s07, 'KPI33', 5.56, 1, NULL UNION ALL SELECT @s07, 'KPI34', 5.55, 2, NULL UNION ALL
    SELECT @s08, 'KPI25', 4.17, 0, '>= 95%' UNION ALL SELECT @s08, 'KPI08', 4.17, 1, '>= 90%' UNION ALL SELECT @s08, 'KPI27', 4.17, 2, NULL UNION ALL SELECT @s08, 'KPI28', 4.16, 3, NULL UNION ALL
    SELECT @s03, 'KPI09', 8.33, 0, NULL UNION ALL SELECT @s03, 'KPI30', 8.34, 1, '<= 1.50%' UNION ALL
    SELECT @s04, 'KPI11', 3.33, 0, NULL UNION ALL SELECT @s04, 'KPI12', 3.33, 1, '>= 85%' UNION ALL SELECT @s04, 'KPI13', 3.33, 2, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 3.33, 3, NULL UNION ALL SELECT @s04, 'KPI22', 3.34, 4, NULL UNION ALL
    SELECT @s05, 'KPI14', 5.55, 0, '>= 85%' UNION ALL SELECT @s05, 'KPI15', 5.55, 1, '>= 85%' UNION ALL SELECT @s05, 'KPI16', 5.56, 2, '>= 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s01,'KPI03',1 UNION ALL SELECT @s01,'KPI04',2 UNION ALL SELECT @s01,'KPI36',3 UNION ALL SELECT @s01,'KPI31',4 UNION ALL
    SELECT @s07,'KPI32',0 UNION ALL SELECT @s07,'KPI33',1 UNION ALL SELECT @s07,'KPI34',2 UNION ALL SELECT @s08,'KPI25',0 UNION ALL SELECT @s08,'KPI08',1 UNION ALL SELECT @s08,'KPI27',2 UNION ALL SELECT @s08,'KPI28',3 UNION ALL
    SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI30',1 UNION ALL SELECT @s04,'KPI11',0 UNION ALL SELECT @s04,'KPI12',1 UNION ALL SELECT @s04,'KPI13',2 UNION ALL SELECT @s04,'KPI21',3 UNION ALL SELECT @s04,'KPI22',4 UNION ALL
    SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI16',2) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 8. REG. EXECUTIVE CHEF
-- =====================================================================
SET @tpl_code := 'KPI_REG_EXC_CHEF_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. EXECUTIVE CHEF', 'Regional Executive Chef', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Regional%Executive Chef%' OR j.nama_jabatan LIKE '%Reg%Exc%Chef%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 20.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS01' AS code, 0 AS sort_order UNION ALL SELECT 'KS08',1 UNION ALL SELECT 'KS03',2 UNION ALL SELECT 'KS04',3 UNION ALL SELECT 'KS05',4) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS01' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s08 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS08' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 4.00 AS weight, 0 AS sort_order, '< 40% - 45%' AS tgt UNION ALL
    SELECT @s01, 'KPI03', 4.00, 1, '< 2%' UNION ALL SELECT @s01, 'KPI04', 4.00, 2, '< 0.5%' UNION ALL SELECT @s01, 'KPI36', 4.00, 3, '< 0.2%' UNION ALL SELECT @s01, 'KPI31', 4.00, 4, '< 11% - 13%' UNION ALL
    SELECT @s08, 'KPI25', 5.00, 0, '>= 95%' UNION ALL SELECT @s08, 'KPI08', 5.00, 1, '>= 90%' UNION ALL SELECT @s08, 'KPI27', 5.00, 2, NULL UNION ALL SELECT @s08, 'KPI28', 5.00, 3, NULL UNION ALL
    SELECT @s03, 'KPI09', 10.00, 0, NULL UNION ALL SELECT @s03, 'KPI30', 10.00, 1, '<= 0.50%' UNION ALL
    SELECT @s04, 'KPI11', 4.00, 0, NULL UNION ALL SELECT @s04, 'KPI12', 4.00, 1, '>= 80%' UNION ALL SELECT @s04, 'KPI13', 4.00, 2, '>= 2 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 4.00, 3, NULL UNION ALL SELECT @s04, 'KPI22', 4.00, 4, NULL UNION ALL
    SELECT @s05, 'KPI14', 5.00, 0, '>= 90%' UNION ALL SELECT @s05, 'KPI15', 5.00, 1, '>= 80%' UNION ALL SELECT @s05, 'KPI35', 5.00, 2, '<= SLA' UNION ALL SELECT @s05, 'KPI16', 5.00, 3, '>= 98%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s01,'KPI03',1 UNION ALL SELECT @s01,'KPI04',2 UNION ALL SELECT @s01,'KPI36',3 UNION ALL SELECT @s01,'KPI31',4 UNION ALL
    SELECT @s08,'KPI25',0 UNION ALL SELECT @s08,'KPI08',1 UNION ALL SELECT @s08,'KPI27',2 UNION ALL SELECT @s08,'KPI28',3 UNION ALL SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI30',1 UNION ALL
    SELECT @s04,'KPI11',0 UNION ALL SELECT @s04,'KPI12',1 UNION ALL SELECT @s04,'KPI13',2 UNION ALL SELECT @s04,'KPI21',3 UNION ALL SELECT @s04,'KPI22',4 UNION ALL
    SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI35',2 UNION ALL SELECT @s05,'KPI16',3) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 9. REG. SOUS CHEF
-- =====================================================================
SET @tpl_code := 'KPI_REG_SOUS_CHEF_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. SOUS CHEF', 'Regional Sous Chef', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND j.nama_jabatan LIKE '%Sous Chef%' AND j.nama_jabatan NOT LIKE '%Asst%' AND j.nama_jabatan NOT LIKE '%Assistant%'
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 20.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS01' AS code, 0 AS sort_order UNION ALL SELECT 'KS08',1 UNION ALL SELECT 'KS03',2 UNION ALL SELECT 'KS04',3 UNION ALL SELECT 'KS05',4) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS01' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s08 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS08' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 5.00 AS weight, 0 AS sort_order, '< 44% - 45%' AS tgt UNION ALL
    SELECT @s01, 'KPI03', 5.00, 1, '<= 1%' UNION ALL SELECT @s01, 'KPI04', 5.00, 2, '<= 0.5%' UNION ALL SELECT @s01, 'KPI05', 5.00, 3, '<= 0.2%' UNION ALL
    SELECT @s08, 'KPI25', 5.00, 0, '>= 90%' UNION ALL SELECT @s08, 'KPI08', 5.00, 1, '>= 90%' UNION ALL SELECT @s08, 'KPI27', 5.00, 2, NULL UNION ALL SELECT @s08, 'KPI28', 5.00, 3, NULL UNION ALL
    SELECT @s03, 'KPI09', 10.00, 0, NULL UNION ALL SELECT @s03, 'KPI30', 10.00, 1, '<= 0.50%' UNION ALL
    SELECT @s04, 'KPI11', 3.00, 0, NULL UNION ALL SELECT @s04, 'KPI12', 3.00, 1, '> 90%' UNION ALL SELECT @s04, 'KPI13', 3.00, 2, '10 Person & 100% on Time' UNION ALL
    SELECT @s04, 'KPI21', 3.00, 3, NULL UNION ALL SELECT @s04, 'KPI22', 3.00, 4, '22 Person' UNION ALL
    SELECT @s05, 'KPI14', 5.00, 0, '>= 90%' UNION ALL SELECT @s05, 'KPI15', 5.00, 1, '>= 80%' UNION ALL SELECT @s05, 'KPI35', 5.00, 2, '<= SLA' UNION ALL SELECT @s05, 'KPI16', 5.00, 3, '> 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s01,'KPI03',1 UNION ALL SELECT @s01,'KPI04',2 UNION ALL SELECT @s01,'KPI05',3 UNION ALL
    SELECT @s08,'KPI25',0 UNION ALL SELECT @s08,'KPI08',1 UNION ALL SELECT @s08,'KPI27',2 UNION ALL SELECT @s08,'KPI28',3 UNION ALL SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI30',1 UNION ALL
    SELECT @s04,'KPI11',0 UNION ALL SELECT @s04,'KPI12',1 UNION ALL SELECT @s04,'KPI13',2 UNION ALL SELECT @s04,'KPI21',3 UNION ALL SELECT @s04,'KPI22',4 UNION ALL
    SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI35',2 UNION ALL SELECT @s05,'KPI16',3) v ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

-- =====================================================================
-- 10. REG. ASST SOUS CHEF
-- =====================================================================
SET @tpl_code := 'KPI_REG_ASST_SOUS_CHEF_v1';
INSERT INTO `kpi_templates` (`code`, `name`, `description`, `version`, `template_status`, `scoring_rules`, `status`, `created_at`, `updated_at`)
SELECT @tpl_code, 'REG. ASST SOUS CHEF', 'Regional Assistant Sous Chef', 1, 'draft', '{"exceeding_min":100,"meeting_min":85,"below_max":85}', 'A', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci));
SET @tpl_id := (SELECT `id` FROM `kpi_templates` WHERE `code` = (@tpl_code COLLATE utf8mb4_unicode_ci) LIMIT 1);
INSERT INTO `kpi_template_positions` (`kpi_template_id`, `id_jabatan`, `effective_from`, `effective_to`, `status`, `created_at`, `updated_at`)
SELECT @tpl_id, j.id_jabatan, NULL, NULL, 'A', NOW(), NOW()
FROM `tbl_data_jabatan` j WHERE j.status = 'A' AND (j.nama_jabatan LIKE '%Asst%Sous%' OR j.nama_jabatan LIKE '%Assistant%Sous%')
  AND NOT EXISTS (SELECT 1 FROM `kpi_template_positions` tp WHERE tp.kpi_template_id = @tpl_id AND tp.id_jabatan = j.id_jabatan);
DELETE FROM `kpi_template_strategies` WHERE `kpi_template_id` = @tpl_id;
INSERT INTO `kpi_template_strategies` (`kpi_template_id`, `kpi_key_strategy_id`, `weight_percent`, `sort_order`, `created_at`, `updated_at`)
SELECT @tpl_id, ks.id, 20.00, v.sort_order, NOW(), NOW()
FROM (SELECT 'KS01' AS code, 0 AS sort_order UNION ALL SELECT 'KS08',1 UNION ALL SELECT 'KS03',2 UNION ALL SELECT 'KS04',3 UNION ALL SELECT 'KS05',4) v JOIN `kpi_key_strategies` ks ON ks.code = (v.code COLLATE utf8mb4_unicode_ci);
SET @s01 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS01' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s08 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS08' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s03 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS03' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s04 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS04' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @s05 := (SELECT ts.id FROM `kpi_template_strategies` ts JOIN `kpi_key_strategies` ks ON ks.id = ts.kpi_key_strategy_id WHERE ts.kpi_template_id = @tpl_id AND ks.code = CONVERT('KS05' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
INSERT INTO `kpi_template_items` (`kpi_template_strategy_id`, `name`, `weight_percent`, `target_value`, `target_direction`, `frequency`, `formula`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT v.strategy_id, p.name, v.weight, COALESCE(v.tgt, p.target_value), p.target_direction, p.frequency, p.formula, v.sort_order, 'A', NOW(), NOW()
FROM (
    SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 5.00 AS weight, 0 AS sort_order, '<= 44% - 45%' AS tgt UNION ALL
    SELECT @s01, 'KPI03', 5.00, 1, '<= 1%' UNION ALL SELECT @s01, 'KPI04', 5.00, 2, '<= 0.5%' UNION ALL SELECT @s01, 'KPI36', 5.00, 3, '<= 0.2%' UNION ALL
    SELECT @s08, 'KPI25', 6.67, 0, '>= 90%' UNION ALL SELECT @s08, 'KPI08', 6.67, 1, '>= 90%' UNION ALL SELECT @s08, 'KPI28', 6.66, 2, NULL UNION ALL
    SELECT @s03, 'KPI09', 10.00, 0, NULL UNION ALL SELECT @s03, 'KPI30', 10.00, 1, '< 0.50%' UNION ALL
    SELECT @s04, 'KPI13', 6.67, 0, '>= 2 Person & 100% on Time' UNION ALL SELECT @s04, 'KPI21', 6.67, 1, NULL UNION ALL SELECT @s04, 'KPI22', 6.66, 2, '> 2 Person' UNION ALL
    SELECT @s05, 'KPI14', 6.67, 0, '>= 90%' UNION ALL SELECT @s05, 'KPI15', 6.67, 1, '>= 85%' UNION ALL SELECT @s05, 'KPI16', 6.66, 2, '> 95%'
) v JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);
INSERT INTO `kpi_template_item_parameters` (`kpi_template_item_id`, `kpi_parameter_id`, `is_required`, `sort_order`, `created_at`, `updated_at`)
SELECT ti.id, p.id, 1, 0, NOW(), NOW() FROM `kpi_template_items` ti
JOIN (SELECT @s01 AS strategy_id, 'KPI02' AS kpi_code, 0 AS sort_order UNION ALL SELECT @s01,'KPI03',1 UNION ALL SELECT @s01,'KPI04',2 UNION ALL SELECT @s01,'KPI36',3 UNION ALL
    SELECT @s08,'KPI25',0 UNION ALL SELECT @s08,'KPI08',1 UNION ALL SELECT @s08,'KPI28',2 UNION ALL SELECT @s03,'KPI09',0 UNION ALL SELECT @s03,'KPI30',1 UNION ALL
    SELECT @s04,'KPI13',0 UNION ALL SELECT @s04,'KPI21',1 UNION ALL SELECT @s04,'KPI22',2 UNION ALL SELECT @s05,'KPI14',0 UNION ALL SELECT @s05,'KPI15',1 UNION ALL SELECT @s05,'KPI16',2) v
    ON v.strategy_id = ti.kpi_template_strategy_id AND v.sort_order = ti.sort_order JOIN `kpi_parameters` p ON p.code = (v.kpi_code COLLATE utf8mb4_unicode_ci);

COMMIT;

-- Verifikasi:
-- SELECT code, name FROM kpi_templates WHERE code LIKE 'KPI_%' ORDER BY code;
-- SELECT COUNT(*) FROM kpi_parameters WHERE code LIKE 'D%' OR code LIKE 'KPI%';
