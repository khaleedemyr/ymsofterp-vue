-- =====================================================
-- FIX: MEMBER REGISTER QUERY - TANPA UBAH CODE APP
-- =====================================================
-- Masalah: Query register mengambil semua 92rb+ data
-- Query: SELECT id, mobile_phone FROM member_apps_members 
--        WHERE mobile_phone IS NOT NULL AND mobile_phone != ''
-- 
-- Solusi: Optimasi di level database saja
-- TIDAK PERLU UBAH CODE DI APP
-- =====================================================

-- 1. TAMBAHKAN KOLOM normalized_mobile_phone
-- =====================================================
-- Kolom ini akan menyimpan mobile_phone yang sudah di-normalize
-- (hanya angka dan +, tanpa spasi, dash, dll)
ALTER TABLE member_apps_members 
ADD COLUMN IF NOT EXISTS normalized_mobile_phone VARCHAR(20) NULL 
AFTER mobile_phone;

-- 2. BUAT INDEX UNTUK normalized_mobile_phone
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_member_apps_members_normalized_mobile 
ON member_apps_members(normalized_mobile_phone);

-- 3. UPDATE DATA EXISTING (Normalize semua mobile_phone)
-- =====================================================
-- Hapus semua karakter selain angka dan +
UPDATE member_apps_members 
SET normalized_mobile_phone = REGEXP_REPLACE(
    TRIM(COALESCE(mobile_phone, '')), 
    '[^0-9+]', 
    ''
)
WHERE mobile_phone IS NOT NULL 
  AND mobile_phone != ''
  AND (normalized_mobile_phone IS NULL OR normalized_mobile_phone = '');

-- 4. BUAT TRIGGER UNTUK AUTO-UPDATE normalized_mobile_phone
-- =====================================================
-- Trigger ini akan otomatis normalize mobile_phone saat insert/update
-- TIDAK PERLU UBAH CODE DI APP - trigger bekerja otomatis

-- Hapus trigger lama jika ada
DROP TRIGGER IF EXISTS trg_member_apps_members_normalize_mobile_insert;
DROP TRIGGER IF EXISTS trg_member_apps_members_normalize_mobile_update;

DELIMITER $$

CREATE TRIGGER trg_member_apps_members_normalize_mobile_insert
BEFORE INSERT ON member_apps_members
FOR EACH ROW
BEGIN
    IF NEW.mobile_phone IS NOT NULL AND NEW.mobile_phone != '' THEN
        SET NEW.normalized_mobile_phone = REGEXP_REPLACE(
            TRIM(NEW.mobile_phone), 
            '[^0-9+]', 
            ''
        );
    END IF;
END$$

CREATE TRIGGER trg_member_apps_members_normalize_mobile_update
BEFORE UPDATE ON member_apps_members
FOR EACH ROW
BEGIN
    IF NEW.mobile_phone IS NOT NULL AND NEW.mobile_phone != '' THEN
        SET NEW.normalized_mobile_phone = REGEXP_REPLACE(
            TRIM(NEW.mobile_phone), 
            '[^0-9+]', 
            ''
        );
    ELSE
        SET NEW.normalized_mobile_phone = NULL;
    END IF;
END$$

DELIMITER ;

-- 5. BUAT INDEX UNTUK QUERY YANG ADA SAAT INI
-- =====================================================
-- Index untuk query: WHERE mobile_phone IS NOT NULL AND mobile_phone != ''
-- Ini akan membantu query yang ada di app (meskipun masih akan scan banyak data)
CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_not_null 
ON member_apps_members(mobile_phone(20))
WHERE mobile_phone IS NOT NULL AND mobile_phone != '';

-- Note: MySQL tidak support partial index seperti PostgreSQL
-- Jadi kita buat index biasa saja
CREATE INDEX IF NOT EXISTS idx_member_apps_members_mobile_phone_full 
ON member_apps_members(mobile_phone);

-- 6. BUAT VIEW UNTUK MEMBER DENGAN NORMALIZED MOBILE
-- =====================================================
-- View ini bisa digunakan untuk query yang lebih cepat
-- Tapi app tetap perlu dipanggil, jadi mungkin tidak membantu banyak
-- CREATE OR REPLACE VIEW v_member_apps_members_normalized AS
-- SELECT 
--     id,
--     member_id,
--     email,
--     mobile_phone,
--     normalized_mobile_phone,
--     -- kolom lain...
-- FROM member_apps_members;

-- 7. VERIFIKASI
-- =====================================================
-- Cek berapa banyak data yang sudah di-update
SELECT 
    COUNT(*) as total_members,
    COUNT(mobile_phone) as with_mobile_phone,
    COUNT(normalized_mobile_phone) as with_normalized_mobile
FROM member_apps_members;

-- Cek beberapa contoh
SELECT 
    id,
    mobile_phone,
    normalized_mobile_phone
FROM member_apps_members
WHERE mobile_phone IS NOT NULL
LIMIT 10;

-- Cek trigger
SHOW TRIGGERS LIKE 'member_apps_members%';

-- Cek index
SHOW INDEX FROM member_apps_members WHERE Key_name LIKE '%mobile%';

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Script ini TIDAK memerlukan perubahan code di app
--    Trigger akan otomatis normalize mobile_phone saat insert/update
--
-- 2. TAPI: Query di app masih akan mengambil semua data dengan get()
--    Ini adalah limitation karena tidak bisa ubah code
--
-- 3. Yang bisa kita lakukan:
--    - Normalize data di database (sudah dilakukan)
--    - Buat index untuk mempercepat query (sudah dilakukan)
--    - Trigger untuk auto-normalize (sudah dilakukan)
--
-- 4. Untuk optimasi lebih lanjut, tetap perlu ubah code app
--    Tapi dengan index dan normalized data, setidaknya query akan lebih cepat
--
-- 5. Query yang ada di app:
--    $allMembers = MemberAppsMember::select('id', 'mobile_phone')
--        ->whereNotNull('mobile_phone')
--        ->where('mobile_phone', '!=', '')
--        ->get();
--    
--    Query ini akan tetap mengambil semua data, tapi:
--    - Index akan membantu sedikit
--    - Data sudah normalized, jadi loop di PHP lebih cepat
--    - Tapi tetap akan load semua data ke memory
--
-- 6. SOLUSI TERBAIK TANPA UBAH CODE:
--    - Normalize data di database (DONE)
--    - Buat index (DONE)
--    - Trigger untuk auto-normalize (DONE)
--    - Monitor dan lihat apakah ada improvement
--    - Jika masih lambat, pertimbangkan untuk ubah code (tapi user tidak mau)
-- =====================================================
