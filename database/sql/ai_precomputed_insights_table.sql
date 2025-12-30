-- =====================================================
-- AI PRECOMPUTED INSIGHTS TABLE
-- =====================================================
-- Tabel untuk menyimpan insights yang sudah di-generate
-- Digunakan untuk scheduled reports dan common queries
-- =====================================================

CREATE TABLE IF NOT EXISTS `ai_precomputed_insights` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `insight_type` VARCHAR(100) NOT NULL COMMENT 'daily_sales_summary, inventory_alerts, demand_forecast, etc',
    `context_key` VARCHAR(255) NOT NULL COMMENT 'Unique key untuk context (outlet_id, date, etc)',
    `context_data` JSON DEFAULT NULL COMMENT 'Additional context data',
    `insight_text` LONGTEXT COMMENT 'Pre-computed insight text',
    `insight_data` JSON DEFAULT NULL COMMENT 'Structured insight data',
    `generated_at` DATETIME NOT NULL COMMENT 'When insight was generated',
    `expires_at` DATETIME NOT NULL COMMENT 'When insight expires',
    `tokens_used` INT DEFAULT 0,
    `cost_rupiah` DECIMAL(12,2) DEFAULT 0.00,
    `model_used` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `unique_insight` (`insight_type`, `context_key`),
    INDEX `idx_insight_type` (`insight_type`),
    INDEX `idx_context_key` (`context_key`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_generated_at` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUERIES UNTUK MONITORING PRECOMPUTED INSIGHTS
-- =====================================================

-- List insights by type
SELECT 
    insight_type,
    COUNT(*) as total_insights,
    SUM(cost_rupiah) as total_cost,
    MAX(generated_at) as last_generated
FROM ai_precomputed_insights
GROUP BY insight_type
ORDER BY total_insights DESC;

-- Expired insights (need regeneration)
SELECT 
    insight_type,
    context_key,
    generated_at,
    expires_at,
    TIMESTAMPDIFF(HOUR, NOW(), expires_at) as hours_until_expiry
FROM ai_precomputed_insights
WHERE expires_at < DATE_ADD(NOW(), INTERVAL 1 HOUR)
ORDER BY expires_at ASC;

-- Clean expired insights
DELETE FROM ai_precomputed_insights WHERE expires_at < NOW();

