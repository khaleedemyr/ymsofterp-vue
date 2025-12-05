-- Add parent_id column to chart_of_accounts table
-- Jalankan query ini jika tabel sudah ada dan belum ada kolom parent_id

-- Cek dulu apakah kolom parent_id sudah ada, jika belum ada baru tambahkan
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'chart_of_accounts'
    AND COLUMN_NAME = 'parent_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `chart_of_accounts` ADD COLUMN `parent_id` bigint(20) unsigned DEFAULT NULL AFTER `type`, ADD KEY `chart_of_accounts_parent_id_index` (`parent_id`)',
    'SELECT "Column parent_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint jika belum ada
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'chart_of_accounts'
    AND CONSTRAINT_NAME = 'chart_of_accounts_parent_id_foreign'
);

SET @sql2 = IF(@constraint_exists = 0,
    'ALTER TABLE `chart_of_accounts` ADD CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL',
    'SELECT "Foreign key constraint already exists" AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Atau jika query di atas error, gunakan query sederhana ini (pastikan kolom belum ada):
-- ALTER TABLE `chart_of_accounts` 
-- ADD COLUMN `parent_id` bigint(20) unsigned DEFAULT NULL AFTER `type`,
-- ADD KEY `chart_of_accounts_parent_id_index` (`parent_id`),
-- ADD CONSTRAINT `chart_of_accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL;

