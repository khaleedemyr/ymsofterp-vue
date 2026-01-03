-- Indexes untuk optimasi CRM Dashboard - VERSI AMAN (Satu per satu, tanpa composite)
-- Jalankan satu per satu untuk menghindari timeout
-- Tabel: member_apps_members (92.166+ rows)

-- ============================================
-- STEP 1: Index untuk created_at
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getMemberGrowth, getLatestMembers, getConversionFunnel
CREATE INDEX idx_created_at ON member_apps_members(created_at);

-- ============================================
-- STEP 2: Index untuk last_login_at
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getChurnAnalysis, getMemberSegmentation, getEngagementMetrics
CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at);

-- ============================================
-- STEP 3: Index untuk email_verified_at
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getStats (email verification count)
CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at);

-- ============================================
-- STEP 4: Index untuk just_points
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getMemberSegmentation (VIP members filter)
CREATE INDEX idx_just_points ON member_apps_members(just_points);

-- ============================================
-- STEP 5: Index untuk member_apps_point_transactions - created_at
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getLatestPointTransactions, getPointActivityTrend
CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at);

-- ============================================
-- STEP 6: Index untuk member_apps_point_transactions - member_id
-- ============================================
-- Waktu estimasi: ~30-60 detik
-- Untuk: getMostActiveMembers, getMemberSegmentation (active members)
CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id);

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Jalankan index SATU PER SATU
-- 2. Tunggu sampai selesai (bisa 30-120 detik per index)
-- 3. Jangan cancel di tengah proses
-- 4. Composite index (multiple columns) lebih lambat - skip dulu jika tidak urgent
-- 5. Setelah semua index selesai, dashboard akan jauh lebih cepat

-- ============================================
-- OPSIONAL: Composite Index (JIKA PERLU)
-- ============================================
-- Hanya buat jika index individual sudah selesai dan masih perlu optimasi lebih
-- Waktu: ~2-5 menit (SANGAT LAMBAT untuk 92K+ rows)
-- 
-- CREATE INDEX idx_member_active_composite ON member_apps_members(is_active, member_level, just_points);
-- CREATE INDEX idx_point_transactions_member_created ON member_apps_point_transactions(member_id, created_at);

