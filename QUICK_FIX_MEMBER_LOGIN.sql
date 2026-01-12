-- =====================================================
-- QUICK FIX: MEMBER LOGIN SLOW QUERY
-- =====================================================
-- Masalah: Query login full table scan pada 92rb+ data
-- Query: WHERE email = ? OR mobile_phone = ?
-- 
-- Solusi: Tambahkan index untuk email dan mobile_phone
-- =====================================================

-- CEK INDEX YANG SUDAH ADA
SHOW INDEX FROM member_apps_members;

-- TAMBAHKAN INDEX YANG DIPERLUKAN
-- =====================================================

-- 1. Index untuk EMAIL (PRIORITAS TINGGI - untuk login)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_email 
ON member_apps_members(email);

-- 2. Index untuk MOBILE_PHONE (PRIORITAS TINGGI - untuk login)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_phone 
ON member_apps_members(mobile_phone);

-- 3. Index untuk MEMBER_ID (sering digunakan di query lain)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_member_id 
ON member_apps_members(member_id);

-- 4. Index untuk IS_ACTIVE (filter setelah login)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_is_active 
ON member_apps_members(is_active);

-- VERIFIKASI INDEX SETELAH DIBUAT
SHOW INDEX FROM member_apps_members;

-- TEST QUERY (Jalankan EXPLAIN untuk verifikasi)
-- =====================================================
-- Ganti 'test@example.com' dengan email/mobile_phone yang ada di database
EXPLAIN SELECT * FROM member_apps_members 
WHERE email = 'test@example.com' 
   OR mobile_phone = 'test@example.com' 
LIMIT 1;

-- HASIL YANG DIHARAPKAN:
-- - type: ref atau range (BUKAN 'ALL')
-- - key: idx_member_apps_members_email atau idx_member_apps_members_mobile_phone
-- - rows: 1 atau sangat kecil (BUKAN 92000+)
