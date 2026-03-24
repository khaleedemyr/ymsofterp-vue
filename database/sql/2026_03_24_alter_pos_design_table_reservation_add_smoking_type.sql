-- =====================================================
-- ALTER TABLE: tambah kolom smoking_type untuk setting meja
-- Jalankan jika tabel pos_design_table_reservation_settings sudah terlanjur dibuat
-- =====================================================

SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_design_table_reservation_settings'
);

SET @has_column := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_design_table_reservation_settings'
    AND COLUMN_NAME = 'smoking_type'
);

SET @sql := IF(
  @has_table = 1 AND @has_column = 0,
  'ALTER TABLE `pos_design_table_reservation_settings` ADD COLUMN `smoking_type` ENUM(''smoking'',''non_smoking'') NOT NULL DEFAULT ''non_smoking'' AFTER `allow_reservation`;',
  'SELECT ''skip alter: table tidak ada atau kolom sudah ada'' AS info;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_design_table_reservation_settings'
    AND INDEX_NAME = 'idx_reservation_setting_smoking_type'
);

SET @sql_index := IF(
  @has_table = 1 AND @has_index = 0,
  'ALTER TABLE `pos_design_table_reservation_settings` ADD KEY `idx_reservation_setting_smoking_type` (`smoking_type`);',
  'SELECT ''skip index: table tidak ada atau index sudah ada'' AS info;'
);

PREPARE stmt_index FROM @sql_index;
EXECUTE stmt_index;
DEALLOCATE PREPARE stmt_index;
