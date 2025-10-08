-- Check if enroll test tables exist
-- Jalankan query ini untuk mengecek apakah tabel sudah ada

-- Check enroll_tests table
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'enroll_tests table EXISTS'
        ELSE 'enroll_tests table NOT EXISTS'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'enroll_tests';

-- Check test_results table
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'test_results table EXISTS'
        ELSE 'test_results table NOT EXISTS'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'test_results';

-- Check test_answers table
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'test_answers table EXISTS'
        ELSE 'test_answers table NOT EXISTS'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'test_answers';

-- Check if master_soal table exists (required for foreign key)
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'master_soal table EXISTS'
        ELSE 'master_soal table NOT EXISTS - REQUIRED!'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'master_soal';

-- Check if soal_pertanyaan table exists (required for foreign key)
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'soal_pertanyaan table EXISTS'
        ELSE 'soal_pertanyaan table NOT EXISTS - REQUIRED!'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'soal_pertanyaan';
