-- =====================================================
-- CEK QUERY purchase_order_foods YANG LAMBAT
-- =====================================================
-- Query yang terdeteksi: 24 detik (sangat lambat!)
-- =====================================================

-- 1. CEK QUERY LENGKAP DARI PROCESS YANG RUNNING
-- =====================================================
-- Ganti 3478641 dengan process ID yang terlihat di dashboard
SELECT 
    id,
    user,
    db,
    command,
    time,
    state,
    info as full_query
FROM information_schema.processlist
WHERE id = 3478641;

-- Atau cek semua process yang running lama
SELECT 
    id,
    user,
    db,
    command,
    time,
    state,
    LEFT(info, 200) as query_preview
FROM information_schema.processlist
WHERE command != 'Sleep'
  AND time > 5
ORDER BY time DESC;

-- 2. CEK STRUKTUR TABEL purchase_order_foods
-- =====================================================
DESCRIBE purchase_order_foods;

-- 3. CEK INDEX YANG ADA
-- =====================================================
SHOW INDEX FROM purchase_order_foods;

-- 4. CEK JUMLAH RECORDS
-- =====================================================
SELECT COUNT(*) as total_records FROM purchase_order_foods;

-- 5. CEK QUERY DI SLOW QUERY LOG
-- =====================================================
SELECT 
    sql_text,
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    created_at
FROM mysql.slow_log 
WHERE sql_text LIKE '%purchase_order_foods%'
  AND sql_text LIKE '%DISTINCT%'
ORDER BY query_time DESC
LIMIT 10;

-- 6. CEK QUERY YANG SERING DIPANGGIL
-- =====================================================
SELECT 
    sql_text,
    COUNT(*) as call_count,
    AVG(query_time) as avg_query_time,
    MAX(query_time) as max_query_time,
    AVG(rows_examined) as avg_rows_examined
FROM mysql.slow_log 
WHERE sql_text LIKE '%purchase_order_foods%'
GROUP BY sql_text
ORDER BY call_count DESC, avg_query_time DESC
LIMIT 10;

-- 7. CEK FOREIGN KEY DAN RELASI
-- =====================================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'purchase_order_foods'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 8. CEK APAKAH ADA INDEX DI FOREIGN KEY
-- =====================================================
-- Setelah dapat foreign key dari query di atas,
-- cek apakah ada index di kolom tersebut
-- Contoh:
-- SHOW INDEX FROM purchase_order_foods WHERE Column_name = 'xxx';

-- =====================================================
-- REKOMENDASI INDEX (jika diperlukan)
-- =====================================================
-- Setelah analisa query lengkap, tambahkan index:
-- 
-- Contoh (sesuaikan dengan query aktual):
-- CREATE INDEX idx_purchase_order_foods_status 
-- ON purchase_order_foods(status);
-- 
-- CREATE INDEX idx_purchase_order_foods_created_at 
-- ON purchase_order_foods(created_at);
-- 
-- CREATE INDEX idx_purchase_order_foods_outlet_id 
-- ON purchase_order_foods(outlet_id);
