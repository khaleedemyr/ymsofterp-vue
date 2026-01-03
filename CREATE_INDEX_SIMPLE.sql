-- Index yang BELUM ADA - VERSI SEDERHANA (tanpa ALGORITHM/LOCK)
-- MySQL 5.7.33 Ubuntu - Buat di waktu MAINTENANCE (akan lock tabel)
-- Jalankan SATU PER SATU

-- ============================================
-- PENTING: Buat index ini di waktu MAINTENANCE (low traffic)
-- Karena akan lock tabel dan memblokir query lain
-- ============================================

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_members)
-- ============================================

-- Index 1: created_at (PENTING - untuk getMemberGrowth, getLatestMembers)
-- Waktu: ~30-60 detik untuk 92K+ rows
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
-- 1. Query ini akan LOCK tabel saat membuat index
-- 2. Semua query lain akan menunggu sampai index selesai
-- 3. Buat di waktu MAINTENANCE (low traffic) untuk menghindari stuck
-- 4. Jalankan index SATU PER SATU
-- 5. Tunggu sampai selesai sebelum lanjut ke index berikutnya
-- 6. Setiap index membutuhkan waktu ~30-120 detik untuk 92K+ rows

-- ============================================
-- ALTERNATIF: Cek apakah bisa pakai CREATE INDEX biasa
-- ============================================
-- CREATE INDEX idx_created_at ON member_apps_members(created_at);
-- CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at);
-- CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at);
-- CREATE INDEX idx_just_points ON member_apps_members(just_points);
-- CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at);
-- CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id);

