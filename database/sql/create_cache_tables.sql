-- =====================================================
-- Create Cache Tables (cache and cache_locks)
-- =====================================================
-- Tabel ini diperlukan untuk Laravel Cache Lock mechanism
-- Error: Table 'db_justus.cache_locks' doesn't exist
-- Eksekusi script ini untuk membuat tabel cache dan cache_locks

-- Create cache table if not exists
CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    INDEX `idx_cache_expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cache_locks table if not exists
CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    INDEX `idx_cache_locks_expiration` (`expiration`),
    INDEX `idx_cache_locks_owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify tables created
SELECT 
    'cache' as table_name,
    COUNT(*) as row_count
FROM `cache`
UNION ALL
SELECT 
    'cache_locks' as table_name,
    COUNT(*) as row_count
FROM `cache_locks`;

