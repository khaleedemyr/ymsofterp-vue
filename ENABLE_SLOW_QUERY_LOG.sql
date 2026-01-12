-- =====================================================
-- ENABLE SLOW QUERY LOG
-- =====================================================
-- Aktifkan slow query log untuk monitoring
-- =====================================================

-- 1. CEK STATUS SLOW QUERY LOG SAAT INI
-- =====================================================
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'slow_query_log_file';
SHOW VARIABLES LIKE 'long_query_time';
SHOW VARIABLES LIKE 'log_queries_not_using_indexes';

-- 2. ENABLE SLOW QUERY LOG
-- =====================================================
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log query yang > 1 detik
SET GLOBAL log_queries_not_using_indexes = 'ON'; -- Log query yang tidak pakai index
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log'; -- Sesuaikan path

-- 3. VERIFIKASI
-- =====================================================
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- 4. CEK APAKAH ADA DATA DI SLOW LOG
-- =====================================================
-- Jika menggunakan table (bukan file)
SELECT COUNT(*) as total_slow_queries FROM mysql.slow_log;

-- Jika menggunakan file, cek dengan:
-- tail -f /var/log/mysql/slow-query.log

-- =====================================================
-- CATATAN:
-- =====================================================
-- 1. Setelah enable, tunggu beberapa saat agar ada data
-- 2. Jalankan query yang lambat untuk test
-- 3. Cek slow log setelah beberapa menit
-- =====================================================
