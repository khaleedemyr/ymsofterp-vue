-- =====================================================
-- AI QUERY CACHE TABLE
-- =====================================================
-- Tabel untuk caching hasil query AI
-- Mengurangi API calls dan biaya
-- =====================================================

CREATE TABLE IF NOT EXISTS `ai_query_cache` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `query_hash` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Hash dari query + context + params',
    `query_text` TEXT COMMENT 'Original query text',
    `context_type` VARCHAR(50) DEFAULT NULL COMMENT 'sales, inventory, cross, bom',
    `context_data` JSON DEFAULT NULL COMMENT 'Additional context (outlet_ids, date_range, etc)',
    `response` LONGTEXT COMMENT 'AI response (cached)',
    `tokens_used` INT DEFAULT 0 COMMENT 'Total tokens used (input + output)',
    `cost_rupiah` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Cost in Rupiah',
    `model_used` VARCHAR(100) DEFAULT NULL COMMENT 'Model yang digunakan (claude-sonnet, gemini-pro, etc)',
    `expires_at` DATETIME NOT NULL COMMENT 'Cache expiration time',
    `hit_count` INT DEFAULT 0 COMMENT 'Number of cache hits',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_query_hash` (`query_hash`),
    INDEX `idx_context_type` (`context_type`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUERIES UNTUK MONITORING CACHE
-- =====================================================

-- Hit rate cache
SELECT 
    COUNT(*) as total_queries,
    SUM(CASE WHEN hit_count > 0 THEN 1 ELSE 0 END) as cached_queries,
    ROUND(SUM(CASE WHEN hit_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as hit_rate_percent
FROM ai_query_cache;

-- Cache effectiveness (cost savings)
SELECT 
    SUM(cost_rupiah * hit_count) as total_cost_saved,
    COUNT(*) as unique_queries,
    AVG(hit_count) as avg_hits_per_query
FROM ai_query_cache
WHERE hit_count > 0;

-- Most cached queries
SELECT 
    query_text,
    context_type,
    hit_count,
    cost_rupiah,
    created_at
FROM ai_query_cache
WHERE hit_count > 0
ORDER BY hit_count DESC
LIMIT 20;

-- Clean expired cache (run periodically)
DELETE FROM ai_query_cache WHERE expires_at < NOW();

