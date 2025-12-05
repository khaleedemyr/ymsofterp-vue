-- =====================================================
-- QUERY YANG BENAR UNTUK USER SEARCH
-- =====================================================

-- 1. Cek struktur tabel yang benar
DESCRIBE users;
DESCRIBE tbl_data_divisi;
DESCRIBE tbl_data_jabatan;

-- 2. Query yang benar dengan JOIN
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
LIMIT 10;

-- 3. Cek data yang tersedia
SELECT 
    COUNT(*) as total_active_users,
    COUNT(u.nama_lengkap) as users_with_nama,
    COUNT(u.email) as users_with_email,
    COUNT(d.nama_divisi) as users_with_divisi,
    COUNT(j.nama_jabatan) as users_with_jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A';

-- 4. Sample data untuk testing
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (u.nama_lengkap IS NOT NULL OR u.email IS NOT NULL)
LIMIT 5;

-- 5. Test search dengan query yang benar
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (
    u.nama_lengkap LIKE '%test%'
    OR u.email LIKE '%test%'
    OR d.nama_divisi LIKE '%test%'
    OR j.nama_jabatan LIKE '%test%'
  )
  AND u.id != 1  -- ganti dengan ID user yang sedang login
LIMIT 10;

-- 6. Test search dengan kata yang lebih umum
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (
    u.nama_lengkap LIKE '%a%'
    OR u.email LIKE '%a%'
    OR d.nama_divisi LIKE '%a%'
    OR j.nama_jabatan LIKE '%a%'
  )
LIMIT 10;

-- 7. Cek divisi yang tersedia
SELECT id, nama_divisi FROM tbl_data_divisi LIMIT 10;

-- 8. Cek jabatan yang tersedia
SELECT id_jabatan, nama_jabatan FROM tbl_data_jabatan LIMIT 10;

-- 9. Test search berdasarkan divisi
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND d.nama_divisi LIKE '%IT%'  -- ganti dengan nama divisi yang ada
LIMIT 10;

-- 10. Test search berdasarkan jabatan
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND j.nama_jabatan LIKE '%Manager%'  -- ganti dengan nama jabatan yang ada
LIMIT 10;

-- 11. Query yang sama persis dengan yang akan digunakan di controller
-- (ganti 'test' dengan kata kunci yang ingin dicari)
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (
    u.nama_lengkap LIKE '%test%'
    OR u.email LIKE '%test%'
    OR d.nama_divisi LIKE '%test%'
    OR j.nama_jabatan LIKE '%test%'
  )
  AND u.id != 1  -- ganti dengan ID user yang sedang login
LIMIT 10;

-- 12. Cek user yang sedang login
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.id = 1;  -- ganti dengan ID user yang sedang login

-- 13. Debug - cek user yang tidak memiliki divisi atau jabatan
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    u.division_id,
    u.id_jabatan,
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (d.nama_divisi IS NULL OR j.nama_jabatan IS NULL)
LIMIT 5;

-- 14. Cek total user yang aktif dan bisa di-search
SELECT COUNT(*) as total_searchable_users
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (u.nama_lengkap IS NOT NULL OR u.email IS NOT NULL);

-- 15. Test dengan case insensitive search
SELECT 
    u.id, 
    u.nama_lengkap, 
    u.email, 
    d.nama_divisi as divisi,
    j.nama_jabatan as jabatan
FROM users u
LEFT JOIN tbl_data_divisi d ON u.division_id = d.id
LEFT JOIN tbl_data_jabatan j ON u.id_jabatan = j.id_jabatan
WHERE u.status = 'A'
  AND (
    LOWER(u.nama_lengkap) LIKE LOWER('%test%')
    OR LOWER(u.email) LIKE LOWER('%test%')
    OR LOWER(d.nama_divisi) LIKE LOWER('%test%')
    OR LOWER(j.nama_jabatan) LIKE LOWER('%test%')
  )
  AND u.id != 1
LIMIT 10; 