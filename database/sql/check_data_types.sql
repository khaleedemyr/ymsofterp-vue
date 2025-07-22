-- =====================================================
-- CHECK DATA TYPES AND CASTING ISSUES
-- =====================================================

-- 1. Cek tipe data di tabel users
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'users' 
  AND TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('id', 'nama_lengkap', 'email', 'status');

-- 2. Cek tipe data di tabel shared_documents
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'shared_documents' 
  AND TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('id', 'created_by', 'is_public');

-- 3. Cek tipe data di tabel document_permissions
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'document_permissions' 
  AND TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('id', 'document_id', 'user_id', 'permission');

-- 4. Test comparison dengan tipe data yang berbeda
-- (ganti 1 dengan document_id, ganti 2 dengan user_id)
SELECT 
    'Document ID Type' as check_type,
    sd.id as document_id,
    TYPEOF(sd.id) as document_id_type,
    '2' as test_user_id,
    TYPEOF('2') as test_user_id_type,
    (sd.id = '2') as comparison_result
FROM shared_documents sd
WHERE sd.id = 1
UNION ALL
SELECT 
    'User ID Type' as check_type,
    u.id as user_id,
    TYPEOF(u.id) as user_id_type,
    '2' as test_user_id,
    TYPEOF('2') as test_user_id_type,
    (u.id = '2') as comparison_result
FROM users u
WHERE u.id = 2;

-- 5. Cek apakah ada masalah dengan boolean casting
SELECT 
    'is_public check' as check_type,
    sd.id,
    sd.is_public,
    TYPEOF(sd.is_public) as is_public_type,
    (sd.is_public = 1) as boolean_comparison,
    (sd.is_public = true) as boolean_comparison_2,
    (sd.is_public = '1') as string_comparison
FROM shared_documents sd
WHERE sd.id = 1;

-- 6. Test query dengan explicit casting
-- (ganti 1 dengan document_id, ganti 2 dengan user_id)
SELECT 
    'Explicit Cast Test' as test_type,
    sd.id as document_id,
    CAST(sd.id AS UNSIGNED) as document_id_unsigned,
    CAST(sd.created_by AS UNSIGNED) as created_by_unsigned,
    (CAST(sd.created_by AS UNSIGNED) = 2) as creator_check,
    (CAST(sd.id AS UNSIGNED) = 1) as document_check
FROM shared_documents sd
WHERE sd.id = 1;

-- 7. Cek apakah ada masalah dengan user status
SELECT 
    'User Status Check' as check_type,
    u.id,
    u.nama_lengkap,
    u.status,
    TYPEOF(u.status) as status_type,
    (u.status = 'A') as status_check,
    (u.status = 'A' AND u.id = 2) as combined_check
FROM users u
WHERE u.id = 2;

-- 8. Test query yang sama dengan method hasPermission dengan explicit casting
-- (ganti 1 dengan document_id, ganti 2 dengan user_id)
SELECT 
    CASE 
        WHEN CAST(sd.is_public AS UNSIGNED) = 1 THEN 'PUBLIC_ACCESS'
        WHEN CAST(sd.created_by AS UNSIGNED) = 2 THEN 'CREATOR_ACCESS'
        WHEN dp.permission IS NOT NULL THEN CONCAT('PERMISSION_ACCESS: ', dp.permission)
        ELSE 'NO_ACCESS'
    END as access_result,
    sd.is_public,
    CAST(sd.is_public AS UNSIGNED) as is_public_unsigned,
    sd.created_by,
    CAST(sd.created_by AS UNSIGNED) as created_by_unsigned,
    dp.permission,
    dp.user_id,
    CAST(dp.user_id AS UNSIGNED) as user_id_unsigned
FROM shared_documents sd
LEFT JOIN document_permissions dp ON CAST(sd.id AS UNSIGNED) = CAST(dp.document_id AS UNSIGNED) AND CAST(dp.user_id AS UNSIGNED) = 2
WHERE CAST(sd.id AS UNSIGNED) = 1;

-- 9. Cek apakah ada masalah dengan NULL values
SELECT 
    'NULL Check' as check_type,
    COUNT(*) as total_permissions,
    COUNT(dp.user_id) as non_null_user_ids,
    COUNT(dp.document_id) as non_null_document_ids,
    COUNT(dp.permission) as non_null_permissions
FROM document_permissions dp
WHERE dp.document_id = 1;  -- ganti dengan document_id

-- 10. Test query dengan COALESCE untuk handle NULL
-- (ganti 1 dengan document_id, ganti 2 dengan user_id)
SELECT 
    'COALESCE Test' as test_type,
    sd.id as document_id,
    COALESCE(sd.is_public, 0) as is_public_coalesced,
    COALESCE(sd.created_by, 0) as created_by_coalesced,
    dp.user_id,
    COALESCE(dp.user_id, 0) as user_id_coalesced,
    dp.permission,
    CASE 
        WHEN COALESCE(sd.is_public, 0) = 1 THEN 'PUBLIC_ACCESS'
        WHEN COALESCE(sd.created_by, 0) = 2 THEN 'CREATOR_ACCESS'
        WHEN dp.permission IS NOT NULL THEN CONCAT('PERMISSION_ACCESS: ', dp.permission)
        ELSE 'NO_ACCESS'
    END as access_result
FROM shared_documents sd
LEFT JOIN document_permissions dp ON sd.id = dp.document_id AND dp.user_id = 2
WHERE sd.id = 1; 