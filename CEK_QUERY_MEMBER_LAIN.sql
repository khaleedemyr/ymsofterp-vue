-- =====================================================
-- CEK QUERY LAIN YANG MUNGKIN LAMBAT DI MEMBER APP
-- =====================================================
-- Query login sudah optimal (index_merge, 2 rows)
-- Tapi mungkin ada query lain yang masih lambat
-- =====================================================

-- 1. CEK QUERY REGISTER (yang mengambil semua member)
-- =====================================================
-- Query ini di register() baris 65-68:
-- SELECT id, mobile_phone FROM member_apps_members 
-- WHERE mobile_phone IS NOT NULL AND mobile_phone != ''
-- 
-- Ini akan scan semua 92rb+ data!
EXPLAIN SELECT id, mobile_phone 
FROM member_apps_members 
WHERE mobile_phone IS NOT NULL 
  AND mobile_phone != '';

-- 2. CEK QUERY DENGAN MEMBER_ID
-- =====================================================
-- Banyak query menggunakan member_id untuk lookup
EXPLAIN SELECT * FROM member_apps_members 
WHERE member_id = 'MEMBER001';

-- 3. CEK QUERY DENGAN IS_ACTIVE
-- =====================================================
-- Filter is_active sering digunakan
EXPLAIN SELECT * FROM member_apps_members 
WHERE is_active = 1;

-- 4. CEK QUERY DENGAN EMAIL_VERIFIED_AT
-- =====================================================
EXPLAIN SELECT * FROM member_apps_members 
WHERE email_verified_at IS NOT NULL;

-- 5. CEK QUERY JOIN DENGAN TABEL LAIN
-- =====================================================
-- Cek apakah ada query yang join dengan member_apps_members
-- tanpa index yang tepat

-- 6. CEK SLOW QUERY LOG UNTUK MEMBER_APPS_MEMBERS
-- =====================================================
-- Cek query yang masih lambat terkait member_apps_members
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

-- 7. CEK APAKAH ADA QUERY YANG SELECT * TANPA WHERE
-- =====================================================
-- Query seperti ini akan sangat lambat:
-- SELECT * FROM member_apps_members (tanpa WHERE clause)

-- 8. CEK QUERY DENGAN LIKE PADA EMAIL/MOBILE_PHONE
-- =====================================================
-- Query dengan LIKE '%...%' tidak bisa menggunakan index
EXPLAIN SELECT * FROM member_apps_members 
WHERE email LIKE '%@gmail.com%';

-- 9. CEK QUERY DENGAN ORDER BY TANPA INDEX
-- =====================================================
-- ORDER BY tanpa index akan lambat
EXPLAIN SELECT * FROM member_apps_members 
ORDER BY created_at DESC 
LIMIT 10;

-- 10. CEK QUERY COUNT(*) TANPA WHERE
-- =====================================================
-- COUNT(*) tanpa WHERE akan scan semua data
EXPLAIN SELECT COUNT(*) FROM member_apps_members;

-- =====================================================
-- REKOMENDASI INDEX TAMBAHAN (jika diperlukan)
-- =====================================================

-- Index untuk created_at (jika sering ORDER BY created_at)
-- CREATE INDEX IF NOT EXISTS idx_member_apps_members_created_at 
-- ON member_apps_members(created_at);

-- Index untuk composite (mobile_phone, is_active) jika sering digunakan bersama
-- CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_active 
-- ON member_apps_members(mobile_phone, is_active);
