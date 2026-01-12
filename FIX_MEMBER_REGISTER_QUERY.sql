-- =====================================================
-- FIX: MEMBER REGISTER QUERY YANG LAMBAT
-- =====================================================
-- Masalah: Query register mengambil semua 92rb+ data
-- Query: SELECT id, mobile_phone FROM member_apps_members 
--        WHERE mobile_phone IS NOT NULL AND mobile_phone != ''
-- 
-- Solusi: Tambahkan kolom normalized_mobile_phone
-- =====================================================

-- 1. CEK STRUKTUR TABEL SAAT INI
-- =====================================================
DESCRIBE member_apps_members;

-- 2. TAMBAHKAN KOLOM normalized_mobile_phone
-- =====================================================
-- Kolom ini akan menyimpan mobile_phone yang sudah di-normalize
-- (hanya angka dan +, tanpa spasi, dash, dll)
ALTER TABLE member_apps_members 
ADD COLUMN normalized_mobile_phone VARCHAR(20) NULL 
AFTER mobile_phone;

-- 3. BUAT INDEX UNTUK normalized_mobile_phone
-- =====================================================
CREATE INDEX idx_member_apps_members_normalized_mobile 
ON member_apps_members(normalized_mobile_phone);

-- 4. UPDATE DATA EXISTING
-- =====================================================
-- Normalize semua mobile_phone yang sudah ada
-- Hapus semua karakter selain angka dan +
UPDATE member_apps_members 
SET normalized_mobile_phone = REGEXP_REPLACE(
    TRIM(mobile_phone), 
    '[^0-9+]', 
    ''
)
WHERE mobile_phone IS NOT NULL 
  AND mobile_phone != '';

-- 5. VERIFIKASI UPDATE
-- =====================================================
-- Cek berapa banyak data yang sudah di-update
SELECT 
    COUNT(*) as total_members,
    COUNT(normalized_mobile_phone) as with_normalized_mobile,
    COUNT(*) - COUNT(normalized_mobile_phone) as without_normalized_mobile
FROM member_apps_members;

-- Cek beberapa contoh data
SELECT 
    id,
    mobile_phone,
    normalized_mobile_phone
FROM member_apps_members
WHERE mobile_phone IS NOT NULL
LIMIT 10;

-- 6. TEST QUERY DENGAN normalized_mobile_phone
-- =====================================================
-- Query yang optimal (setelah code diubah):
-- SELECT * FROM member_apps_members 
-- WHERE normalized_mobile_phone = '081234567890'
-- LIMIT 1;

-- Test dengan EXPLAIN
EXPLAIN SELECT * FROM member_apps_members 
WHERE normalized_mobile_phone = '081234567890'
LIMIT 1;

-- HASIL YANG DIHARAPKAN:
-- - type: ref (BUKAN 'ALL')
-- - key: idx_member_apps_members_normalized_mobile
-- - rows: 1 atau sangat kecil

-- 7. TRIGGER UNTUK AUTO-UPDATE (OPTIONAL)
-- =====================================================
-- Buat trigger untuk auto-normalize mobile_phone saat insert/update
-- Ini memastikan normalized_mobile_phone selalu ter-update

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

-- 8. VERIFIKASI TRIGGER
-- =====================================================
SHOW TRIGGERS LIKE 'member_apps_members%';

-- Test trigger dengan insert
-- INSERT INTO member_apps_members (member_id, email, mobile_phone, ...)
-- VALUES ('TEST001', 'test@test.com', '+62 812-3456-7890', ...);
-- 
-- Cek apakah normalized_mobile_phone terisi otomatis
-- SELECT mobile_phone, normalized_mobile_phone FROM member_apps_members WHERE member_id = 'TEST001';

-- =====================================================
-- CATATAN PENTING:
-- =====================================================
-- 1. Setelah kolom normalized_mobile_phone dibuat, 
--    code perlu diubah untuk menggunakan kolom ini
--
-- 2. Query register yang baru:
--    $normalizedMobile = preg_replace('/[^0-9+]/', '', trim($request->mobile_phone));
--    $existingMobile = MemberAppsMember::where('normalized_mobile_phone', $normalizedMobile)->exists();
--
-- 3. Trigger akan memastikan normalized_mobile_phone selalu ter-update
--    saat ada insert/update mobile_phone
--
-- 4. Index akan membuat query sangat cepat (<50ms)
-- =====================================================
