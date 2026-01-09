-- ============================================
-- FIX MEMBER LOGIN SLOW QUERY
-- Tambah Generated Column dan Index untuk Optimasi Login Member App
-- ============================================

USE db_justus;

-- ============================================
-- 1. Tambah Generated Column untuk email_normalized
-- ============================================

-- Check apakah column sudah ada
SELECT COUNT(*) as column_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'db_justus' 
AND TABLE_NAME = 'member_apps_members' 
AND COLUMN_NAME = 'email_normalized';

-- Jika belum ada, tambah generated column
ALTER TABLE member_apps_members 
ADD COLUMN email_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_email_normalized ON member_apps_members(email_normalized);

-- ============================================
-- 2. Tambah Generated Column untuk member_id_normalized
-- ============================================

-- Check apakah column sudah ada
SELECT COUNT(*) as column_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'db_justus' 
AND TABLE_NAME = 'member_apps_members' 
AND COLUMN_NAME = 'member_id_normalized';

-- Jika belum ada, tambah generated column
ALTER TABLE member_apps_members 
ADD COLUMN member_id_normalized VARCHAR(255) 
GENERATED ALWAYS AS (LOWER(TRIM(member_id))) STORED;

-- Tambah index di generated column
CREATE INDEX idx_member_id_normalized ON member_apps_members(member_id_normalized);

-- ============================================
-- 3. Verifikasi
-- ============================================

-- Check columns
SHOW COLUMNS FROM member_apps_members LIKE '%_normalized';

-- Check indexes
SHOW INDEXES FROM member_apps_members WHERE Key_name LIKE '%_normalized';

-- Test query dengan EXPLAIN
EXPLAIN SELECT * FROM member_apps_members 
WHERE email_normalized = 'test@example.com' 
LIMIT 1;

EXPLAIN SELECT * FROM member_apps_members 
WHERE member_id_normalized = 'u10729' 
LIMIT 1;

-- ============================================
-- SELESAI!
-- ============================================
-- Expected results:
-- - type = 'ref' (pakai index)
-- - key = 'idx_email_normalized' atau 'idx_member_id_normalized'
-- - rows = 1 (bukan 92,555 atau 64,742)
-- ============================================
