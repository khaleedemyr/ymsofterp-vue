-- Index yang BELUM ADA - Menggunakan ALTER TABLE (MySQL 5.7.33)
-- CREATE INDEX tidak support LOCK=NONE, jadi pakai ALTER TABLE
-- Jalankan SATU PER SATU

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_members)
-- ============================================

-- Index 1: created_at (PENTING - untuk getMemberGrowth, getLatestMembers)
-- Waktu: ~30-60 detik untuk 92K+ rows
-- CATATAN: MySQL 5.7.33 Ubuntu tidak support ALGORITHM=INPLACE, LOCK=NONE
-- Gunakan query sederhana ini (akan lock tabel - buat di waktu maintenance)
ALTER TABLE member_apps_members ADD INDEX idx_created_at(created_at);

-- Index 2: last_login_at (PENTING - untuk getChurnAnalysis, getMemberSegmentation)
-- Waktu: ~30-60 detik
ALTER TABLE member_apps_members ADD INDEX idx_last_login_at(last_login_at);

-- Index 3: email_verified_at (PENTING - untuk getStats email verification)
-- Waktu: ~30-60 detik
ALTER TABLE member_apps_members ADD INDEX idx_email_verified_at(email_verified_at);

-- Index 4: just_points (PENTING - untuk getMemberSegmentation VIP members)
-- Waktu: ~30-60 detik
ALTER TABLE member_apps_members ADD INDEX idx_just_points(just_points);

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_point_transactions)
-- ============================================

-- Index 5: created_at (PENTING - untuk getLatestPointTransactions, getPointActivityTrend)
-- Waktu: ~30-60 detik
ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_created_at(created_at);

-- Index 6: member_id (PENTING - untuk getMostActiveMembers, getMemberSegmentation)
-- Waktu: ~30-60 detik
ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_member_id(member_id);

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. MySQL 5.7.33 Ubuntu: Tidak support ALGORITHM=INPLACE, LOCK=NONE untuk ADD INDEX
-- 2. Query ini akan LOCK tabel saat membuat index
-- 3. Semua query lain akan menunggu sampai index selesai
-- 4. BUAT DI WAKTU MAINTENANCE (low traffic) untuk menghindari stuck
-- 5. Jalankan index SATU PER SATU
-- 6. Tunggu sampai selesai (~30-120 detik per index) sebelum lanjut
-- 7. Setelah semua index selesai, dashboard akan jauh lebih cepat

