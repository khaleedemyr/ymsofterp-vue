-- =====================================================
-- TEST DOCUMENT DOWNLOAD - WITH CORRECT FILE PATH
-- =====================================================

-- 1. Cek document yang ada dengan file path yang benar
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
WHERE file_path LIKE 'shared-documents/%'
ORDER BY id;

-- 2. Test untuk file yang spesifik (sesuai gambar)
SELECT 
    id,
    title,
    filename,
    file_path,
    file_type,
    file_size,
    -- Path yang benar untuk storage Laravel
    CONCAT('storage/app/public/', file_path) as correct_storage_path,
    -- URL download yang benar
    CONCAT('http://localhost:8000/shared-documents/', id, '/download') as download_url,
    -- URL callback yang benar
    CONCAT('http://localhost:8000/shared-documents/', id, '/callback') as callback_url
FROM shared_documents 
WHERE filename LIKE '%price_update_template%'
   OR file_path LIKE '%price_update_template%'
ORDER BY created_at DESC;

-- 3. Cek document yang bisa diakses user (ganti user_id = 2)
SELECT 
    sd.id,
    sd.title,
    sd.filename,
    sd.file_path,
    sd.file_type,
    sd.file_size,
    sd.created_by,
    dp.permission,
    dp.user_id,
    -- URL untuk OnlyOffice
    CONCAT('http://localhost:8000/shared-documents/', sd.id, '/download') as onlyoffice_url
FROM shared_documents sd
LEFT JOIN document_permissions dp ON sd.id = dp.document_id
WHERE sd.created_by = 2  -- ganti dengan user_id yang sedang login
   OR dp.user_id = 2     -- ganti dengan user_id yang sedang login
   OR sd.is_public = 1
ORDER BY sd.created_at DESC;

-- 4. Debug - cek semua data yang diperlukan untuk OnlyOffice
SELECT 
    'Document Info' as data_type,
    sd.id,
    sd.title,
    sd.filename,
    sd.file_path,
    sd.file_type,
    sd.file_size
FROM shared_documents sd
WHERE sd.id = 1  -- ganti dengan document_id yang diakses
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
WHERE sd.id = 1;  -- ganti dengan document_id yang diakses

-- 5. Test URL generation untuk OnlyOffice dengan data yang benar
SELECT 
    id,
    title,
    filename,
    file_path,
    file_type,
    -- Config untuk OnlyOffice
    JSON_OBJECT(
        'document', JSON_OBJECT(
            'fileType', file_type,
            'key', CAST(id AS CHAR),
            'title', title,
            'url', CONCAT('http://localhost:8000/shared-documents/', id, '/download')
        ),
        'documentType', CASE 
            WHEN file_type IN ('xlsx', 'xls') THEN 'spreadsheet'
            WHEN file_type IN ('docx', 'doc') THEN 'text'
            WHEN file_type IN ('pptx', 'ppt') THEN 'presentation'
            ELSE 'text'
        END,
        'editorConfig', JSON_OBJECT(
            'mode', 'edit',
            'callbackUrl', CONCAT('http://localhost:8000/shared-documents/', id, '/callback'),
            'user', JSON_OBJECT(
                'id', '2',
                'name', 'User'
            ),
            'customization', JSON_OBJECT(
                'chat', false,
                'comments', true,
                'compactToolbar', false,
                'feedback', false,
                'forcesave', true,
                'submitForm', false
            )
        ),
        'height', '600px',
        'width', '100%'
    ) as onlyoffice_config
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id yang diakses

-- 6. Cek apakah file benar-benar ada di storage
-- (Ganti dengan file_path yang ada di database)
SELECT 
    id,
    title,
    file_path,
    -- Path yang benar untuk storage Laravel
    CONCAT('storage/app/public/', file_path) as storage_path,
    -- Path lengkap untuk file_exists check
    CONCAT('D:/Gawean/YM/web/ymsofterp/storage/app/public/', file_path) as full_path
FROM shared_documents 
WHERE file_path LIKE 'shared-documents/%'
ORDER BY id;

-- 7. Test untuk semua document yang ada
SELECT 
    id,
    title,
    filename,
    file_path,
    file_type,
    file_size,
    created_at,
    -- Status file berdasarkan path
    CASE 
        WHEN file_path LIKE 'shared-documents/%' THEN 'CORRECT_PATH'
        WHEN file_path IS NULL THEN 'NULL_PATH'
        WHEN file_path = '' THEN 'EMPTY_PATH'
        ELSE 'UNKNOWN_PATH'
    END as path_status,
    -- URL untuk testing
    CONCAT('http://localhost:8000/shared-documents/', id, '/download') as test_url
FROM shared_documents 
ORDER BY created_at DESC; 