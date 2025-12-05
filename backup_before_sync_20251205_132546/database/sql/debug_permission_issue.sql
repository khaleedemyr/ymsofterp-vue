-- =====================================================
-- DEBUG PERMISSION ISSUE - 403 ERROR
-- =====================================================

-- 1. Cek data permission yang ada
SELECT 
    dp.id,
    dp.document_id,
    dp.user_id,
    dp.permission,
    dp.created_at,
    dp.updated_at,
    u.nama_lengkap as user_name,
    u.email as user_email,
    sd.title as document_title
FROM document_permissions dp
LEFT JOIN users u ON dp.user_id = u.id
LEFT JOIN shared_documents sd ON dp.document_id = sd.id
ORDER BY dp.document_id, dp.user_id;

-- 2. Cek user yang sedang login (ganti 2 dengan ID user yang sedang login)
SELECT 
    id,
    nama_lengkap,
    email,
    status
FROM users 
WHERE id = 2;  -- ganti dengan ID user yang sedang login

-- 3. Cek apakah user memiliki permission untuk document tertentu
-- (ganti 1 dengan document_id, ganti 2 dengan user_id yang sedang login)
SELECT 
    dp.id,
    dp.document_id,
    dp.user_id,
    dp.permission,
    u.nama_lengkap as user_name,
    u.email as user_email,
    sd.title as document_title,
    sd.created_by as document_creator,
    sd.is_public
FROM document_permissions dp
LEFT JOIN users u ON dp.user_id = u.id
LEFT JOIN shared_documents sd ON dp.document_id = sd.id
WHERE dp.document_id = 1  -- ganti dengan document_id yang diakses
  AND dp.user_id = 2;     -- ganti dengan user_id yang sedang login

-- 4. Cek semua permission untuk document tertentu
SELECT 
    dp.id,
    dp.document_id,
    dp.user_id,
    dp.permission,
    u.nama_lengkap as user_name,
    u.email as user_email,
    u.status as user_status
FROM document_permissions dp
LEFT JOIN users u ON dp.user_id = u.id
WHERE dp.document_id = 1  -- ganti dengan document_id yang diakses
ORDER BY dp.user_id;

-- 5. Cek document info
SELECT 
    id,
    title,
    created_by,
    is_public,
    created_at
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id yang diakses

-- 6. Cek apakah user adalah creator document
SELECT 
    sd.id as document_id,
    sd.title,
    sd.created_by as creator_id,
    u.nama_lengkap as creator_name,
    u.email as creator_email
FROM shared_documents sd
LEFT JOIN users u ON sd.created_by = u.id
WHERE sd.id = 1;  -- ganti dengan document_id yang diakses

-- 7. Debug - cek semua kondisi permission
-- (ganti 1 dengan document_id, ganti 2 dengan user_id yang sedang login)
SELECT 
    'Document Info' as info_type,
    sd.id as document_id,
    sd.title,
    sd.is_public,
    sd.created_by as creator_id
FROM shared_documents sd
WHERE sd.id = 1
UNION ALL
SELECT 
    'User Info' as info_type,
    u.id as user_id,
    u.nama_lengkap as name,
    u.status,
    NULL as creator_id
FROM users u
WHERE u.id = 2
UNION ALL
SELECT 
    'Permission Info' as info_type,
    dp.document_id,
    dp.permission,
    dp.user_id,
    NULL as creator_id
FROM document_permissions dp
WHERE dp.document_id = 1 AND dp.user_id = 2;

-- 8. Test query yang sama dengan method hasPermission
-- (ganti 1 dengan document_id, ganti 2 dengan user_id yang sedang login)
SELECT 
    CASE 
        WHEN sd.is_public = 1 THEN 'PUBLIC_ACCESS'
        WHEN sd.created_by = 2 THEN 'CREATOR_ACCESS'
        WHEN dp.permission IS NOT NULL THEN CONCAT('PERMISSION_ACCESS: ', dp.permission)
        ELSE 'NO_ACCESS'
    END as access_result,
    sd.is_public,
    sd.created_by,
    dp.permission,
    dp.user_id
FROM shared_documents sd
LEFT JOIN document_permissions dp ON sd.id = dp.document_id AND dp.user_id = 2
WHERE sd.id = 1;

-- 9. Cek apakah ada masalah dengan user status
SELECT 
    u.id,
    u.nama_lengkap,
    u.email,
    u.status,
    CASE 
        WHEN u.status = 'A' THEN 'ACTIVE'
        WHEN u.status = 'I' THEN 'INACTIVE'
        ELSE 'UNKNOWN_STATUS'
    END as status_description
FROM users u
WHERE u.id IN (2, 26, 243);  -- ganti dengan user_id yang relevan

-- 10. Cek apakah ada masalah dengan document_permissions table structure
DESCRIBE document_permissions;

-- 11. Cek apakah ada data yang tidak konsisten
SELECT 
    dp.document_id,
    dp.user_id,
    dp.permission,
    CASE 
        WHEN u.id IS NULL THEN 'USER_NOT_FOUND'
        WHEN u.status != 'A' THEN 'USER_INACTIVE'
        WHEN sd.id IS NULL THEN 'DOCUMENT_NOT_FOUND'
        ELSE 'OK'
    END as data_status
FROM document_permissions dp
LEFT JOIN users u ON dp.user_id = u.id
LEFT JOIN shared_documents sd ON dp.document_id = sd.id
WHERE dp.document_id = 1;  -- ganti dengan document_id yang diakses

-- 12. Test query untuk method hasPermission dengan detail
-- (ganti 1 dengan document_id, ganti 2 dengan user_id yang sedang login)
SELECT 
    'Document Public' as check_type,
    sd.is_public as result,
    CASE WHEN sd.is_public = 1 THEN 'GRANTED' ELSE 'DENIED' END as access
FROM shared_documents sd
WHERE sd.id = 1
UNION ALL
SELECT 
    'User is Creator' as check_type,
    (sd.created_by = 2) as result,
    CASE WHEN sd.created_by = 2 THEN 'GRANTED' ELSE 'DENIED' END as access
FROM shared_documents sd
WHERE sd.id = 1
UNION ALL
SELECT 
    'User has Permission' as check_type,
    (dp.permission IS NOT NULL) as result,
    CASE WHEN dp.permission IS NOT NULL THEN CONCAT('GRANTED: ', dp.permission) ELSE 'DENIED' END as access
FROM shared_documents sd
LEFT JOIN document_permissions dp ON sd.id = dp.document_id AND dp.user_id = 2
WHERE sd.id = 1; 