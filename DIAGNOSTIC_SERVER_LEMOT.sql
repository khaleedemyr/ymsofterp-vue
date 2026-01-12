-- =====================================================
-- DIAGNOSTIC: IDENTIFIKASI SUMBER MASALAH LEMOT
-- =====================================================
-- Script ini akan membantu identifikasi:
-- 1. Query yang paling lambat
-- 2. Query yang paling sering dipanggil
-- 3. Tabel yang paling banyak diakses
-- 4. Index yang missing
-- 5. Process yang sedang running
-- =====================================================

-- 1. CEK QUERY YANG PALING LAMBAT (TOP 20)
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    created_at,
    TIMESTAMPDIFF(SECOND, created_at, NOW()) as seconds_ago
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
ORDER BY query_time DESC
LIMIT 20;

-- 2. CEK QUERY YANG PALING SERING DIPANGGIL (TOP 20)
-- =====================================================
SELECT 
    sql_text,
    COUNT(*) as call_count,
    AVG(query_time) as avg_query_time,
    MAX(query_time) as max_query_time,
    SUM(query_time) as total_query_time,
    SUM(rows_examined) as total_rows_examined
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
GROUP BY sql_text
ORDER BY call_count DESC
LIMIT 20;

-- 3. CEK TABEL YANG PALING SERING DIAKSES
-- =====================================================
SELECT 
    CASE 
        WHEN sql_text LIKE '%FROM `%' THEN 
            SUBSTRING_INDEX(SUBSTRING_INDEX(sql_text, 'FROM `', -1), '`', 1)
        WHEN sql_text LIKE '%FROM %' THEN 
            SUBSTRING_INDEX(SUBSTRING_INDEX(sql_text, 'FROM ', -1), ' ', 1)
        ELSE 'unknown'
    END as table_name,
    COUNT(*) as access_count,
    AVG(query_time) as avg_query_time,
    MAX(query_time) as max_query_time,
    SUM(rows_examined) as total_rows_examined
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
  AND (sql_text LIKE '%FROM `%' OR sql_text LIKE '%FROM %')
GROUP BY table_name
ORDER BY access_count DESC
LIMIT 20;

-- 4. CEK QUERY YANG EXAMINE BANYAK ROWS (TOP 20)
-- =====================================================
SELECT 
    sql_text,
    query_time,
    rows_examined,
    rows_sent,
    ROUND(rows_examined / NULLIF(rows_sent, 0), 2) as ratio_examined_sent,
    created_at
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
  AND rows_examined > 1000
ORDER BY rows_examined DESC
LIMIT 20;

-- 5. CEK QUERY YANG TIDAK PAKAI INDEX (FULL TABLE SCAN)
-- =====================================================
SELECT 
    sql_text,
    query_time,
    rows_examined,
    created_at
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
  AND rows_examined > 10000  -- Kemungkinan full table scan
ORDER BY rows_examined DESC
LIMIT 20;

-- 6. CEK PROCESS YANG SEDANG RUNNING
-- =====================================================
SHOW PROCESSLIST;

-- 7. CEK PROCESS YANG LAMA RUNNING (>5 detik)
-- =====================================================
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    info
FROM information_schema.processlist
WHERE time > 5
  AND command != 'Sleep'
ORDER BY time DESC;

-- 8. CEK QUERY YANG TERKAIT MEMBER_APPS_MEMBERS
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    created_at
FROM mysql.slow_log 
WHERE sql_text LIKE '%member_apps_members%'
  AND sql_text NOT LIKE '%EXPLAIN%'
ORDER BY query_time DESC
LIMIT 20;

-- 9. CEK QUERY YANG TERKAIT ORDERS
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    created_at
FROM mysql.slow_log 
WHERE (sql_text LIKE '%orders%' OR sql_text LIKE '%order_items%')
  AND sql_text NOT LIKE '%EXPLAIN%'
ORDER BY query_time DESC
LIMIT 20;

-- 10. CEK QUERY YANG TERKAIT ACTIVITY_LOGS
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    created_at
FROM mysql.slow_log 
WHERE sql_text LIKE '%activity_logs%'
  AND sql_text NOT LIKE '%EXPLAIN%'
ORDER BY query_time DESC
LIMIT 20;

-- 11. CEK INDEX YANG MISSING (ESTIMASI)
-- =====================================================
-- Query yang sering dipanggil tapi rows_examined sangat besar
-- kemungkinan tidak ada index yang tepat
SELECT 
    sql_text,
    COUNT(*) as call_count,
    AVG(rows_examined) as avg_rows_examined,
    MAX(rows_examined) as max_rows_examined,
    AVG(query_time) as avg_query_time
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
GROUP BY sql_text
HAVING avg_rows_examined > 10000
ORDER BY call_count DESC, avg_rows_examined DESC
LIMIT 20;

-- 12. CEK QUERY TIME DISTRIBUTION
-- =====================================================
SELECT 
    CASE 
        WHEN query_time < 1 THEN '< 1s'
        WHEN query_time < 5 THEN '1-5s'
        WHEN query_time < 10 THEN '5-10s'
        WHEN query_time < 30 THEN '10-30s'
        ELSE '> 30s'
    END as query_time_range,
    COUNT(*) as query_count,
    AVG(rows_examined) as avg_rows_examined
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
GROUP BY query_time_range
ORDER BY 
    CASE query_time_range
        WHEN '< 1s' THEN 1
        WHEN '1-5s' THEN 2
        WHEN '5-10s' THEN 3
        WHEN '10-30s' THEN 4
        ELSE 5
    END;

-- 13. CEK QUERY YANG DIPANGGIL DARI BERBAGAI APLIKASI
-- =====================================================
-- Identifikasi pattern query dari berbagai app
SELECT 
    CASE 
        WHEN sql_text LIKE '%member_apps_members%' THEN 'Member App'
        WHEN sql_text LIKE '%orders%' OR sql_text LIKE '%order_items%' THEN 'POS/Order'
        WHEN sql_text LIKE '%activity_logs%' THEN 'Activity Log'
        WHEN sql_text LIKE '%users%' THEN 'User Management'
        ELSE 'Other'
    END as app_category,
    COUNT(*) as query_count,
    AVG(query_time) as avg_query_time,
    MAX(query_time) as max_query_time,
    SUM(rows_examined) as total_rows_examined
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
GROUP BY app_category
ORDER BY query_count DESC;

-- 14. CEK QUERY YANG LOCK TABLE LAMA
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    created_at
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
  AND lock_time > 1
ORDER BY lock_time DESC
LIMIT 20;

-- 15. CEK QUERY YANG TERKAIT JOIN BANYAK TABEL
-- =====================================================
SELECT 
    sql_text,
    query_time,
    rows_examined,
    (LENGTH(sql_text) - LENGTH(REPLACE(sql_text, 'JOIN', ''))) / LENGTH('JOIN') as join_count,
    created_at
FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%slow_log%'
  AND sql_text NOT LIKE '%EXPLAIN%'
  AND sql_text LIKE '%JOIN%'
ORDER BY query_time DESC
LIMIT 20;
