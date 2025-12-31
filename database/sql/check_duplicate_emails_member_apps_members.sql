-- Query untuk mengecek email yang duplicate di member_apps_members
-- Menampilkan email yang muncul lebih dari 1 kali beserta jumlahnya

SELECT 
    email,
    COUNT(*) as jumlah_duplicate,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as list_id,
    GROUP_CONCAT(nama_lengkap ORDER BY id SEPARATOR ', ') as list_nama
FROM member_apps_members
WHERE email IS NOT NULL 
    AND email != ''
GROUP BY email
HAVING COUNT(*) > 1
ORDER BY jumlah_duplicate DESC, email;

-- Query alternatif: Tampilkan detail lengkap untuk setiap email duplicate
-- Uncomment query di bawah jika ingin melihat detail lengkap

/*
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.nama_lengkap,
    m1.mobile_phone,
    m1.created_at,
    m1.updated_at
FROM member_apps_members m1
INNER JOIN (
    SELECT email
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
ORDER BY m1.email, m1.id;
*/

-- Query untuk menghitung total email duplicate
SELECT 
    COUNT(*) as total_email_duplicate,
    SUM(jumlah) as total_record_duplicate
FROM (
    SELECT 
        email,
        COUNT(*) as jumlah
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) as duplicates;

-- ============================================
-- QUERY UNTUK MENGHAPUS EMAIL DUPLICATE
-- ============================================
-- PENTING: Backup database dulu sebelum menjalankan query DELETE!
-- PENTING: Review query SELECT terlebih dahulu untuk melihat data yang akan dihapus!

-- OPSI 1: Hapus yang ID lebih besar (sisakan yang lebih lama/pertama dibuat)
-- Query ini akan menghapus record dengan ID lebih besar, menyisakan record dengan ID terkecil untuk setiap email

-- STEP 1: Preview data yang akan dihapus (JALANKAN INI DULU UNTUK REVIEW!)
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.nama_lengkap,
    m1.mobile_phone,
    m1.created_at,
    'AKAN DIHAPUS' as status
FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MIN(id) as min_id
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.id > m2.min_id  -- Hapus yang ID lebih besar (sisakan yang ID terkecil)
ORDER BY m1.email, m1.id;

-- STEP 2: Jika sudah yakin, jalankan query DELETE di bawah
-- UNCOMMENT query di bawah untuk menghapus duplicate (hapus yang ID lebih besar)

/*
DELETE m1 FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MIN(id) as min_id
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.id > m2.min_id;  -- Hapus yang ID lebih besar (sisakan yang ID terkecil)
*/

-- ============================================
-- OPSI 2: Hapus yang ID lebih kecil (sisakan yang lebih baru/terakhir dibuat) ⭐ RECOMMENDED
-- Query ini akan menghapus record dengan ID lebih kecil, menyisakan record dengan ID terbesar untuk setiap email
-- Artinya: Hapus data lama, sisakan data baru

-- STEP 1: Preview data yang akan dihapus (JALANKAN INI DULU UNTUK REVIEW!)
-- Query ini menampilkan semua record yang akan dihapus (ID lebih kecil dari MAX ID untuk setiap email)
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.nama_lengkap,
    m1.mobile_phone,
    m1.created_at,
    'AKAN DIHAPUS' as status,
    m2.max_id as 'ID yang akan DISISAKAN'
FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MAX(id) as max_id
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.id < m2.max_id  -- Hapus yang ID lebih kecil (sisakan yang ID terbesar/baru)
ORDER BY m1.email, m1.id;

-- STEP 2: Jika sudah yakin dengan preview di atas, jalankan query DELETE di bawah
-- PENTING: Backup database dulu sebelum menjalankan DELETE!
-- UNCOMMENT query di bawah untuk menghapus duplicate (hapus yang ID lebih kecil, sisakan yang ID terbesar)

/*
DELETE m1 FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MAX(id) as max_id
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.id < m2.max_id;  -- Hapus yang ID lebih kecil (sisakan yang ID terbesar/baru)
*/

-- ============================================
-- OPSI 3: Hapus berdasarkan created_at (sisakan yang paling lama/pertama dibuat)
-- Query ini akan menghapus record yang dibuat lebih baru, menyisakan record yang dibuat paling lama

-- STEP 1: Preview data yang akan dihapus (JALANKAN INI DULU UNTUK REVIEW!)
/*
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.nama_lengkap,
    m1.mobile_phone,
    m1.created_at,
    'AKAN DIHAPUS' as status
FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MIN(created_at) as min_created_at
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.created_at > m2.min_created_at  -- Hapus yang created_at lebih baru (sisakan yang paling lama)
ORDER BY m1.email, m1.created_at;
*/

-- STEP 2: Jika sudah yakin, jalankan query DELETE di bawah
-- UNCOMMENT query di bawah untuk menghapus duplicate (hapus yang created_at lebih baru)

/*
DELETE m1 FROM member_apps_members m1
INNER JOIN (
    SELECT 
        email,
        MIN(created_at) as min_created_at
    FROM member_apps_members
    WHERE email IS NOT NULL 
        AND email != ''
    GROUP BY email
    HAVING COUNT(*) > 1
) m2 ON m1.email = m2.email
WHERE m1.email IS NOT NULL 
    AND m1.email != ''
    AND m1.created_at > m2.min_created_at;  -- Hapus yang created_at lebih baru (sisakan yang paling lama)
*/

-- ============================================
-- QUERY UNTUK MOBILE_PHONE DUPLICATE
-- ============================================

-- Query untuk mengecek mobile_phone yang duplicate di member_apps_members
-- Menampilkan mobile_phone yang muncul lebih dari 1 kali beserta jumlahnya

SELECT 
    mobile_phone,
    COUNT(*) as jumlah_duplicate,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as list_id,
    GROUP_CONCAT(nama_lengkap ORDER BY id SEPARATOR ', ') as list_nama,
    GROUP_CONCAT(email ORDER BY id SEPARATOR ', ') as list_email
FROM member_apps_members
WHERE mobile_phone IS NOT NULL 
    AND mobile_phone != ''
    AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
GROUP BY mobile_phone
HAVING COUNT(*) > 1
ORDER BY jumlah_duplicate DESC, mobile_phone;

-- Query alternatif: Tampilkan detail lengkap untuk setiap mobile_phone duplicate
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.mobile_phone,
    m1.nama_lengkap,
    m1.created_at,
    m1.updated_at
FROM member_apps_members m1
INNER JOIN (
    SELECT mobile_phone
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) m2 ON m1.mobile_phone = m2.mobile_phone
WHERE m1.mobile_phone IS NOT NULL 
    AND m1.mobile_phone != ''
    AND m1.mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
ORDER BY m1.mobile_phone, m1.id;

-- Query untuk menghitung total mobile_phone duplicate
SELECT 
    COUNT(*) as total_mobile_phone_duplicate,
    SUM(jumlah) as total_record_duplicate
FROM (
    SELECT 
        mobile_phone,
        COUNT(*) as jumlah
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) as duplicates;

-- ============================================
-- QUERY UNTUK MENGHAPUS MOBILE_PHONE DUPLICATE
-- ============================================
-- PENTING: Backup database dulu sebelum menjalankan query DELETE!
-- PENTING: Review query SELECT terlebih dahulu untuk melihat data yang akan dihapus!

-- OPSI 1: Hapus yang ID lebih kecil (sisakan yang lebih baru/terakhir dibuat) ⭐ RECOMMENDED
-- Query ini akan menghapus record dengan ID lebih kecil, menyisakan record dengan ID terbesar untuk setiap mobile_phone
-- Artinya: Hapus data lama, sisakan data baru

-- STEP 1: Preview data yang akan dihapus (JALANKAN INI DULU UNTUK REVIEW!)
-- Query ini menampilkan semua record yang akan dihapus (ID lebih kecil dari MAX ID untuk setiap mobile_phone)
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.mobile_phone,
    m1.nama_lengkap,
    m1.created_at,
    'AKAN DIHAPUS' as status,
    m2.max_id as 'ID yang akan DISISAKAN'
FROM member_apps_members m1
INNER JOIN (
    SELECT 
        mobile_phone,
        MAX(id) as max_id
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) m2 ON m1.mobile_phone = m2.mobile_phone
WHERE m1.mobile_phone IS NOT NULL 
    AND m1.mobile_phone != ''
    AND m1.mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    AND m1.id < m2.max_id  -- Hapus yang ID lebih kecil (sisakan yang ID terbesar/baru)
ORDER BY m1.mobile_phone, m1.id;

-- STEP 2: Jika sudah yakin dengan preview di atas, jalankan query DELETE di bawah
-- PENTING: Backup database dulu sebelum menjalankan DELETE!
-- UNCOMMENT query di bawah untuk menghapus duplicate (hapus yang ID lebih kecil, sisakan yang ID terbesar)

/*
DELETE m1 FROM member_apps_members m1
INNER JOIN (
    SELECT 
        mobile_phone,
        MAX(id) as max_id
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) m2 ON m1.mobile_phone = m2.mobile_phone
WHERE m1.mobile_phone IS NOT NULL 
    AND m1.mobile_phone != ''
    AND m1.mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    AND m1.id < m2.max_id;  -- Hapus yang ID lebih kecil (sisakan yang ID terbesar/baru)
*/

-- ============================================
-- OPSI 2: Hapus yang ID lebih besar (sisakan yang lebih lama/pertama dibuat)
-- Query ini akan menghapus record dengan ID lebih besar, menyisakan record dengan ID terkecil untuk setiap mobile_phone

-- STEP 1: Preview data yang akan dihapus (JALANKAN INI DULU UNTUK REVIEW!)
/*
SELECT 
    m1.id,
    m1.member_id,
    m1.email,
    m1.mobile_phone,
    m1.nama_lengkap,
    m1.created_at,
    'AKAN DIHAPUS' as status,
    m2.min_id as 'ID yang akan DISISAKAN'
FROM member_apps_members m1
INNER JOIN (
    SELECT 
        mobile_phone,
        MIN(id) as min_id
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) m2 ON m1.mobile_phone = m2.mobile_phone
WHERE m1.mobile_phone IS NOT NULL 
    AND m1.mobile_phone != ''
    AND m1.mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    AND m1.id > m2.min_id  -- Hapus yang ID lebih besar (sisakan yang ID terkecil/lama)
ORDER BY m1.mobile_phone, m1.id;
*/

-- STEP 2: Jika sudah yakin, jalankan query DELETE di bawah
-- UNCOMMENT query di bawah untuk menghapus duplicate (hapus yang ID lebih besar)

/*
DELETE m1 FROM member_apps_members m1
INNER JOIN (
    SELECT 
        mobile_phone,
        MIN(id) as min_id
    FROM member_apps_members
    WHERE mobile_phone IS NOT NULL 
        AND mobile_phone != ''
        AND mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    GROUP BY mobile_phone
    HAVING COUNT(*) > 1
) m2 ON m1.mobile_phone = m2.mobile_phone
WHERE m1.mobile_phone IS NOT NULL 
    AND m1.mobile_phone != ''
    AND m1.mobile_phone != '0'  -- Kecualikan mobile_phone yang nilainya "0"
    AND m1.id > m2.min_id;  -- Hapus yang ID lebih besar (sisakan yang ID terkecil/lama)
*/
