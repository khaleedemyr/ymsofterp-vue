-- Fix Metadata Lock Issue
-- Masalah: CREATE INDEX memblokir semua query lain

-- ============================================
-- STEP 1: Kill process CREATE INDEX yang stuck
-- ============================================
-- Process ID 1214875 adalah yang membuat index
KILL 1214875;

-- ============================================
-- STEP 2: Tunggu beberapa detik, lalu cek lagi
-- ============================================
SHOW PROCESSLIST;

-- ============================================
-- STEP 3: Jika masih ada process yang menunggu, kill yang tidak penting
-- ============================================
-- Hanya kill jika memang tidak penting (jangan kill yang penting!)
-- KILL 1213560;  -- SELECT query
-- KILL 1213667;  -- SELECT query
-- KILL 1213830;  -- UPDATE query (hati-hati!)
-- KILL 1213909;  -- SELECT query
-- KILL 1214054;  -- SELECT query
-- KILL 1214286;  -- UPDATE query (hati-hati!)
-- KILL 1215266;  -- UPDATE query (hati-hati!)
-- KILL 1216446;  -- SELECT query
-- KILL 1216683;  -- SELECT query

-- ============================================
-- STEP 4: Setelah semua clear, buat index dengan ALGORITHM=INPLACE
-- ============================================
-- Ini akan membuat index tanpa memblokir query lain (jika MySQL versi 5.6+)
-- CREATE INDEX idx_created_at ON member_apps_members(created_at) 
-- ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Metadata lock terjadi karena CREATE INDEX memerlukan exclusive lock
-- 2. Semua query lain harus menunggu sampai CREATE INDEX selesai
-- 3. Untuk 92K+ rows, CREATE INDEX bisa memakan waktu 2-5 menit
-- 4. Solusi terbaik: Buat index di waktu maintenance (low traffic)
-- 5. Atau gunakan ALGORITHM=INPLACE, LOCK=NONE (jika MySQL 5.6+)

