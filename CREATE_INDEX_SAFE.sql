-- Cara membuat index yang AMAN tanpa memblokir query lain
-- Gunakan ALGORITHM=INPLACE, LOCK=NONE untuk MySQL 5.6+

-- ============================================
-- STEP 1: Cek versi MySQL dulu
-- ============================================
SELECT VERSION();

-- Jika versi >= 5.6, bisa pakai ALGORITHM=INPLACE, LOCK=NONE
-- Jika versi < 5.6, harus tunggu waktu maintenance

-- ============================================
-- STEP 2: Buat index dengan ALGORITHM=INPLACE (jika MySQL 5.6+)
-- ============================================
-- Index 1: created_at
CREATE INDEX idx_created_at ON member_apps_members(created_at) 
ALGORITHM=INPLACE, LOCK=NONE;

-- Index 2: last_login_at
CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at) 
ALGORITHM=INPLACE, LOCK=NONE;

-- Index 3: email_verified_at
CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at) 
ALGORITHM=INPLACE, LOCK=NONE;

-- Index 4: just_points
CREATE INDEX idx_just_points ON member_apps_members(just_points) 
ALGORITHM=INPLACE, LOCK=NONE;

-- Index 5: point_transactions created_at
CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at) 
ALGORITHM=INPLACE, LOCK=NONE;

-- Index 6: point_transactions member_id
CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id) 
ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- CATATAN:
-- ============================================
-- ALGORITHM=INPLACE: Membuat index tanpa copy tabel (lebih cepat)
-- LOCK=NONE: Tidak memblokir query lain (bisa SELECT/UPDATE saat index dibuat)
-- 
-- Jika error "ALGORITHM=INPLACE is not supported", berarti MySQL versi < 5.6
-- Solusi: Buat index di waktu maintenance atau upgrade MySQL

