-- Index yang BELUM ADA - VERSI ALTER TABLE (MySQL 5.7.33)
-- CREATE INDEX tidak support LOCK=NONE, jadi pakai ALTER TABLE
-- Copy-paste query ini SATU PER SATU dalam SATU BARIS

-- ============================================
-- INDEX 1: created_at
-- ============================================
ALTER TABLE member_apps_members ADD INDEX idx_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX 2: last_login_at
-- ============================================
ALTER TABLE member_apps_members ADD INDEX idx_last_login_at(last_login_at) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX 3: email_verified_at
-- ============================================
ALTER TABLE member_apps_members ADD INDEX idx_email_verified_at(email_verified_at) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX 4: just_points
-- ============================================
ALTER TABLE member_apps_members ADD INDEX idx_just_points(just_points) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX 5: point_transactions created_at
-- ============================================
ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- INDEX 6: point_transactions member_id
-- ============================================
ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_member_id(member_id) ALGORITHM=INPLACE, LOCK=NONE;

-- ============================================
-- ALTERNATIF: Jika masih error, gunakan ALTER TABLE
-- ============================================
-- ALTER TABLE member_apps_members ADD INDEX idx_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_last_login_at(last_login_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_email_verified_at(email_verified_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_members ADD INDEX idx_just_points(just_points) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_created_at(created_at) ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE member_apps_point_transactions ADD INDEX idx_point_transactions_member_id(member_id) ALGORITHM=INPLACE, LOCK=NONE;

