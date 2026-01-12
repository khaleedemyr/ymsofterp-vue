-- ============================================
-- SCRIPT UNTUK CEK SLOW QUERIES DI MYSQL
-- ============================================
-- Jalankan script ini untuk enable dan cek slow query log
-- ============================================

-- ============================================
-- STEP 1: CEK STATUS SLOW QUERY LOG SAAT INI
-- ============================================
-- Cek apakah slow query log sudah enabled
SHOW VARIABLES LIKE 'slow_query_log%';
SHOW VARIABLES LIKE 'long_query_time';
SHOW VARIABLES LIKE 'log_queries_not_using_indexes';

-- ============================================
-- STEP 2: ENABLE SLOW QUERY LOG
-- ============================================
-- Enable slow query log (akan aktif sampai MySQL restart)
SET GLOBAL slow_query_log = 'ON';

-- Set threshold untuk slow query (dalam detik)
-- Query yang lebih lama dari ini akan di-log
-- Rekomendasi: 1 detik untuk production, 0.5 detik untuk development
SET GLOBAL long_query_time = 1;

-- Log queries yang tidak menggunakan index (opsional, tapi sangat membantu)
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- ============================================
-- STEP 3: SET LOKASI FILE LOG
-- ============================================
-- Cek lokasi file log saat ini
SHOW VARIABLES LIKE 'slow_query_log_file';

-- Set lokasi file log (jika perlu)
-- SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';

-- ============================================
-- STEP 4: CEK SLOW QUERIES YANG SUDAH TER-LOG
-- ============================================
-- Catatan: Untuk melihat isi file log, gunakan command line:
-- Linux: tail -f /var/log/mysql/slow-query.log
-- Windows: Get-Content -Path "C:\path\to\slow-query.log" -Tail 50 -Wait

-- ============================================
-- STEP 5: ANALISA SLOW QUERIES DENGAN PERFORMANCE SCHEMA
-- ============================================
-- Cek apakah performance_schema enabled
SHOW VARIABLES LIKE 'performance_schema';

-- Jika performance_schema enabled, bisa cek slow queries dari sini:
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    SUM_TIMER_WAIT/1000000000000 as total_time_seconds,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds,
    MAX_TIMER_WAIT/1000000000000 as max_time_seconds,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND AVG_TIMER_WAIT/1000000000000 > 1  -- Query yang rata-rata > 1 detik
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 20;

-- ============================================
-- STEP 6: CEK QUERIES YANG PALING SERING DIEKSEKUSI
-- ============================================
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY COUNT_STAR DESC
LIMIT 20;

-- ============================================
-- STEP 7: CEK QUERIES YANG TIDAK MENGGUNAKAN INDEX
-- ============================================
-- Query ini akan menunjukkan queries yang scan banyak rows
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent,
    (SUM_ROWS_EXAMINED / SUM_ROWS_SENT) as rows_examined_per_row_sent
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND SUM_ROWS_EXAMINED > 10000  -- Scan lebih dari 10K rows
ORDER BY SUM_ROWS_EXAMINED DESC
LIMIT 20;

-- ============================================
-- STEP 8: RESET PERFORMANCE SCHEMA (OPSIONAL)
-- ============================================
-- Jika ingin reset statistik untuk mulai monitoring dari awal
-- TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Slow query log akan aktif sampai MySQL restart
-- 2. Untuk membuat permanent, edit my.cnf atau my.ini:
--    [mysqld]
--    slow_query_log = 1
--    slow_query_log_file = /var/log/mysql/slow-query.log
--    long_query_time = 1
--    log_queries_not_using_indexes = 1
-- 
-- 3. File log akan terus bertambah, pastikan ada rotasi log
-- 4. Monitor ukuran file log secara berkala
-- 5. Performance_schema harus enabled untuk query di atas
