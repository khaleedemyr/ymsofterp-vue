-- =====================================================
-- CHECK IF DOCUMENT EXISTS - ROUTE MODEL BINDING ISSUE
-- =====================================================

-- 1. Cek apakah document dengan ID 1 ada
SELECT 
    id,
    title,
    filename,
    created_by,
    is_public,
    created_at,
    updated_at
FROM shared_documents 
WHERE id = 1;

-- 2. Cek semua document yang ada
SELECT 
    id,
    title,
    filename,
    created_by,
    is_public,
    created_at
FROM shared_documents 
ORDER BY id;

-- 3. Cek struktur tabel shared_documents
DESCRIBE shared_documents;

-- 4. Cek apakah ada masalah dengan primary key
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'shared_documents' 
  AND TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'id';

-- 5. Cek apakah ada auto increment
SHOW CREATE TABLE shared_documents;

-- 6. Test query dengan explicit ID
SELECT 
    'Document 1' as test_name,
    COUNT(*) as found_count
FROM shared_documents 
WHERE id = 1
UNION ALL
SELECT 
    'Document 2' as test_name,
    COUNT(*) as found_count
FROM shared_documents 
WHERE id = 2
UNION ALL
SELECT 
    'All Documents' as test_name,
    COUNT(*) as found_count
FROM shared_documents;

-- 7. Cek apakah ada document yang dibuat oleh user 2
SELECT 
    id,
    title,
    filename,
    created_by,
    is_public,
    created_at
FROM shared_documents 
WHERE created_by = 2
ORDER BY created_at DESC;

-- 8. Cek document yang memiliki permission untuk user 2
SELECT 
    sd.id,
    sd.title,
    sd.filename,
    sd.created_by,
    sd.is_public,
    dp.permission,
    dp.user_id
FROM shared_documents sd
INNER JOIN document_permissions dp ON sd.id = dp.document_id
WHERE dp.user_id = 2
ORDER BY sd.created_at DESC;

-- 9. Debug - cek semua data yang terkait dengan document 1
SELECT 
    'Document Info' as data_type,
    sd.id,
    sd.title,
    sd.created_by,
    sd.is_public
FROM shared_documents sd
WHERE sd.id = 1
UNION ALL
SELECT 
    'Permissions' as data_type,
    dp.document_id,
    dp.permission,
    dp.user_id,
    NULL as is_public
FROM document_permissions dp
WHERE dp.document_id = 1
UNION ALL
SELECT 
    'Creator Info' as data_type,
    u.id,
    u.nama_lengkap,
    u.email,
    u.status
FROM shared_documents sd
LEFT JOIN users u ON sd.created_by = u.id
WHERE sd.id = 1;

-- 10. Test route model binding simulation
-- (Query yang sama dengan yang akan dijalankan Laravel)
SELECT 
    sd.*
FROM shared_documents sd
WHERE sd.id = 1
LIMIT 1;

-- 11. Cek apakah ada masalah dengan table name
SHOW TABLES LIKE '%shared%';
SHOW TABLES LIKE '%document%';

-- 12. Cek apakah ada masalah dengan database connection
SELECT DATABASE() as current_database;
SELECT USER() as current_user; 