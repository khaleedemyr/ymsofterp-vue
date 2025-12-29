-- =====================================================
-- Table: ai_usage_logs
-- Description: Log penggunaan AI API untuk tracking budget
-- =====================================================

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(50) NOT NULL COMMENT 'Provider AI: gemini, openai, claude',
  `request_type` VARCHAR(50) NOT NULL COMMENT 'Tipe request: insight, qa',
  `input_tokens` BIGINT NOT NULL DEFAULT 0 COMMENT 'Jumlah input tokens',
  `output_tokens` BIGINT NOT NULL DEFAULT 0 COMMENT 'Jumlah output tokens',
  `total_tokens` BIGINT NOT NULL DEFAULT 0 COMMENT 'Total tokens (input + output)',
  `cost_usd` DECIMAL(10, 6) NOT NULL DEFAULT 0.000000 COMMENT 'Biaya dalam USD',
  `cost_rupiah` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Biaya dalam Rupiah',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_provider` (`provider`),
  INDEX `idx_request_type` (`request_type`),
  INDEX `idx_provider_created_at` (`provider`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log penggunaan AI API untuk tracking budget';

-- =====================================================
-- Query untuk Monitoring Usage
-- =====================================================

-- 1. Total usage bulan ini (Claude)
SELECT 
    SUM(cost_rupiah) AS total_cost_rupiah,
    SUM(cost_usd) AS total_cost_usd,
    COUNT(*) AS total_requests,
    SUM(input_tokens) AS total_input_tokens,
    SUM(output_tokens) AS total_output_tokens,
    SUM(total_tokens) AS total_tokens,
    AVG(cost_rupiah) AS avg_cost_per_request
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW());

-- 2. Usage per hari (bulan ini)
SELECT 
    DATE(created_at) AS tanggal,
    COUNT(*) AS jumlah_request,
    SUM(cost_rupiah) AS total_cost_rupiah,
    SUM(total_tokens) AS total_tokens
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW())
GROUP BY DATE(created_at)
ORDER BY tanggal DESC;

-- 3. Usage per request type
SELECT 
    request_type,
    COUNT(*) AS jumlah_request,
    SUM(cost_rupiah) AS total_cost_rupiah,
    AVG(cost_rupiah) AS avg_cost_per_request
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW())
GROUP BY request_type;

-- 4. Top 10 request termahal
SELECT 
    id,
    request_type,
    input_tokens,
    output_tokens,
    total_tokens,
    cost_rupiah,
    created_at
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW())
ORDER BY cost_rupiah DESC
LIMIT 10;

-- 5. Remaining budget (asumsi limit Rp 2 juta)
SELECT 
    2000000 AS budget_limit,
    COALESCE(SUM(cost_rupiah), 0) AS current_usage,
    2000000 - COALESCE(SUM(cost_rupiah), 0) AS remaining_budget,
    ROUND((COALESCE(SUM(cost_rupiah), 0) / 2000000) * 100, 2) AS usage_percentage
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW());

-- 6. Usage per bulan (12 bulan terakhir)
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') AS bulan,
    COUNT(*) AS jumlah_request,
    SUM(cost_rupiah) AS total_cost_rupiah,
    SUM(total_tokens) AS total_tokens
FROM ai_usage_logs
WHERE provider = 'claude'
AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY bulan DESC;

-- 7. Daily average cost
SELECT 
    AVG(daily_cost) AS avg_daily_cost,
    MAX(daily_cost) AS max_daily_cost,
    MIN(daily_cost) AS min_daily_cost
FROM (
    SELECT 
        DATE(created_at) AS tanggal,
        SUM(cost_rupiah) AS daily_cost
    FROM ai_usage_logs
    WHERE provider = 'claude'
    AND MONTH(created_at) = MONTH(NOW())
    AND YEAR(created_at) = YEAR(NOW())
    GROUP BY DATE(created_at)
) AS daily_stats;

-- 8. Check jika budget sudah hampir habis (80% threshold)
SELECT 
    CASE 
        WHEN (SUM(cost_rupiah) / 2000000) * 100 >= 80 THEN 'WARNING: Budget hampir habis!'
        WHEN (SUM(cost_rupiah) / 2000000) * 100 >= 100 THEN 'BLOCKED: Budget sudah habis!'
        ELSE 'OK: Budget masih aman'
    END AS status,
    SUM(cost_rupiah) AS current_usage,
    2000000 - SUM(cost_rupiah) AS remaining_budget,
    ROUND((SUM(cost_rupiah) / 2000000) * 100, 2) AS usage_percentage
FROM ai_usage_logs
WHERE provider = 'claude'
AND MONTH(created_at) = MONTH(NOW())
AND YEAR(created_at) = YEAR(NOW());

-- =====================================================
-- Query untuk Maintenance
-- =====================================================

-- Hapus log lebih dari 6 bulan (optional, untuk cleanup)
-- DELETE FROM ai_usage_logs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Reset usage bulanan (jika perlu manual reset)
-- UPDATE ai_usage_logs 
-- SET created_at = DATE_SUB(created_at, INTERVAL 1 MONTH)
-- WHERE provider = 'claude' 
-- AND MONTH(created_at) = MONTH(NOW());

