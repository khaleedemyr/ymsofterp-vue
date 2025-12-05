-- =====================================================
-- TEST CORRECT FILE URL - BASED ON ACTUAL FILE LOCATION
-- =====================================================

-- 1. Cek file path yang benar berdasarkan struktur storage
SELECT 
    id,
    title,
    filename,
    file_path,
    -- Path yang benar untuk storage Laravel
    CONCAT('storage/', file_path) as correct_storage_path,
    -- Path yang salah (sebelumnya)
    CONCAT('storage/app/public/', file_path) as wrong_storage_path,
    file_type,
    file_size
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id yang diakses

-- 2. Test URL yang benar untuk OnlyOffice
SELECT 
    id,
    title,
    filename,
    file_path,
    -- URL download yang benar
    CONCAT('http://localhost:8000/shared-documents/', id, '/download') as correct_download_url,
    -- URL callback yang benar
    CONCAT('http://localhost:8000/shared-documents/', id, '/callback') as correct_callback_url,
    file_type
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id

-- 3. Cek apakah file benar-benar ada di lokasi yang benar
-- (Ganti dengan file_path yang ada di database)
SELECT 
    id,
    title,
    file_path,
    -- Path yang benar untuk storage Laravel
    CONCAT('storage/', file_path) as storage_path,
    -- Path lengkap untuk file_exists check
    CONCAT('/path/to/laravel/storage/', file_path) as full_path
FROM shared_documents 
WHERE file_path LIKE 'shared-documents/%'
ORDER BY id;

-- 4. Debug - cek semua file di shared-documents
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
        WHEN file_path LIKE 'app/public/%' THEN 'WRONG_PATH'
        WHEN file_path IS NULL THEN 'NULL_PATH'
        ELSE 'UNKNOWN_PATH'
    END as path_status
FROM shared_documents 
ORDER BY created_at DESC;

-- 5. Test untuk file yang spesifik (sesuai gambar)
SELECT 
    id,
    title,
    filename,
    file_path,
    -- Path yang benar
    CONCAT('storage/', file_path) as correct_path,
    -- URL yang benar
    CONCAT('http://localhost:8000/shared-documents/', id, '/download') as download_url,
    file_type,
    file_size
FROM shared_documents 
WHERE filename LIKE '%price_update_template%'
   OR file_path LIKE '%price_update_template%'
ORDER BY created_at DESC;

-- 6. Cek struktur file path yang benar
SELECT 
    'Expected Structure' as info,
    'storage/shared-documents/filename.xlsx' as correct_format,
    'storage/app/public/shared-documents/filename.xlsx' as wrong_format
UNION ALL
SELECT 
    'Actual File Path',
    file_path,
    CONCAT('app/public/', file_path) as wrong_format
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id

-- 7. Test URL generation untuk OnlyOffice
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
            'callbackUrl', CONCAT('http://localhost:8000/shared-documents/', id, '/callback')
        )
    ) as onlyoffice_config
FROM shared_documents 
WHERE id = 1;  -- ganti dengan document_id 