-- =====================================================
-- TEST QUERIES FINAL - SAMA PERSIS DENGAN CONTROLLER
-- =====================================================

-- 1. Test query yang sama persis dengan controller searchUsers
-- (Ganti 'test' dengan kata kunci yang ingin dicari, ganti 1 dengan ID user yang sedang login)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND (
    users.nama_lengkap LIKE '%test%'
    OR users.email LIKE '%test%'
    OR tbl_data_divisi.nama_divisi LIKE '%test%'
    OR tbl_data_jabatan.nama_jabatan LIKE '%test%'
  )
  AND users.id != 1
LIMIT 10;

-- 2. Test dengan kata yang lebih umum (huruf 'a')
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND (
    users.nama_lengkap LIKE '%a%'
    OR users.email LIKE '%a%'
    OR tbl_data_divisi.nama_divisi LIKE '%a%'
    OR tbl_data_jabatan.nama_jabatan LIKE '%a%'
  )
  AND users.id != 1
LIMIT 10;

-- 3. Test query yang sama persis dengan controller getDropdownData
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND users.id != 1
LIMIT 10;

-- 4. Cek apakah ada data di tabel divisi
SELECT COUNT(*) as total_divisi FROM tbl_data_divisi;

-- 5. Cek apakah ada data di tabel jabatan
SELECT COUNT(*) as total_jabatan FROM tbl_data_jabatan;

-- 6. Cek user yang aktif
SELECT COUNT(*) as total_active_users FROM users WHERE status = 'A';

-- 7. Cek user yang memiliki nama_lengkap
SELECT COUNT(*) as users_with_nama FROM users WHERE status = 'A' AND nama_lengkap IS NOT NULL;

-- 8. Cek user yang memiliki email
SELECT COUNT(*) as users_with_email FROM users WHERE status = 'A' AND email IS NOT NULL;

-- 9. Test search berdasarkan nama (ganti 'john' dengan nama yang ada)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND users.nama_lengkap LIKE '%john%'
  AND users.id != 1
LIMIT 10;

-- 10. Test search berdasarkan email (ganti 'test' dengan email yang ada)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND users.email LIKE '%test%'
  AND users.id != 1
LIMIT 10;

-- 11. Test search berdasarkan divisi (ganti 'IT' dengan nama divisi yang ada)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND tbl_data_divisi.nama_divisi LIKE '%IT%'
  AND users.id != 1
LIMIT 10;

-- 12. Test search berdasarkan jabatan (ganti 'Manager' dengan nama jabatan yang ada)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND tbl_data_jabatan.nama_jabatan LIKE '%Manager%'
  AND users.id != 1
LIMIT 10;

-- 13. Debug - cek user yang tidak memiliki divisi atau jabatan
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    users.division_id,
    users.id_jabatan,
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND (tbl_data_divisi.nama_divisi IS NULL OR tbl_data_jabatan.nama_jabatan IS NULL)
LIMIT 5;

-- 14. Cek sample data lengkap untuk testing
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND users.nama_lengkap IS NOT NULL
  AND users.email IS NOT NULL
LIMIT 5;

-- 15. Test tanpa exclude current user (untuk debugging)
SELECT 
    users.id, 
    users.nama_lengkap, 
    users.email, 
    tbl_data_divisi.nama_divisi as divisi,
    tbl_data_jabatan.nama_jabatan as jabatan
FROM users
LEFT JOIN tbl_data_divisi ON users.division_id = tbl_data_divisi.id
LEFT JOIN tbl_data_jabatan ON users.id_jabatan = tbl_data_jabatan.id_jabatan
WHERE users.status = 'A'
  AND (
    users.nama_lengkap LIKE '%a%'
    OR users.email LIKE '%a%'
    OR tbl_data_divisi.nama_divisi LIKE '%a%'
    OR tbl_data_jabatan.nama_jabatan LIKE '%a%'
  )
LIMIT 10; 