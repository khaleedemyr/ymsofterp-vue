-- =====================================================
-- AI USAGE LOGS EXTENDED
-- =====================================================
-- Extend existing ai_usage_logs table untuk track per cabang/gudang
-- =====================================================

-- Check existing structure
-- SELECT * FROM ai_usage_logs LIMIT 1;

-- Jika perlu extend, tambahkan kolom:
-- ALTER TABLE ai_usage_logs 
-- ADD COLUMN `outlet_id` INT DEFAULT NULL COMMENT 'FK ke tbl_data_outlet.id_outlet' AFTER `user_id`,
-- ADD COLUMN `warehouse_id` INT DEFAULT NULL COMMENT 'FK ke warehouses.id' AFTER `outlet_id`,
-- ADD COLUMN `context_type` VARCHAR(50) DEFAULT NULL COMMENT 'sales, inventory, cross, bom' AFTER `query_type`,
-- ADD INDEX `idx_outlet_id` (`outlet_id`),
-- ADD INDEX `idx_warehouse_id` (`warehouse_id`),
-- ADD INDEX `idx_context_type` (`context_type`);

-- =====================================================
-- QUERIES UNTUK MONITORING USAGE PER CABANG/GUDANG
-- =====================================================

-- Usage per outlet
SELECT 
    o.id_outlet,
    o.nama_outlet,
    COUNT(*) as total_queries,
    SUM(ul.tokens_used) as total_tokens,
    SUM(ul.cost_rupiah) as total_cost,
    AVG(ul.tokens_used) as avg_tokens_per_query
FROM ai_usage_logs ul
LEFT JOIN tbl_data_outlet o ON ul.outlet_id = o.id_outlet
WHERE ul.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY o.id_outlet, o.nama_outlet
ORDER BY total_cost DESC;

-- Usage per warehouse
SELECT 
    w.id,
    w.name as warehouse_name,
    COUNT(*) as total_queries,
    SUM(ul.tokens_used) as total_tokens,
    SUM(ul.cost_rupiah) as total_cost
FROM ai_usage_logs ul
LEFT JOIN warehouses w ON ul.warehouse_id = w.id
WHERE ul.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY w.id, w.name
ORDER BY total_cost DESC;

-- Usage per context type
SELECT 
    context_type,
    COUNT(*) as total_queries,
    SUM(ul.tokens_used) as total_tokens,
    SUM(ul.cost_rupiah) as total_cost,
    AVG(ul.tokens_used) as avg_tokens
FROM ai_usage_logs ul
WHERE ul.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY context_type
ORDER BY total_cost DESC;

-- Daily usage trend
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_queries,
    SUM(tokens_used) as total_tokens,
    SUM(cost_rupiah) as total_cost
FROM ai_usage_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

