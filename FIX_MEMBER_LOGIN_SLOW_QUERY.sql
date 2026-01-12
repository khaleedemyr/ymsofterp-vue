-- =====================================================
-- FIX SLOW QUERY: MEMBER LOGIN (member_apps_members)
-- =====================================================
-- Masalah: Query login menggunakan orWhere yang menyebabkan full table scan
-- Query yang bermasalah:
--   MemberAppsMember::where('email', $email)->orWhere('mobile_phone', $email)->first()
-- 
-- Dengan 92rb+ data, ini akan sangat lambat!
-- =====================================================

-- 1. CEK INDEX YANG SUDAH ADA
-- =====================================================
SHOW INDEX FROM member_apps_members;

-- 2. CEK STRUKTUR TABEL
-- =====================================================
DESCRIBE member_apps_members;

-- 3. ANALISA QUERY LOGIN (Jalankan EXPLAIN)
-- =====================================================
-- Simulasi query login dengan email
EXPLAIN SELECT * FROM member_apps_members 
WHERE email = 'test@example.com' 
   OR mobile_phone = 'test@example.com' 
LIMIT 1;

-- Simulasi query login dengan mobile_phone
EXPLAIN SELECT * FROM member_apps_members 
WHERE email = '081234567890' 
   OR mobile_phone = '081234567890' 
LIMIT 1;

-- 4. BUAT INDEX YANG DIPERLUKAN
-- =====================================================
-- Index untuk email (sangat penting untuk login)
-- Email harus UNIQUE karena digunakan untuk login
CREATE INDEX IF NOT EXISTS idx_member_apps_members_email 
ON member_apps_members(email);

-- Index untuk mobile_phone (sangat penting untuk login)
-- Mobile phone juga digunakan untuk login
CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_phone 
ON member_apps_members(mobile_phone);

-- Index untuk member_id (sering digunakan di query lain)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_member_id 
ON member_apps_members(member_id);

-- Index untuk is_active (sering digunakan untuk filter)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_is_active 
ON member_apps_members(is_active);

-- Composite index untuk query login yang lebih optimal
-- (email, is_active) - untuk query login dengan email
CREATE INDEX IF NOT EXISTS idx_member_apps_members_email_active 
ON member_apps_members(email, is_active);

-- (mobile_phone, is_active) - untuk query login dengan mobile_phone
CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_active 
ON member_apps_members(mobile_phone, is_active);

-- Index untuk email_verified_at (diperiksa setelah login)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_email_verified 
ON member_apps_members(email_verified_at);

-- 5. VERIFIKASI INDEX SETELAH DIBUAT
-- =====================================================
SHOW INDEX FROM member_apps_members;

-- 6. TEST QUERY SETELAH INDEX (Jalankan EXPLAIN lagi)
-- =====================================================
-- Query dengan email
EXPLAIN SELECT * FROM member_apps_members 
WHERE email = 'test@example.com' 
   OR mobile_phone = 'test@example.com' 
LIMIT 1;

-- Query dengan mobile_phone
EXPLAIN SELECT * FROM member_apps_members 
WHERE email = '081234567890' 
   OR mobile_phone = '081234567890' 
LIMIT 1;

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Query login saat ini menggunakan OR yang tidak optimal
--    Solusi terbaik: Ubah query menjadi 2 query terpisah
--    atau gunakan UNION
--
-- 2. Query yang lebih optimal:
--    SELECT * FROM member_apps_members 
--    WHERE email = ? AND is_active = 1
--    UNION
--    SELECT * FROM member_apps_members 
--    WHERE mobile_phone = ? AND is_active = 1
--    LIMIT 1;
--
-- 3. Atau lebih baik lagi, pisahkan logic:
--    - Coba cari dengan email dulu
--    - Jika tidak ketemu, baru cari dengan mobile_phone
--
-- 4. Index akan membantu, tapi query structure juga perlu dioptimasi
-- =====================================================
