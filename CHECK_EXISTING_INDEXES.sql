-- Cek index yang sudah ada di tabel member_apps_members
SHOW INDEXES FROM member_apps_members;

-- Cek index yang sudah ada di tabel member_apps_point_transactions
SHOW INDEXES FROM member_apps_point_transactions;

-- ============================================
-- INDEX YANG SUDAH ADA (dari screenshot):
-- ============================================
-- ✅ unique_member_id (member_id)
-- ✅ idx_mobile_phone
-- ✅ idx_email
-- ✅ idx_member_level
-- ✅ idx_is_active
-- ✅ idx_pekerjaan_id
-- ✅ idx_member_id
-- ✅ unique_email
-- ✅ mobile_phone
-- ✅ unique_mobile_phone
-- ✅ email

-- ============================================
-- INDEX YANG BELUM ADA (PERLU DIBUAT):
-- ============================================
-- ❌ idx_created_at
-- ❌ idx_last_login_at
-- ❌ idx_email_verified_at
-- ❌ idx_just_points
-- ❌ idx_point_transactions_created_at (di tabel member_apps_point_transactions)
-- ❌ idx_point_transactions_member_id (di tabel member_apps_point_transactions)

