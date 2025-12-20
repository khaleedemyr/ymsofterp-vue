-- =====================================================
-- Setup Queue Tables untuk Member Notification
-- =====================================================
-- Jalankan query ini di database MySQL untuk membuat table queue
-- Jika table sudah ada, query ini akan di-skip (IF NOT EXISTS)

-- 1. Table jobs (untuk menyimpan queue jobs)
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_jobs_queue` (`queue`),
    INDEX `idx_jobs_reserved_at` (`reserved_at`),
    INDEX `idx_jobs_available_at` (`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table failed_jobs (untuk menyimpan jobs yang gagal)
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table job_batches (untuk batch jobs - optional tapi recommended)
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL DEFAULT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VERIFIKASI
-- =====================================================
-- Cek apakah table sudah dibuat dengan benar
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
