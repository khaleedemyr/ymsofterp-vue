-- =====================================================
-- Create Laravel Queue & Cache Tables (Complete Setup)
-- =====================================================
-- Script ini membuat semua tabel yang diperlukan untuk:
-- 1. Laravel Cache Lock mechanism (cache, cache_locks)
-- 2. Laravel Queue system (jobs, job_batches, failed_jobs)
-- 
-- Error yang diperbaiki:
-- - Table 'db_justus.cache_locks' doesn't exist
-- - Table 'db_justus.jobs' doesn't exist
-- 
-- Eksekusi script ini untuk membuat semua tabel sekaligus

-- =====================================================
-- 1. CACHE TABLES
-- =====================================================

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

-- =====================================================
-- 2. QUEUE TABLES
-- =====================================================

-- Create jobs table if not exists
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_jobs_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create job_batches table if not exists
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL DEFAULT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create failed_jobs table if not exists
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL UNIQUE,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VERIFICATION
-- =====================================================

-- Verify all tables created
SELECT 
    'cache' as table_name,
    COUNT(*) as row_count,
    'OK' as status
FROM `cache`
UNION ALL
SELECT 
    'cache_locks' as table_name,
    COUNT(*) as row_count,
    'OK' as status
FROM `cache_locks`
UNION ALL
SELECT 
    'jobs' as table_name,
    COUNT(*) as row_count,
    'OK' as status
FROM `jobs`
UNION ALL
SELECT 
    'job_batches' as table_name,
    COUNT(*) as row_count,
    'OK' as status
FROM `job_batches`
UNION ALL
SELECT 
    'failed_jobs' as table_name,
    COUNT(*) as row_count,
    'OK' as status
FROM `failed_jobs`;

