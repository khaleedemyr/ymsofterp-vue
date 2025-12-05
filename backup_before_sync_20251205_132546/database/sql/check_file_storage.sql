-- =====================================================
-- CHECK FILE STORAGE - DEBUG FILE LOADING ISSUE
-- =====================================================

-- 1. Cek data document yang ada
SELECT 
    id,
    title,
    filename,
    file_path,
    file_type,
    file_size,
    created_by,
    created_at
FROM shared_documents 
ORDER BY id;

-- 2. Cek apakah file_path ada dan valid
SELECT 
    id,
    title,
    filename,
    file_path,
    CASE 
        WHEN file_path IS NULL THEN 'NULL_PATH'
        WHEN file_path = '' THEN 'EMPTY_PATH'
        ELSE 'HAS_PATH'
    END as path_status,
    file_type,
    file_size
FROM shared_documents 
ORDER BY id;

-- 3. Test file path yang akan digunakan
-- (Ganti dengan file_path yang ada di database)
SELECT 
    id,
    title,
    file_path,
    CONCAT('storage/app/public/', file_path) as full_storage_path,
    CONCAT('public/storage/', file_path) as public_path
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id yang diakses

-- 4. Cek apakah ada file yang tidak memiliki path
SELECT 
    COUNT(*) as total_documents,
    COUNT(file_path) as documents_with_path,
    COUNT(CASE WHEN file_path IS NULL OR file_path = '' THEN 1 END) as documents_without_path
FROM shared_documents;

-- 5. Cek document yang dibuat oleh user tertentu
SELECT 
    id,
    title,
    filename,
    file_path,
    file_type,
    file_size,
    created_by,
    created_at
FROM shared_documents 
WHERE created_by = 2  -- ganti dengan user_id yang sedang login
ORDER BY created_at DESC;

-- 6. Debug - cek semua data yang diperlukan untuk OnlyOffice
SELECT 
    'Document Info' as data_type,
    sd.id,
    sd.title,
    sd.filename,
    sd.file_path,
    sd.file_type,
    sd.file_size
FROM shared_documents sd
WHERE sd.id = 1  -- ganti dengan document_id
UNION ALL
SELECT 
    'Storage Paths' as data_type,
    sd.id,
    CONCAT('storage/app/public/', sd.file_path) as storage_path,
    CONCAT('public/storage/', sd.file_path) as public_path,
    CASE 
        WHEN sd.file_path IS NULL THEN 'NULL'
        WHEN sd.file_path = '' THEN 'EMPTY'
        ELSE 'VALID'
    END as path_status,
    sd.file_type,
    sd.file_size
FROM shared_documents sd
WHERE sd.id = 1;  -- ganti dengan document_id

-- 7. Cek apakah ada masalah dengan file extension
SELECT 
    id,
    title,
    filename,
    file_type,
    CASE 
        WHEN file_type IN ('xlsx', 'xls') THEN 'EXCEL'
        WHEN file_type IN ('docx', 'doc') THEN 'WORD'
        WHEN file_type IN ('pptx', 'ppt') THEN 'POWERPOINT'
        ELSE 'UNKNOWN'
    END as file_category
FROM shared_documents 
ORDER BY id;

-- 8. Test URL yang akan digunakan OnlyOffice
-- (Ganti dengan document_id yang diakses)
SELECT 
    id,
    title,
    CONCAT('http://localhost:8000/shared-documents/', id, '/download') as download_url,
    CONCAT('http://localhost:8000/shared-documents/', id, '/callback') as callback_url
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id

-- 9. Cek apakah ada document yang bisa diakses user
SELECT 
    sd.id,
    sd.title,
    sd.filename,
    sd.file_path,
    sd.created_by,
    dp.permission,
    dp.user_id
FROM shared_documents sd
LEFT JOIN document_permissions dp ON sd.id = dp.document_id
WHERE sd.created_by = 2  -- ganti dengan user_id yang sedang login
   OR dp.user_id = 2     -- ganti dengan user_id yang sedang login
   OR sd.is_public = 1
ORDER BY sd.created_at DESC;

-- 10. Cek file size dan type yang valid
SELECT 
    id,
    title,
    filename,
    file_type,
    file_size,
    CASE 
        WHEN file_size > 0 THEN 'VALID_SIZE'
        WHEN file_size = 0 THEN 'ZERO_SIZE'
        WHEN file_size IS NULL THEN 'NULL_SIZE'
        ELSE 'INVALID_SIZE'
    END as size_status
FROM shared_documents 
ORDER BY id; 