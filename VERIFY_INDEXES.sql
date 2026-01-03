-- Verifikasi index yang sudah dibuat
-- Jalankan query ini untuk memastikan semua index sudah ada

-- ============================================
-- Cek index di tabel member_apps_members
-- ============================================
SHOW INDEXES FROM member_apps_members WHERE Key_name IN (
    'idx_created_at',
    'idx_last_login_at',
    'idx_email_verified_at',
    'idx_just_points'
);

-- ============================================
-- Cek index di tabel member_apps_point_transactions
-- ============================================
SHOW INDEXES FROM member_apps_point_transactions WHERE Key_name IN (
    'idx_point_transactions_created_at',
    'idx_point_transactions_member_id'
);

-- ============================================
-- Cek semua index yang ada (untuk referensi)
-- ============================================
-- SHOW INDEXES FROM member_apps_members;
-- SHOW INDEXES FROM member_apps_point_transactions;

