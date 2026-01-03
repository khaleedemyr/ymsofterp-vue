-- Index yang BELUM ADA - VERSI FIXED untuk MySQL 5.7.33
-- Syntax harus dalam SATU BARIS (tidak boleh ada line break)
-- Jalankan SATU PER SATU

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_members)
-- ============================================

-- Index 1: created_at (PENTING - untuk getMemberGrowth, getLatestMembers)
-- Copy-paste query ini dalam SATU BARIS (jangan ada line break!)
CREATE INDEX idx_created_at ON member_apps_members(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 2: last_login_at (PENTING - untuk getChurnAnalysis, getMemberSegmentation)
CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 3: email_verified_at (PENTING - untuk getStats email verification)
CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 4: just_points (PENTING - untuk getMemberSegmentation VIP members)
CREATE INDEX idx_just_points ON member_apps_members(just_points) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_point_transactions)
-- ============================================

-- Index 5: created_at (PENTING - untuk getLatestPointTransactions, getPointActivityTrend)
CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 6: member_id (PENTING - untuk getMostActiveMembers, getMemberSegmentation)
CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Copy-paste query dalam SATU BARIS (tidak boleh ada line break di tengah query)
-- 2. Jalankan index SATU PER SATU
-- 3. Tunggu sampai selesai (~30-60 detik per index)
-- 4. ALGORITHM=INPLACE: Membuat index tanpa copy tabel (lebih cepat)
-- 5. LOCK=NONE: Tidak memblokir query lain (SELECT/UPDATE tetap bisa jalan)

-- ============================================
-- ALTERNATIF: Jika masih error, gunakan ALTER TABLE
-- ============================================
-- ALTER TABLE member_apps_members ADD INDEX idx_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_last_login_at(last_login_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_email_verified_at(email_verified_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_just_points(just_points) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_member_id(member_id) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- OPSI TERAKHIR: Tanpa ALGORITHM (jika masih error)
-- ============================================
-- Buat di waktu maintenance (low traffic) karena akan lock tabel
-- CREATE INDEX idx_created_at ON member_apps_members(created_at);
-- CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at);
-- CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at);
-- CREATE INDEX idx_just_points ON member_apps_members(just_points);
-- CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at);
-- CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id);

