-- ============================================
-- DIAGNOSTIC SCRIPT: ANALISA SERVER LEMOT & CPU 100%
-- ============================================
-- Jalankan script ini untuk mengidentifikasi masalah performa
-- ============================================

-- ============================================
-- STEP 1: CEK PROCESS MYSQL YANG STUCK
-- ============================================
-- Lihat semua process yang sedang berjalan dan waktu eksekusinya
SELECT 
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME,
    STATE,
    LEFT(INFO, 100) as QUERY_PREVIEW
FROM information_schema.PROCESSLIST
WHERE COMMAND != 'Sleep'
ORDER BY TIME DESC;

-- ============================================
-- STEP 2: CEK PROCESS YANG LAMA (> 30 detik)
-- ============================================
-- Process yang berjalan lebih dari 30 detik kemungkinan stuck
SELECT 
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME as TIME_SECONDS,
    STATE,
    LEFT(INFO, 200) as QUERY
FROM information_schema.PROCESSLIST
WHERE TIME > 30
ORDER BY TIME DESC;

-- ============================================
-- STEP 3: CEK PROCESS YANG MEMBUAT INDEX ATAU ALTER TABLE
-- ============================================
-- Process ini biasanya memblokir query lain
SELECT 
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME,
    STATE,
    INFO
FROM information_schema.PROCESSLIST
WHERE INFO LIKE '%CREATE INDEX%' 
   OR INFO LIKE '%ALTER TABLE%'
   OR INFO LIKE '%DROP INDEX%'
   OR INFO LIKE '%ADD INDEX%'
ORDER BY TIME DESC;

-- ============================================
-- STEP 4: CEK METADATA LOCK
-- ============================================
-- Lihat process yang menunggu metadata lock
SELECT 
    p.ID,
    p.USER,
    p.HOST,
    p.DB,
    p.COMMAND,
    p.TIME,
    p.STATE,
    LEFT(p.INFO, 200) as QUERY,
    p.TIME as WAIT_TIME_SECONDS
FROM information_schema.PROCESSLIST p
WHERE p.STATE LIKE '%metadata%'
   OR p.STATE LIKE '%Waiting for%'
ORDER BY p.TIME DESC;

-- ============================================
-- STEP 5: CEK QUERY YANG LAMBAT (jika slow query log enabled)
-- ============================================
-- Catatan: Hanya berfungsi jika slow_query_log = ON
SHOW VARIABLES LIKE 'slow_query_log%';
SHOW VARIABLES LIKE 'long_query_time';

-- ============================================
-- STEP 6: CEK TABLE LOCKS
-- ============================================
-- Lihat table yang sedang di-lock
SELECT 
    r.trx_id waiting_trx_id,
    r.trx_mysql_thread_id waiting_thread,
    r.trx_query waiting_query,
    b.trx_id blocking_trx_id,
    b.trx_mysql_thread_id blocking_thread,
    b.trx_query blocking_query
FROM information_schema.innodb_lock_waits w
INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id;

-- ============================================
-- STEP 7: CEK CONNECTION COUNT
-- ============================================
-- Lihat jumlah koneksi aktif
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Threads_running';
SHOW STATUS LIKE 'Max_used_connections';
SHOW VARIABLES LIKE 'max_connections';

-- ============================================
-- STEP 8: CEK QUERY YANG PALING SERING DIEKSEKUSI
-- ============================================
-- Catatan: Hanya berfungsi jika performance_schema enabled
SELECT 
    DIGEST_TEXT,
    COUNT_STAR as EXECUTION_COUNT,
    SUM_TIMER_WAIT/1000000000000 as TOTAL_TIME_SECONDS,
    AVG_TIMER_WAIT/1000000000000 as AVG_TIME_SECONDS,
    MAX_TIMER_WAIT/1000000000000 as MAX_TIME_SECONDS
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY COUNT_STAR DESC
LIMIT 20;

-- ============================================
-- STEP 9: CEK QUERY YANG PALING LAMBAT
-- ============================================
-- Catatan: Hanya berfungsi jika performance_schema enabled
SELECT 
    DIGEST_TEXT,
    COUNT_STAR as EXECUTION_COUNT,
    SUM_TIMER_WAIT/1000000000000 as TOTAL_TIME_SECONDS,
    AVG_TIMER_WAIT/1000000000000 as AVG_TIME_SECONDS,
    MAX_TIMER_WAIT/1000000000000 as MAX_TIME_SECONDS
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 20;

-- ============================================
-- STEP 10: KILL PROCESS YANG STUCK (HATI-HATI!)
-- ============================================
-- Hanya jalankan jika yakin process tersebut tidak penting
-- Ganti [PROCESS_ID] dengan ID dari STEP 1 atau STEP 2

-- Contoh:
-- KILL 123456;  -- Kill process dengan ID 123456
-- KILL QUERY 123456;  -- Hanya kill query, bukan connection

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Jangan kill process yang sedang membuat index/alter table
--    kecuali jika memang stuck (sudah berjalan > 10 menit)
-- 2. Process dengan TIME > 60 detik biasanya stuck
-- 3. Metadata lock biasanya terjadi karena:
--    - CREATE INDEX tanpa ALGORITHM=INPLACE
--    - ALTER TABLE yang memblokir
--    - Transaction yang lama tidak commit
-- 4. Jika banyak connection, cek:
--    - Queue workers yang tidak di-restart
--    - PHP-FPM processes yang tidak di-cleanup
--    - Application yang tidak menutup connection dengan benar
