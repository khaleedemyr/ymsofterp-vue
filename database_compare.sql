-- Script untuk membandingkan struktur 2 database MySQL/MariaDB
-- Ganti nama database sesuai kebutuhan: database1 dan database2

-- ============================================
-- 1. MEMBANDINGKAN TABEL YANG ADA DI SETIAP DATABASE
-- ============================================

-- Tabel yang ada di database1 tapi tidak ada di database2
SELECT 
    'Tabel di DB1 tapi tidak di DB2' as keterangan,
    TABLE_NAME as nama_tabel,
    'database1' as database_source
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'database1'
AND TABLE_NAME NOT IN (
    SELECT TABLE_NAME 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'database2'
);

-- Tabel yang ada di database2 tapi tidak ada di database1
SELECT 
    'Tabel di DB2 tapi tidak di DB1' as keterangan,
    TABLE_NAME as nama_tabel,
    'database2' as database_source
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'database2'
AND TABLE_NAME NOT IN (
    SELECT TABLE_NAME 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'database1'
);

-- ============================================
-- 2. MEMBANDINGKAN KOLOM DALAM TABEL YANG SAMA
-- ============================================

-- Kolom yang berbeda antara 2 database (untuk tabel yang sama)
SELECT 
    t1.TABLE_NAME,
    t1.COLUMN_NAME,
    t1.COLUMN_TYPE as db1_type,
    t2.COLUMN_TYPE as db2_type,
    t1.IS_NULLABLE as db1_nullable,
    t2.IS_NULLABLE as db2_nullable,
    t1.COLUMN_DEFAULT as db1_default,
    t2.COLUMN_DEFAULT as db2_default,
    t1.COLUMN_KEY as db1_key,
    t2.COLUMN_KEY as db2_key,
    t1.EXTRA as db1_extra,
    t2.EXTRA as db2_extra
FROM INFORMATION_SCHEMA.COLUMNS t1
LEFT JOIN INFORMATION_SCHEMA.COLUMNS t2 
    ON t1.TABLE_NAME = t2.TABLE_NAME 
    AND t1.COLUMN_NAME = t2.COLUMN_NAME
    AND t2.TABLE_SCHEMA = 'database2'
WHERE t1.TABLE_SCHEMA = 'database1'
AND t2.TABLE_SCHEMA = 'database2'
AND (
    t1.COLUMN_TYPE != t2.COLUMN_TYPE 
    OR t1.IS_NULLABLE != t2.IS_NULLABLE
    OR COALESCE(t1.COLUMN_DEFAULT, '') != COALESCE(t2.COLUMN_DEFAULT, '')
    OR t1.COLUMN_KEY != t2.COLUMN_KEY
    OR t1.EXTRA != t2.EXTRA
    OR t2.COLUMN_NAME IS NULL
)
ORDER BY t1.TABLE_NAME, t1.ORDINAL_POSITION;

-- Kolom yang ada di database1 tapi tidak ada di database2
SELECT 
    'Kolom di DB1 tapi tidak di DB2' as keterangan,
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS t1
WHERE TABLE_SCHEMA = 'database1'
AND NOT EXISTS (
    SELECT 1 
    FROM INFORMATION_SCHEMA.COLUMNS t2
    WHERE t2.TABLE_SCHEMA = 'database2'
    AND t2.TABLE_NAME = t1.TABLE_NAME
    AND t2.COLUMN_NAME = t1.COLUMN_NAME
)
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Kolom yang ada di database2 tapi tidak ada di database1
SELECT 
    'Kolom di DB2 tapi tidak di DB1' as keterangan,
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS t2
WHERE TABLE_SCHEMA = 'database2'
AND NOT EXISTS (
    SELECT 1 
    FROM INFORMATION_SCHEMA.COLUMNS t1
    WHERE t1.TABLE_SCHEMA = 'database1'
    AND t1.TABLE_NAME = t2.TABLE_NAME
    AND t1.COLUMN_NAME = t2.COLUMN_NAME
)
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- ============================================
-- 3. MEMBANDINGKAN INDEX
-- ============================================

-- Index yang berbeda antara 2 database
SELECT 
    t1.TABLE_NAME,
    t1.INDEX_NAME,
    t1.COLUMN_NAME as db1_column,
    t2.COLUMN_NAME as db2_column,
    t1.SEQ_IN_INDEX as db1_seq,
    t2.SEQ_IN_INDEX as db2_seq,
    t1.NON_UNIQUE as db1_non_unique,
    t2.NON_UNIQUE as db2_non_unique
FROM INFORMATION_SCHEMA.STATISTICS t1
LEFT JOIN INFORMATION_SCHEMA.STATISTICS t2
    ON t1.TABLE_NAME = t2.TABLE_NAME
    AND t1.INDEX_NAME = t2.INDEX_NAME
    AND t1.COLUMN_NAME = t2.COLUMN_NAME
    AND t2.TABLE_SCHEMA = 'database2'
WHERE t1.TABLE_SCHEMA = 'database1'
AND (
    t2.INDEX_NAME IS NULL
    OR t1.NON_UNIQUE != t2.NON_UNIQUE
)
ORDER BY t1.TABLE_NAME, t1.INDEX_NAME, t1.SEQ_IN_INDEX;

-- ============================================
-- 4. MEMBANDINGKAN FOREIGN KEY
-- ============================================

-- Foreign Key yang berbeda
SELECT 
    t1.TABLE_NAME,
    t1.CONSTRAINT_NAME,
    t1.COLUMN_NAME as db1_column,
    t2.COLUMN_NAME as db2_column,
    t1.REFERENCED_TABLE_NAME as db1_ref_table,
    t2.REFERENCED_TABLE_NAME as db2_ref_table,
    t1.REFERENCED_COLUMN_NAME as db1_ref_column,
    t2.REFERENCED_COLUMN_NAME as db2_ref_column
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE t1
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE t2
    ON t1.TABLE_NAME = t2.TABLE_NAME
    AND t1.CONSTRAINT_NAME = t2.CONSTRAINT_NAME
    AND t1.COLUMN_NAME = t2.COLUMN_NAME
    AND t2.TABLE_SCHEMA = 'database2'
WHERE t1.TABLE_SCHEMA = 'database1'
AND t1.REFERENCED_TABLE_NAME IS NOT NULL
AND (
    t2.CONSTRAINT_NAME IS NULL
    OR t1.REFERENCED_TABLE_NAME != t2.REFERENCED_TABLE_NAME
    OR t1.REFERENCED_COLUMN_NAME != t2.REFERENCED_COLUMN_NAME
)
ORDER BY t1.TABLE_NAME, t1.CONSTRAINT_NAME;

-- ============================================
-- 5. RINGKASAN PERBANDINGAN
-- ============================================

-- Jumlah tabel per database
SELECT 
    TABLE_SCHEMA as database_name,
    COUNT(*) as jumlah_tabel
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA IN ('database1', 'database2')
GROUP BY TABLE_SCHEMA;

-- Jumlah kolom per tabel (untuk tabel yang sama)
SELECT 
    t1.TABLE_NAME,
    COUNT(DISTINCT t1.COLUMN_NAME) as kolom_db1,
    COUNT(DISTINCT t2.COLUMN_NAME) as kolom_db2,
    COUNT(DISTINCT t1.COLUMN_NAME) - COUNT(DISTINCT t2.COLUMN_NAME) as selisih
FROM INFORMATION_SCHEMA.COLUMNS t1
LEFT JOIN INFORMATION_SCHEMA.COLUMNS t2
    ON t1.TABLE_NAME = t2.TABLE_NAME
    AND t1.COLUMN_NAME = t2.COLUMN_NAME
    AND t2.TABLE_SCHEMA = 'database2'
WHERE t1.TABLE_SCHEMA = 'database1'
GROUP BY t1.TABLE_NAME
HAVING selisih != 0
ORDER BY t1.TABLE_NAME;

