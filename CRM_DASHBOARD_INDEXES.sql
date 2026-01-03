-- Indexes untuk optimasi CRM Dashboard
-- Jalankan satu per satu untuk menghindari timeout
-- Tabel: member_apps_members (92.166+ rows)

-- 1. Index untuk created_at (untuk getMemberGrowth, getLatestMembers)
-- Waktu: ~30-60 detik
CREATE INDEX idx_created_at ON member_apps_members(created_at);

-- 2. Index untuk last_login_at (untuk getChurnAnalysis, getMemberSegmentation)
-- Waktu: ~30-60 detik
CREATE INDEX idx_last_login_at ON member_apps_members(last_login_at);

-- 3. Index untuk email_verified_at (untuk getStats)
-- Waktu: ~30-60 detik
CREATE INDEX idx_email_verified_at ON member_apps_members(email_verified_at);

-- 4. Composite index untuk is_active + member_level + just_points (untuk getMemberSegmentation, getMemberLifetimeValue)
-- Waktu: ~60-120 detik (LAMBAT - composite index)
-- Catatan: Jika stuck, skip dulu dan buat index individual di bawah
CREATE INDEX idx_member_active_composite ON member_apps_members(is_active, member_level, just_points);

-- 5. Index untuk is_active saja (jika composite stuck, gunakan ini)
CREATE INDEX idx_is_active_only ON member_apps_members(is_active);

-- 6. Index untuk just_points (untuk filtering point)
CREATE INDEX idx_just_points ON member_apps_members(just_points);

-- Indexes untuk member_apps_point_transactions
-- 7. Index untuk created_at (untuk getLatestPointTransactions, getPointActivityTrend)
CREATE INDEX idx_point_transactions_created_at ON member_apps_point_transactions(created_at);

-- 8. Composite index untuk member_id + created_at (untuk getMostActiveMembers)
CREATE INDEX idx_point_transactions_member_created ON member_apps_point_transactions(member_id, created_at);

-- Indexes untuk orders (db_justus)
-- 9. Index untuk member_id + status (untuk getTopSpenders, getLatestMembers spending)
-- Catatan: Pastikan sudah ada index di database db_justus
-- CREATE INDEX idx_orders_member_status ON orders(member_id, status);

-- 10. Index untuk created_at + status (untuk getSpendingTrend, getComparisonData)
-- CREATE INDEX idx_orders_created_status ON orders(created_at, status);

-- 11. Index untuk kode_outlet (untuk getRegionalBreakdown)
-- CREATE INDEX idx_orders_kode_outlet ON orders(kode_outlet);

-- Catatan:
-- - Jalankan index satu per satu
-- - Tunggu sampai selesai sebelum menjalankan index berikutnya
-- - Jika stuck lebih dari 5 menit, cancel dan coba index berikutnya
-- - Composite index lebih lambat, buat di waktu maintenance jika perlu

