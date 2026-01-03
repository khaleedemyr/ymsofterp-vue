-- Index yang BELUM ADA dan PERLU dibuat untuk optimasi CRM Dashboard
-- Jalankan SATU PER SATU dengan ALGORITHM=INPLACE untuk menghindari stuck

-- ============================================
-- CEK VERSI MYSQL DULU (PENTING!)
-- ============================================
SELECT VERSION();
-- Jika versi >= 5.6, bisa pakai ALGORITHM=INPLACE, LOCK=NONE
-- Jika versi < 5.6, jangan pakai ALGORITHM (akan error)

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_members)
-- ============================================

-- Index 1: created_at (PENTING - untuk getMemberGrowth, getLatestMembers)
-- Waktu: ~30-60 detik untuk 92K+ rows
-- PENTING: Copy-paste query ini dalam SATU BARIS (tidak boleh ada line break!)
CREATE INDEX idx_created_at ON member_apps_members(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 2: last_login_at (PENTING - untuk getChurnAnalysis, getMemberSegmentation)
-- Waktu: ~30-60 detik
CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 3: email_verified_at (PENTING - untuk getStats email verification)
-- Waktu: ~30-60 detik
CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 4: just_points (PENTING - untuk getMemberSegmentation VIP members)
-- Waktu: ~30-60 detik
CREATE INDEX idx_just_points ON member_apps_members(just_points) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX YANG BELUM ADA (Tabel: member_apps_point_transactions)
-- ============================================

-- Index 5: created_at (PENTING - untuk getLatestPointTransactions, getPointActivityTrend)
-- Waktu: ~30-60 detik
CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- Index 6: member_id (PENTING - untuk getMostActiveMembers, getMemberSegmentation)
-- Waktu: ~30-60 detik
CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Jalankan index SATU PER SATU (jangan sekaligus!)
-- 2. Tunggu sampai selesai sebelum lanjut ke index berikutnya
-- 3. ALGORITHM=INPLACE: Membuat index tanpa copy tabel (lebih cepat)
-- 4. LOCK=NONE: Tidak memblokir query lain (SELECT/UPDATE tetap bisa jalan)
-- 5. Jika error "ALGORITHM=INPLACE is not supported", berarti MySQL < 5.6
--    Solusi: Hapus "ALGORITHM=INPLACE, LOCK=NONE" dan buat di waktu maintenance

-- ============================================
-- JIKA MYSQL VERSI < 5.6 (TIDAK SUPPORT ALGORITHM=INPLACE)
-- ============================================
-- Gunakan query ini (tanpa ALGORITHM) - HARUS di waktu maintenance:
-- 
-- CREATE INDEX idx_created_at ON member_apps_members(created_at);
-- CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at);
-- CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at);
-- CREATE INDEX idx_just_points ON member_apps_members(just_points);
-- CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at);
-- CREATE INDEX idx_point_transactions_member_id ON member_apps_point_transactions(member_id);

