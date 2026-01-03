-- Query untuk update email_verified_at yang masih NULL di table member_apps_members
-- Set email_verified_at ke timestamp saat ini untuk semua member yang email_verified_at masih NULL

-- Opsi 1: Update semua yang NULL ke timestamp saat ini
UPDATE member_apps_members 
SET email_verified_at = NOW() 
WHERE email_verified_at IS NULL;

-- Opsi 2: Update dengan created_at (jika ingin menggunakan tanggal registrasi)
-- UPDATE member_apps_members 
-- SET email_verified_at = created_at 
-- WHERE email_verified_at IS NULL;

-- Opsi 3: Update dengan tanggal tertentu (contoh: 2024-01-01)
-- UPDATE member_apps_members 
-- SET email_verified_at = '2024-01-01 00:00:00' 
-- WHERE email_verified_at IS NULL;

-- Cek jumlah yang akan di-update (sebelum update)
-- SELECT COUNT(*) as total_null 
-- FROM member_apps_members 
-- WHERE email_verified_at IS NULL;

-- Cek hasil setelah update
-- SELECT COUNT(*) as total_verified 
-- FROM member_apps_members 
-- WHERE email_verified_at IS NOT NULL;

